<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\ReceivingReportStatus;
use App\Events\Procurement\PurchaseOrderFullyReceived;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivingReport;
use App\Support\AttachmentSync;
use App\Support\ProcurementSearch;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceivingReportService
{
    public function __construct(private PurchaseOrderService $purchaseOrderService) {}

    /**
     * List receiving reports with optional filtering.
     *
     * @param  array{search?: string|null, status?: string|null, per_page?: int|string|null, purchase_order_id?: int|null}  $filters
     * @return LengthAwarePaginator<int, ReceivingReport>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 10), 100));

        return ReceivingReport::query()
            ->orderByDesc('created_at')
            ->with(['purchaseOrder.supplier', 'receivedBy'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search) {
                    $query->where('remarks', 'like', '%'.$search.'%')
                        ->orWhere('received_date', 'like', '%'.$search.'%')
                        ->orWhereRaw('LOWER(CAST(receiving_reports.status AS TEXT)) LIKE ?', ['%'.mb_strtolower($search).'%']);

                    foreach (ProcurementSearch::documentNumberSearchPatterns($search) as $docPattern) {
                        $query->orWhere('rr_number', 'like', '%'.$docPattern.'%');
                    }

                    $query->orWhereHas('receivedBy', function ($q) use ($search) {
                        $q->where(function ($q2) use ($search) {
                            $q2->where('name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%');
                        });
                    });

                    $trimmedSearch = trim($search);
                    if ($trimmedSearch !== '' && ctype_digit($trimmedSearch)) {
                        $query->orWhere('received_by_id', (int) $trimmedSearch);
                    }

                    $query->orWhereHas('purchaseOrder', function ($q) use ($search) {
                        $q->where(function ($pq) use ($search) {
                            $pq->where('expected_delivery_date', 'like', '%'.$search.'%')
                                ->orWhere('terms_and_conditions', 'like', '%'.$search.'%');

                            foreach (ProcurementSearch::documentNumberSearchPatterns($search) as $docPattern) {
                                $pq->orWhere('po_number', 'like', '%'.$docPattern.'%');
                            }
                        })
                            ->orWhereHas('supplier', function ($sq) use ($search) {
                                ProcurementSearch::applyCaseInsensitiveLike($sq, 'name', $search);
                            })
                            ->orWhereHas('purchaseRequisition', fn ($prq) => $prq->where('title', 'like', '%'.$search.'%'));
                    });
                });
            })
            ->when($filters['status'] ?? null, function ($query, string $status): void {
                $query->where('status', $status);
            })
            ->when($filters['purchase_order_id'] ?? null, function ($query, int $poId): void {
                $query->where('purchase_order_id', $poId);
            })
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Create a draft receiving report for a purchase order. Draft quantities
     * already count as allocated — only deletion or cancellation frees them.
     *
     * Validates that:
     * - The PO is in a receivable status (RELEASED/ordered or PARTIALLY_RECEIVED)
     * - Received quantities do not exceed what remains to be delivered
     *
     * @param  array{received_by_id: int, received_date: string, remarks?: string|null, items: array<int, array{purchase_order_item_id: int, quantity_received: int, quantity_rejected?: int, remarks?: string|null}>, notes?: array<int, array{id?: int, content?: string|null}>}  $data
     *
     * @throws ValidationException
     */
    /**
     * @param  list<UploadedFile>|null  $attachmentFiles
     */
    public function create(PurchaseOrder $purchaseOrder, array $data, ?array $attachmentFiles = null): ReceivingReport
    {
        return DB::transaction(function () use ($purchaseOrder, $data, $attachmentFiles) {
            $this->assertPurchaseOrderIsReceivable($purchaseOrder);
            $this->lockPurchaseOrderItems($purchaseOrder, $data['items']);
            $this->validateDeliveryQuantities($purchaseOrder, $data['items']);

            /** @var ReceivingReport $receivingReport */
            $receivingReport = $purchaseOrder->receivingReports()->create([
                'received_by_id' => $data['received_by_id'],
                'received_date' => $data['received_date'],
                'remarks' => $data['remarks'] ?? null,
                'status' => ReceivingReportStatus::DRAFT,
            ]);

            $receivingReport->items()->createMany($data['items']);

            $this->syncNotes($receivingReport, $data);

            activity('allocation')
                ->performedOn($receivingReport)
                ->withProperties(['items' => $this->itemsSnapshot($data['items'])])
                ->log('allocated');

            $this->syncPurchaseOrderReceivingStatus($purchaseOrder);

            AttachmentSync::sync($receivingReport, $attachmentFiles, null);

            return $receivingReport->load(['purchaseOrder.supplier', 'receivedBy', 'items.purchaseOrderItem']);
        });
    }

    /**
     * Update a draft receiving report, revalidating delivery quantities against
     * the pool with this report's own recorded quantities excluded.
     *
     * @param  array{received_date?: string, remarks?: string|null, items?: array<int, array{id?: int, purchase_order_item_id: int, quantity_received: int, quantity_rejected?: int, remarks?: string|null}>, notes?: array<int, array{id?: int, content?: string|null}>}  $data
     *
     * @throws ValidationException
     */
    /**
     * @param  list<UploadedFile>|null  $attachmentFiles
     * @param  list<int>|null  $removedAttachmentIds
     */
    public function update(ReceivingReport $receivingReport, array $data, ?array $attachmentFiles = null, ?array $removedAttachmentIds = null): ReceivingReport
    {
        return DB::transaction(function () use ($receivingReport, $data, $attachmentFiles, $removedAttachmentIds) {
            $this->assertReceivingReportIsDraft($receivingReport);

            $receivingReport->update($data);

            if (isset($data['items']) && \is_array($data['items'])) {
                $purchaseOrder = $receivingReport->purchaseOrder;

                $incomingItemIds = collect($data['items'])->pluck('id')->filter()->toArray();

                // Delete items not in incoming request
                $receivingReport->items()->whereNotIn('id', $incomingItemIds)->delete();

                // Validate deliveries excluding this report's current items (they'll be replaced)
                $this->lockPurchaseOrderItems($purchaseOrder, $data['items']);
                $this->validateDeliveryQuantities($purchaseOrder, $data['items'], $receivingReport->id);

                // Update existing or create new
                foreach ($data['items'] as $itemData) {
                    if (isset($itemData['id'])) {
                        $receivingReport->items()->where('id', $itemData['id'])->update($itemData);
                    } else {
                        $receivingReport->items()->create($itemData);
                    }
                }

                activity('allocation')
                    ->performedOn($receivingReport)
                    ->withProperties(['items' => $this->itemsSnapshot($data['items'])])
                    ->log('reallocated');

                $this->syncPurchaseOrderReceivingStatus($purchaseOrder);
            }

            $this->syncNotes($receivingReport, $data);

            AttachmentSync::sync($receivingReport, $attachmentFiles, $removedAttachmentIds);

            return $receivingReport->fresh()->load(['purchaseOrder.supplier', 'receivedBy', 'items.purchaseOrderItem']);
        });
    }

    /**
     * Delete a draft receiving report and recalculate PO receiving status,
     * returning its quantities to the pool.
     *
     * @throws ValidationException
     */
    public function delete(ReceivingReport $receivingReport, ?string $reason = null): void
    {
        DB::transaction(function () use ($receivingReport, $reason) {
            $this->assertReceivingReportIsDraft($receivingReport);

            $purchaseOrder = $receivingReport->purchaseOrder;

            activity('allocation')
                ->performedOn($receivingReport)
                ->withProperties([
                    'items' => $this->itemsSnapshot($receivingReport->items->map->only(['purchase_order_item_id', 'quantity_received'])->all()),
                    'reason' => $reason,
                ])
                ->log('deallocated');

            $receivingReport->items()->delete();
            $receivingReport->notes()->delete();
            $receivingReport->delete();

            $this->syncPurchaseOrderReceivingStatus($purchaseOrder);
        });
    }

    /**
     * Assert that the purchase order is in a receivable status. A PO becomes
     * receivable only once it has been marked as ordered (sealed).
     *
     * @throws ValidationException
     */
    private function assertPurchaseOrderIsReceivable(PurchaseOrder $purchaseOrder): void
    {
        $receivableStatuses = [
            PurchaseOrderStatus::RELEASED,
            PurchaseOrderStatus::PARTIALLY_RECEIVED,
        ];

        if (! \in_array($purchaseOrder->status, $receivableStatuses, true)) {
            throw ValidationException::withMessages([
                'purchase_order_id' => 'The purchase order must be marked as ordered before creating a receiving report.',
            ]);
        }
    }

    /**
     * Assert that the receiving report is in DRAFT status.
     *
     * @throws ValidationException
     */
    private function assertReceivingReportIsDraft(ReceivingReport $receivingReport): void
    {
        if ($receivingReport->status !== ReceivingReportStatus::DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only draft receiving reports can be modified.',
            ]);
        }
    }

    /**
     * Lock the PO item rows being received so concurrent receiving reports can't
     * over-receive the same line (read-sum-then-write race). No-op on SQLite.
     *
     * @param  array<int, array{purchase_order_item_id: int}>  $items
     */
    private function lockPurchaseOrderItems(PurchaseOrder $purchaseOrder, array $items): void
    {
        $poItemIds = collect($items)->pluck('purchase_order_item_id')->unique()->all();

        $purchaseOrder->items()->whereIn('id', $poItemIds)->lockForUpdate()->get();
    }

    /**
     * Validate that delivery quantities do not exceed what remains on the PO.
     *
     * @param  array<int, array{purchase_order_item_id: int, quantity_received: int}>  $items
     * @param  int|null  $excludeReceivingReportId  Exclude this report's items when recalculating (for updates)
     *
     * @throws ValidationException
     */
    private function validateDeliveryQuantities(PurchaseOrder $purchaseOrder, array $items, ?int $excludeReceivingReportId = null): void
    {
        $purchaseOrder->loadMissing('items');
        $errors = [];

        // Quantities already claimed by earlier rows of this same payload, per PO item.
        // Guards over-receipt via duplicate rows for callers that bypass the
        // request-level `distinct` rule.
        $pendingByPoItem = [];

        foreach ($items as $index => $item) {
            /** @var PurchaseOrderItem|null $poItem */
            $poItem = $purchaseOrder->items->firstWhere('id', $item['purchase_order_item_id']);

            if (! $poItem) {
                $errors["items.{$index}.purchase_order_item_id"] = 'This item does not belong to the purchase order.';

                continue;
            }

            if ($poItem->is_omitted) {
                $errors["items.{$index}.purchase_order_item_id"] = 'This item has been omitted and cannot be received.';

                continue;
            }

            // Recorded quantity already excludes rejected/cancelled receiving reports,
            // minus this report's own rows when updating.
            $existingReceived = (int) $poItem->receivingReportItems()
                ->counted()
                ->when($excludeReceivingReportId, function ($query, int $rrId): void {
                    $query->where('receiving_report_id', '!=', $rrId);
                })
                ->sum('quantity_received');
            $pending = $pendingByPoItem[$poItem->id] ?? 0;
            $totalAfterDelivery = $existingReceived + $pending + $item['quantity_received'];

            if ($totalAfterDelivery > $poItem->quantity) {
                $remaining = max(0, $poItem->quantity - $existingReceived - $pending);
                $errors["items.{$index}.quantity_received"] = "Cannot receive {$item['quantity_received']} units. Only {$remaining} remain to be delivered.";
            }

            $pendingByPoItem[$poItem->id] = $pending + $item['quantity_received'];
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Recalculate and sync the PO's receiving status based on delivered quantities,
     * then re-evaluate the parent requisition (receiving completion can close it).
     *
     * Transitions the PO (only while in a receivable/received state) to:
     * - FULLY_RECEIVED: every non-omitted item fully received via *verified* reports
     *   (or every item short-closed). Draft/pending receipts do not complete the order.
     * - PARTIALLY_RECEIVED: something has been recorded (draft, pending or verified)
     *   but not every item is verified-complete
     * - RELEASED: nothing recorded (reverts after a report is deleted/rejected/cancelled;
     *   the order keeps its seal and stays receivable)
     */
    public function syncPurchaseOrderReceivingStatus(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->loadMissing('items', 'purchaseRequisition');

        $wasFullyReceived = $purchaseOrder->status === PurchaseOrderStatus::FULLY_RECEIVED;

        $receivableStates = [
            PurchaseOrderStatus::RELEASED,
            PurchaseOrderStatus::PARTIALLY_RECEIVED,
            PurchaseOrderStatus::FULLY_RECEIVED,
        ];

        // Omitted (short-closed) items are excluded: they neither block nor count toward full receipt.
        $poItems = $purchaseOrder->items->reject(fn (PurchaseOrderItem $item) => $item->is_omitted);

        if ($poItems->isEmpty()) {
            // Every item short-closed → nothing left to receive.
            if ($purchaseOrder->items->isNotEmpty()
                && \in_array($purchaseOrder->status, $receivableStates, true)) {
                $purchaseOrder->forceFill(['status' => PurchaseOrderStatus::FULLY_RECEIVED])->save();
            }
        } else {
            $allVerifiedReceived = true;
            $anyReceived = false;

            foreach ($poItems as $poItem) {
                // Recorded (pending + verified) decides "any activity"; verified decides "complete".
                if ($poItem->quantity_received > 0) {
                    $anyReceived = true;
                }

                if ($poItem->quantity_verified_received < $poItem->quantity) {
                    $allVerifiedReceived = false;
                }
            }

            // Revert floor is RELEASED: a sealed order that loses all its receipts
            // stays ordered/receivable rather than dropping back to APPROVED.
            $desired = match (true) {
                $allVerifiedReceived => PurchaseOrderStatus::FULLY_RECEIVED,
                $anyReceived => PurchaseOrderStatus::PARTIALLY_RECEIVED,
                default => PurchaseOrderStatus::RELEASED,
            };

            if (\in_array($purchaseOrder->status, $receivableStates, true)) {
                $purchaseOrder->forceFill(['status' => $desired])->save();
            }
        }

        // Notify only on the transition into fully received, not on re-syncs.
        if (! $wasFullyReceived && $purchaseOrder->status === PurchaseOrderStatus::FULLY_RECEIVED) {
            PurchaseOrderFullyReceived::dispatch($purchaseOrder);
        }

        // Receiving completion (or short-close) can close the parent requisition.
        if ($purchaseOrder->purchaseRequisition !== null) {
            $this->purchaseOrderService->syncPurchaseRequisitionFulfillmentStatus($purchaseOrder->purchaseRequisition);
        }
    }

    /**
     * Sync submitted notes: notes absent from the payload (or blanked out) are
     * deleted, existing ones updated, new ones created and attributed to the receiver.
     *
     * @param  array{received_by_id?: int, notes?: array<int, array{id?: int, content?: string|null}>}  $data
     */
    private function syncNotes(ReceivingReport $receivingReport, array $data): void
    {
        if (! isset($data['notes']) || ! \is_array($data['notes'])) {
            return;
        }

        $incomingNoteIds = collect($data['notes'])->pluck('id')->filter()->toArray();
        $receivingReport->notes()->whereNotIn('id', $incomingNoteIds)->delete();

        foreach ($data['notes'] as $noteData) {
            if (empty($noteData['content'])) {
                if (isset($noteData['id'])) {
                    $receivingReport->notes()->where('id', $noteData['id'])->delete();
                }

                continue;
            }

            if (isset($noteData['id'])) {
                $receivingReport->notes()->where('id', $noteData['id'])->update(['content' => $noteData['content']]);
            } else {
                $receivingReport->notes()->create([
                    'content' => $noteData['content'],
                    'user_id' => $data['received_by_id'] ?? $receivingReport->received_by_id,
                ]);
            }
        }
    }

    /**
     * Reduce item rows to the fields worth keeping in the allocation audit trail.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{purchase_order_item_id: mixed, quantity_received: mixed}>
     */
    private function itemsSnapshot(array $items): array
    {
        return array_values(array_map(fn (array $item) => [
            'purchase_order_item_id' => $item['purchase_order_item_id'] ?? null,
            'quantity_received' => $item['quantity_received'] ?? null,
        ], $items));
    }
}

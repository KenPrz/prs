<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequisitionStatus;
use App\Models\LineItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Settings\GeneralSettings;
use App\Support\AttachmentSync;
use App\Support\ProcurementSearch;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    /**
     * List purchase orders with optional filtering.
     *
     * @param  array{search?: string|null, status?: string|null, per_page?: int|string|null, purchase_requisition_id?: int|null}  $filters
     * @return LengthAwarePaginator<int, PurchaseOrder>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? app(GeneralSettings::class)->records_per_page), 100));

        return PurchaseOrder::query()
            ->orderByDesc('created_at')
            ->with(['supplier', 'purchaseRequisition'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search) {
                    $query->where('remarks', 'like', '%'.$search.'%')
                        ->orWhere('terms_and_conditions', 'like', '%'.$search.'%')
                        ->orWhere('payment_terms', 'like', '%'.$search.'%')
                        ->orWhere('currency', 'like', '%'.$search.'%')
                        ->orWhere('expected_delivery_date', 'like', '%'.$search.'%')
                        ->orWhereHas('supplier', function ($q) use ($search) {
                            ProcurementSearch::applyCaseInsensitiveLike($q, 'name', $search);
                        })
                        ->orWhereHas('purchaseRequisition', fn ($q) => $q->where('title', 'like', '%'.$search.'%'));

                    foreach (ProcurementSearch::documentNumberSearchPatterns($search) as $docPattern) {
                        $query->orWhere('po_number', 'like', '%'.$docPattern.'%');
                    }
                });
            })
            ->when($filters['status'] ?? null, function ($query, string $status): void {
                $query->where('status', $status);
            })
            ->when($filters['purchase_requisition_id'] ?? null, function ($query, int $prId): void {
                $query->where('purchase_requisition_id', $prId);
            })
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Create a purchase order from an approved purchase requisition.
     *
     * Validates that:
     * - The PR is in an orderable status (APPROVED or PARTIALLY_ORDERED)
     * - All referenced line items belong to the PR
     * - Allocated quantities do not exceed unallocated amounts
     *
     * @param  array{supplier_id: int, expected_delivery_date?: string|null, terms_and_conditions?: string|null, remarks?: string|null, price_type: string, items: array<int, array{line_item_id: int, quantity: int, unit_id: int, price?: float|null}>}  $data
     *
     * @throws ValidationException
     */
    /**
     * @param  list<UploadedFile>|null  $attachmentFiles
     */
    public function create(PurchaseRequisition $purchaseRequisition, array $data, ?array $attachmentFiles = null): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseRequisition, $data, $attachmentFiles) {
            $this->assertPurchaseRequisitionIsOrderable($purchaseRequisition);
            $this->lockLineItems($purchaseRequisition, $data['items']);
            $this->validateAllocations($purchaseRequisition, $data['items']);

            /** @var PurchaseOrder $purchaseOrder */
            $purchaseOrder = $purchaseRequisition->purchaseOrders()->create([
                'supplier_id' => $data['supplier_id'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'terms_and_conditions' => $data['terms_and_conditions'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'price_type' => $data['price_type'],
                'status' => PurchaseOrderStatus::DRAFT,
            ]);

            $purchaseOrder->items()->createMany($data['items']);

            $this->syncNotes($purchaseOrder, $data);

            activity('allocation')
                ->performedOn($purchaseOrder)
                ->withProperties(['items' => $this->itemsSnapshot($data['items'])])
                ->log('allocated');

            $this->syncPurchaseRequisitionFulfillmentStatus($purchaseRequisition);

            AttachmentSync::sync($purchaseOrder, $attachmentFiles, null);

            return $purchaseOrder->load(['supplier', 'items.lineItem']);
        });
    }

    /**
     * Update a draft purchase order.
     *
     * @param  array{supplier_id?: int, expected_delivery_date?: string|null, terms_and_conditions?: string|null, remarks?: string|null, price_type?: string, items?: array<int, array{id?: int, line_item_id: int, quantity: int, unit_id: int, price?: float|null}>}  $data
     *
     * @throws ValidationException
     */
    /**
     * @param  list<UploadedFile>|null  $attachmentFiles
     * @param  list<int>|null  $removedAttachmentIds
     */
    public function update(PurchaseOrder $purchaseOrder, array $data, ?array $attachmentFiles = null, ?array $removedAttachmentIds = null): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $data, $attachmentFiles, $removedAttachmentIds) {
            $this->assertPurchaseOrderIsDraft($purchaseOrder);

            $purchaseOrder->update($data);

            if (isset($data['items']) && \is_array($data['items'])) {
                $purchaseRequisition = $purchaseOrder->purchaseRequisition;

                $incomingItemIds = collect($data['items'])->pluck('id')->filter()->toArray();

                // Delete items not in incoming request
                $purchaseOrder->items()->whereNotIn('id', $incomingItemIds)->delete();

                // Validate allocations excluding this PO's current items (they'll be replaced)
                $this->lockLineItems($purchaseRequisition, $data['items']);
                $this->validateAllocations($purchaseRequisition, $data['items'], $purchaseOrder->id);

                // Update existing or create new
                foreach ($data['items'] as $itemData) {
                    if (isset($itemData['id'])) {
                        $purchaseOrder->items()->where('id', $itemData['id'])->update($itemData);
                    } else {
                        $purchaseOrder->items()->create($itemData);
                    }
                }

                activity('allocation')
                    ->performedOn($purchaseOrder)
                    ->withProperties(['items' => $this->itemsSnapshot($data['items'])])
                    ->log('reallocated');

                $this->syncPurchaseRequisitionFulfillmentStatus($purchaseRequisition);
            }

            $this->syncNotes($purchaseOrder, $data);

            AttachmentSync::sync($purchaseOrder, $attachmentFiles, $removedAttachmentIds);

            return $purchaseOrder->fresh()->load(['supplier', 'items.lineItem', 'purchaseRequisition']);
        });
    }

    /**
     * Delete a draft purchase order and recalculate PR fulfillment status,
     * returning its allocated quantities to the pool.
     *
     * @throws ValidationException
     */
    public function delete(PurchaseOrder $purchaseOrder, ?string $reason = null): void
    {
        DB::transaction(function () use ($purchaseOrder, $reason) {
            $this->assertPurchaseOrderIsDraft($purchaseOrder);

            $purchaseRequisition = $purchaseOrder->purchaseRequisition;

            activity('allocation')
                ->performedOn($purchaseOrder)
                ->withProperties([
                    'items' => $this->itemsSnapshot($purchaseOrder->items->map->only(['line_item_id', 'quantity'])->all()),
                    'reason' => $reason,
                ])
                ->log('deallocated');

            $purchaseOrder->items()->delete();
            $purchaseOrder->notes()->delete();
            $purchaseOrder->delete();

            $this->syncPurchaseRequisitionFulfillmentStatus($purchaseRequisition);
        });
    }

    /**
     * Assert that the purchase requisition is in an orderable status.
     *
     * @throws ValidationException
     */
    private function assertPurchaseRequisitionIsOrderable(PurchaseRequisition $purchaseRequisition): void
    {
        $orderableStatuses = [
            PurchaseRequisitionStatus::READY_FOR_PO,
            PurchaseRequisitionStatus::PARTIALLY_ORDERED,
        ];

        if (! \in_array($purchaseRequisition->status, $orderableStatuses, true)) {
            throw ValidationException::withMessages([
                'purchase_requisition_id' => 'The purchase requisition must be marked ready for PO before creating a purchase order.',
            ]);
        }
    }

    /**
     * Assert that the purchase order is in DRAFT status.
     *
     * @throws ValidationException
     */
    private function assertPurchaseOrderIsDraft(PurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only draft purchase orders can be modified.',
            ]);
        }
    }

    /**
     * Lock the PR line rows being allocated so concurrent purchase orders can't
     * double-book the same quantity (read-sum-then-write race). No-op on SQLite.
     *
     * @param  array<int, array{line_item_id: int}>  $items
     */
    private function lockLineItems(PurchaseRequisition $purchaseRequisition, array $items): void
    {
        $lineItemIds = collect($items)->pluck('line_item_id')->unique()->all();

        $purchaseRequisition->lineItems()->whereIn('id', $lineItemIds)->lockForUpdate()->get();
    }

    /**
     * Validate that PO item allocations do not exceed what's available on the PR.
     *
     * @param  array<int, array{line_item_id: int, quantity: int, unit_id: int}>  $items
     * @param  int|null  $excludePurchaseOrderId  Exclude this PO's items when recalculating (for updates)
     *
     * @throws ValidationException
     */
    private function validateAllocations(PurchaseRequisition $purchaseRequisition, array $items, ?int $excludePurchaseOrderId = null): void
    {
        $purchaseRequisition->loadMissing('lineItems');
        $errors = [];

        // Quantities already claimed by earlier rows of this same payload, per line item.
        // Guards over-allocation via duplicate rows for callers that bypass the
        // request-level `distinct` rule.
        $pendingByLineItem = [];

        foreach ($items as $index => $item) {
            /** @var LineItem|null $lineItem */
            $lineItem = $purchaseRequisition->lineItems->firstWhere('id', $item['line_item_id']);

            if (! $lineItem) {
                $errors["items.{$index}.line_item_id"] = 'This line item does not belong to the purchase requisition.';

                continue;
            }

            if ($lineItem->is_omitted) {
                $errors["items.{$index}.line_item_id"] = "'{$lineItem->name}' has been omitted and cannot be ordered.";

                continue;
            }

            if ((int) ($item['unit_id'] ?? 0) !== (int) $lineItem->unit_id) {
                $errors["items.{$index}.unit_id"] = "The unit must match the requisition's unit for '{$lineItem->name}'.";

                continue;
            }

            // Calculate already allocated quantity (omitted items and cancelled POs don't count), excluding the current PO if updating
            $existingAllocated = $lineItem->purchaseOrderItems()
                ->allocating()
                ->when($excludePurchaseOrderId, function ($query, int $poId): void {
                    $query->where('purchase_order_id', '!=', $poId);
                })
                ->sum('quantity');

            $pending = $pendingByLineItem[$lineItem->id] ?? 0;
            $totalAfterAllocation = $existingAllocated + $pending + $item['quantity'];

            if ($totalAfterAllocation > $lineItem->quantity) {
                $available = max(0, $lineItem->quantity - $existingAllocated - $pending);
                $errors["items.{$index}.quantity"] = "Cannot allocate {$item['quantity']} units. Only {$available} of '{$lineItem->name}' remain unallocated.";
            }

            $pendingByLineItem[$lineItem->id] = $pending + $item['quantity'];
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Recalculate and sync the PR's fulfillment status based on PO allocations.
     *
     * Transitions PR to:
     * - PARTIALLY_ORDERED: some but not all line-item qty allocated
     * - FULLY_ALLOCATED: all qty allocated, but not all POs approved
     * - FULLY_ORDERED: all qty allocated and every active PO approved or beyond
     * - CLOSED: all qty allocated and every active PO fully received (or every line short-closed)
     * - READY_FOR_PO: no allocations at all (reverted; the PR keeps its snapshot and stays orderable)
     *
     * Cancelled POs and omitted line items are excluded from the accounting.
     */
    public function syncPurchaseRequisitionFulfillmentStatus(PurchaseRequisition $purchaseRequisition): void
    {
        $purchaseRequisition->loadMissing('lineItems');

        $orderingStates = [
            PurchaseRequisitionStatus::READY_FOR_PO,
            PurchaseRequisitionStatus::PARTIALLY_ORDERED,
            PurchaseRequisitionStatus::FULLY_ALLOCATED,
            PurchaseRequisitionStatus::FULLY_ORDERED,
            PurchaseRequisitionStatus::CLOSED,
        ];

        // Fulfillment only moves a PR between ordering states — never out of
        // DRAFT/REVIEWING/CANCELLED/REJECTED.
        if (! \in_array($purchaseRequisition->status, $orderingStates, true)) {
            return;
        }

        // Omission is a partial short-close: an omitted line stops counting its
        // UNALLOCATED remainder (the waived quantity), but anything already on
        // active purchase orders stays in the accounting. A line omitted with
        // zero allocations therefore drops out entirely.
        $lineItems = $purchaseRequisition->lineItems->reject(
            fn (LineItem $item) => $item->is_omitted && $item->quantity_allocated === 0,
        );

        // Every line short-closed with nothing on order → nothing left to procure.
        if ($lineItems->isEmpty()) {
            if ($purchaseRequisition->lineItems->isNotEmpty()
                && \in_array($purchaseRequisition->status, $orderingStates, true)) {
                $purchaseRequisition->forceFill(['status' => PurchaseRequisitionStatus::CLOSED])->save();
            }

            return;
        }

        $totalAllocated = 0;
        $totalRequested = 0;

        foreach ($lineItems as $lineItem) {
            $allocated = $lineItem->quantity_allocated;

            $totalAllocated += $allocated;
            // Omitted line with allocations: the remainder is waived, so only
            // what was actually ordered still counts as requested.
            $totalRequested += $lineItem->is_omitted ? $allocated : $lineItem->quantity;
        }

        if ($totalAllocated === 0) {
            if (\in_array($purchaseRequisition->status, [
                PurchaseRequisitionStatus::PARTIALLY_ORDERED,
                PurchaseRequisitionStatus::FULLY_ALLOCATED,
                PurchaseRequisitionStatus::FULLY_ORDERED,
                PurchaseRequisitionStatus::CLOSED,
            ], true)) {
                $purchaseRequisition->forceFill(['status' => PurchaseRequisitionStatus::READY_FOR_PO])->save();
            }
        } elseif ($totalAllocated >= $totalRequested) {
            $status = match (true) {
                $this->allActivePurchaseOrdersReceived($purchaseRequisition) => PurchaseRequisitionStatus::CLOSED,
                $this->allPurchaseOrdersApproved($purchaseRequisition) => PurchaseRequisitionStatus::FULLY_ORDERED,
                default => PurchaseRequisitionStatus::FULLY_ALLOCATED,
            };

            $purchaseRequisition->forceFill(['status' => $status])->save();
        } else {
            $purchaseRequisition->forceFill(['status' => PurchaseRequisitionStatus::PARTIALLY_ORDERED])->save();
        }
    }

    /**
     * True when every active (non-cancelled) PO has passed approval (or progressed to receiving).
     */
    private function allPurchaseOrdersApproved(PurchaseRequisition $purchaseRequisition): bool
    {
        $purchaseOrders = $purchaseRequisition->purchaseOrders()->active()->get();

        if ($purchaseOrders->isEmpty()) {
            return false;
        }

        $approvedStatuses = [
            PurchaseOrderStatus::APPROVED,
            PurchaseOrderStatus::RELEASED,
            PurchaseOrderStatus::PARTIALLY_RECEIVED,
            PurchaseOrderStatus::FULLY_RECEIVED,
        ];

        return $purchaseOrders->every(
            fn (PurchaseOrder $purchaseOrder) => \in_array($purchaseOrder->status, $approvedStatuses, true),
        );
    }

    /**
     * True when at least one active PO exists and every one is fully received.
     */
    private function allActivePurchaseOrdersReceived(PurchaseRequisition $purchaseRequisition): bool
    {
        $purchaseOrders = $purchaseRequisition->purchaseOrders()->active()->get();

        if ($purchaseOrders->isEmpty()) {
            return false;
        }

        return $purchaseOrders->every(
            fn (PurchaseOrder $purchaseOrder) => $purchaseOrder->status === PurchaseOrderStatus::FULLY_RECEIVED,
        );
    }

    /**
     * Sync submitted notes: notes absent from the payload (or blanked out) are
     * deleted, existing ones updated, new ones created and attributed to the
     * acting user (`notes_user_id` from the controller, else the authenticated user).
     *
     * @param  array{notes_user_id?: int, notes?: array<int, array{id?: int, content?: string|null}>}  $data
     */
    private function syncNotes(PurchaseOrder $purchaseOrder, array $data): void
    {
        if (! isset($data['notes']) || ! \is_array($data['notes'])) {
            return;
        }

        $userId = $data['notes_user_id'] ?? auth()->id();

        $incomingNoteIds = collect($data['notes'])->pluck('id')->filter()->toArray();
        $purchaseOrder->notes()->whereNotIn('id', $incomingNoteIds)->delete();

        foreach ($data['notes'] as $noteData) {
            if (empty($noteData['content'])) {
                if (isset($noteData['id'])) {
                    $purchaseOrder->notes()->where('id', $noteData['id'])->delete();
                }

                continue;
            }

            if (isset($noteData['id'])) {
                $purchaseOrder->notes()->where('id', $noteData['id'])->update(['content' => $noteData['content']]);
            } elseif ($userId !== null) {
                $purchaseOrder->notes()->create([
                    'content' => $noteData['content'],
                    'user_id' => $userId,
                ]);
            }
        }
    }

    /**
     * Reduce item rows to the fields worth keeping in the allocation audit trail.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{line_item_id: mixed, quantity: mixed}>
     */
    private function itemsSnapshot(array $items): array
    {
        return array_values(array_map(fn (array $item) => [
            'line_item_id' => $item['line_item_id'] ?? null,
            'quantity' => $item['quantity'] ?? null,
        ], $items));
    }
}

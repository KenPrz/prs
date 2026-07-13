<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\Document;
use App\Models\PurchaseRequisition;
use App\Settings\GeneralSettings;
use App\Support\AttachmentSync;
use App\Support\ProcurementSearch;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PurchaseRequisitionService
{
    /**
     * List the purchase requisitions.
     *
     * @param  array{search?: string|null, status?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, PurchaseRequisition>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? app(GeneralSettings::class)->records_per_page), 100));

        return PurchaseRequisition::query()
            ->orderByDesc('created_at')
            ->with('requestor')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhere('delivery_date', 'like', '%'.$search.'%')
                        ->orWhereRaw('LOWER(CAST(purchase_requisitions.status AS TEXT)) LIKE ?', ['%'.mb_strtolower($search).'%']);

                    foreach (ProcurementSearch::documentNumberSearchPatterns($search) as $docPattern) {
                        $query->orWhere('pr_number', 'like', '%'.$docPattern.'%');
                    }
                });
            })
            ->when($filters['status'] ?? null, function ($query, string $status): void {
                $query->where('status', $status);
            })
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Create a new purchase requisition.
     *
     * @param  array{requestor_id: int, title: string, description?: string|null, delivery_date?: string|null, status?: string|null, price_type: string, department_ids?: int[], line_items?: array<int, array{name: string, quantity: int, unit_id: int, price?: float|null}>, notes?: array<int, array{content: string}>}  $data
     */
    /**
     * @param  list<UploadedFile>|null  $attachmentFiles
     */
    public function create(array $data, ?array $attachmentFiles = null): PurchaseRequisition
    {
        return DB::transaction(function () use ($data, $attachmentFiles) {
            $data['document_id'] = $data['document_id'] ?? Document::defaultPurchaseOrderTemplate()?->id;
            $data['requisition_document_id'] = $data['requisition_document_id'] ?? Document::defaultPurchaseRequisitionTemplate()?->id;
            $data['to_be_ordered_by_id'] = $this->resolveToBeOrderedById($data);

            $requisition = PurchaseRequisition::create($data);

            if (isset($data['department_ids']) && \is_array($data['department_ids'])) {
                $requisition->departments()->sync($data['department_ids']);
            }

            if (isset($data['line_items']) && \is_array($data['line_items'])) {
                $requisition->lineItems()->createMany($data['line_items']);
            }

            if (isset($data['notes']) && \is_array($data['notes'])) {
                foreach ($data['notes'] as $note) {
                    if (empty($note['content'])) {
                        continue;
                    }

                    $requisition->notes()->create([
                        'content' => $note['content'],
                        'user_id' => $data['requestor_id'],
                    ]);
                }
            }

            AttachmentSync::sync($requisition, $attachmentFiles, null);

            return $requisition->load('requestor');
        });
    }

    /**
     * Update the given purchase requisition.
     *
     * @param  array{requestor_id?: int, title: string, description?: string|null, delivery_date?: string|null, status?: string|null, price_type?: string, department_ids?: int[], line_items?: array<int, array{id?: int, name: string, quantity: int, unit_id: int, price?: float|null}>, notes?: array<int, array{id?: int, content: string}>}  $data
     */
    /**
     * @param  list<UploadedFile>|null  $attachmentFiles
     * @param  list<int>|null  $removedAttachmentIds
     */
    public function update(PurchaseRequisition $purchaseRequisition, array $data, ?array $attachmentFiles = null, ?array $removedAttachmentIds = null): PurchaseRequisition
    {
        return DB::transaction(function () use ($purchaseRequisition, $data, $attachmentFiles, $removedAttachmentIds) {
            $purchaseRequisition->update($data);

            if (isset($data['department_ids']) && \is_array($data['department_ids'])) {
                $purchaseRequisition->departments()->sync($data['department_ids']);
            }

            if (isset($data['line_items']) && \is_array($data['line_items'])) {
                $incomingItemIds = collect($data['line_items'])->pluck('id')->filter()->toArray();

                // 1. Delete items not in incoming request
                $purchaseRequisition->lineItems()->whereNotIn('id', $incomingItemIds)->delete();

                // 2. Update existing or create new
                foreach ($data['line_items'] as $itemData) {
                    if (isset($itemData['id'])) {
                        $purchaseRequisition->lineItems()->where('id', $itemData['id'])->update($itemData);
                    } else {
                        $purchaseRequisition->lineItems()->create($itemData);
                    }
                }
            }

            if (isset($data['notes']) && \is_array($data['notes'])) {
                $incomingNoteIds = collect($data['notes'])->pluck('id')->filter()->toArray();

                // 1. Delete notes not in incoming request
                $purchaseRequisition->notes()->whereNotIn('id', $incomingNoteIds)->delete();

                // 2. Process incoming
                foreach ($data['notes'] as $noteData) {
                    if (empty($noteData['content'])) {
                        if (isset($noteData['id'])) {
                            $purchaseRequisition->notes()->where('id', $noteData['id'])->delete();
                        }

                        continue;
                    }

                    if (isset($noteData['id'])) {
                        $purchaseRequisition->notes()->where('id', $noteData['id'])->update(['content' => $noteData['content']]);
                    } else {
                        $purchaseRequisition->notes()->create([
                            'content' => $noteData['content'],
                            'user_id' => $data['requestor_id'] ?? $purchaseRequisition->requestor_id,
                        ]);
                    }
                }
            }

            AttachmentSync::sync($purchaseRequisition, $attachmentFiles, $removedAttachmentIds);

            return $purchaseRequisition->fresh()->load(['requestor', 'departments', 'lineItems', 'notes']);
        });
    }

    /**
     * Resolve the "to be ordered by" user for a new requisition.
     *
     * Uses the per-requisition value when given; otherwise falls back to the
     * company profile's configured default, and finally to the requestor when
     * no default is configured.
     *
     * @param  array{requestor_id?: int, to_be_ordered_by_id?: int|null}  $data
     */
    private function resolveToBeOrderedById(array $data): ?int
    {
        if (! empty($data['to_be_ordered_by_id'])) {
            return (int) $data['to_be_ordered_by_id'];
        }

        $configured = CompanyProfile::query()->value('default_received_by_user_id');

        return $configured ?? ($data['requestor_id'] ?? null);
    }

    /**
     * Delete the given purchase requisition, recording the reason.
     */
    public function delete(PurchaseRequisition $purchaseRequisition, ?string $reason = null): void
    {
        DB::transaction(function () use ($purchaseRequisition, $reason) {
            activity()
                ->performedOn($purchaseRequisition)
                ->withProperties(['reason' => $reason])
                ->log('deleted');

            // Cascade delete relations that have RESTRICT constraints or are polymorphic
            $purchaseRequisition->lineItems()->delete();
            $purchaseRequisition->notes()->delete();
            $purchaseRequisition->departments()->detach();

            $purchaseRequisition->delete();
        });
    }
}

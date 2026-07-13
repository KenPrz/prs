<?php

namespace App\Services;

use App\Models\PaymentRequestForm;
use App\Support\AttachmentSync;
use App\Support\ProcurementSearch;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PaymentRequestFormService
{
    /**
     * List the payment request forms.
     *
     * @param  array{search?: string|null, status?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, PaymentRequestForm>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 10), 100));

        return PaymentRequestForm::query()
            ->orderByDesc('created_at')
            ->with(['requestor', 'supplier'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search) {
                    $query->where('description', 'like', '%'.$search.'%')
                        ->orWhere('prf_number', 'like', '%'.$search.'%')
                        ->orWhere('invoice_number', 'like', '%'.$search.'%')
                        ->orWhereHas('supplier', function ($q) use ($search) {
                            ProcurementSearch::applyCaseInsensitiveLike($q, 'name', $search);
                        });
                });
            })
            ->when($filters['status'] ?? null, function ($query, string $status): void {
                $query->where('status', $status);
            })
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Create a new payment request form.
     *
     * @param  array{requestor_id: int, supplier_id: int, company_profile_id?: int|null, description?: string|null, amount: float|string, invoice_number?: string|null, due_date?: string|null, stamp_date?: string|null, department_ids?: int[], notes?: array<int, array{content: string}>}  $data
     * @param  list<UploadedFile>|null  $attachmentFiles
     */
    public function create(array $data, ?array $attachmentFiles = null): PaymentRequestForm
    {
        return DB::transaction(function () use ($data, $attachmentFiles) {
            $prf = PaymentRequestForm::create($data);

            if (isset($data['department_ids']) && \is_array($data['department_ids'])) {
                $prf->departments()->sync($data['department_ids']);
            }

            if (isset($data['notes']) && \is_array($data['notes'])) {
                foreach ($data['notes'] as $note) {
                    if (empty($note['content'])) {
                        continue;
                    }

                    $prf->notes()->create([
                        'content' => $note['content'],
                        'user_id' => $data['requestor_id'],
                    ]);
                }
            }

            AttachmentSync::sync($prf, $attachmentFiles, null);

            return $prf->load(['requestor', 'supplier']);
        });
    }

    /**
     * Update the given payment request form.
     *
     * @param  array{requestor_id?: int, supplier_id?: int, company_profile_id?: int|null, description?: string|null, amount?: float|string, invoice_number?: string|null, due_date?: string|null, stamp_date?: string|null, department_ids?: int[], notes?: array<int, array{id?: int, content: string}>}  $data
     * @param  list<UploadedFile>|null  $attachmentFiles
     * @param  list<int>|null  $removedAttachmentIds
     */
    public function update(PaymentRequestForm $prf, array $data, ?array $attachmentFiles = null, ?array $removedAttachmentIds = null): PaymentRequestForm
    {
        return DB::transaction(function () use ($prf, $data, $attachmentFiles, $removedAttachmentIds) {
            $prf->update($data);

            if (isset($data['department_ids']) && \is_array($data['department_ids'])) {
                $prf->departments()->sync($data['department_ids']);
            }

            if (isset($data['notes']) && \is_array($data['notes'])) {
                $incomingNoteIds = collect($data['notes'])->pluck('id')->filter()->toArray();

                // 1. Delete notes not in incoming request
                $prf->notes()->whereNotIn('id', $incomingNoteIds)->delete();

                // 2. Process incoming
                foreach ($data['notes'] as $noteData) {
                    if (empty($noteData['content'])) {
                        if (isset($noteData['id'])) {
                            $prf->notes()->where('id', $noteData['id'])->delete();
                        }

                        continue;
                    }

                    if (isset($noteData['id'])) {
                        $prf->notes()->where('id', $noteData['id'])->update(['content' => $noteData['content']]);
                    } else {
                        $prf->notes()->create([
                            'content' => $noteData['content'],
                            'user_id' => $data['requestor_id'] ?? $prf->requestor_id,
                        ]);
                    }
                }
            }

            AttachmentSync::sync($prf, $attachmentFiles, $removedAttachmentIds);

            return $prf->fresh()->load(['requestor', 'supplier', 'departments', 'notes']);
        });
    }

    /**
     * Delete the given payment request form, recording the reason.
     */
    public function delete(PaymentRequestForm $prf, ?string $reason = null): void
    {
        DB::transaction(function () use ($prf, $reason) {
            activity()
                ->performedOn($prf)
                ->withProperties(['reason' => $reason])
                ->log('deleted');

            $prf->notes()->delete();
            $prf->departments()->detach();

            $prf->delete();
        });
    }
}

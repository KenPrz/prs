<?php

namespace App\Models;

use App\Concerns\HasNotes;
use App\Concerns\InteractsWithWorkflow;
use App\Concerns\RegistersAttachmentMediaCollection;
use App\Contracts\WorkflowSubject;
use App\Enums\ReceivingReportStatus;
use App\Enums\WorkflowInstanceStatus;
use App\Events\Procurement\ReceivingReportVerified;
use App\Services\ReceivingReportService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable([
    'purchase_order_id',
    'received_by_id',
    'received_date',
    'workflow_id',
    'workflow_instance_id',
    'status',
    'remarks',
])]
class ReceivingReport extends Model implements HasMedia, WorkflowSubject
{
    use HasFactory, HasNotes, InteractsWithMedia, InteractsWithWorkflow, LogsActivity, RegistersAttachmentMediaCollection {
        RegistersAttachmentMediaCollection::registerMediaCollections insteadof InteractsWithMedia;
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (ReceivingReport $model) {
            $year = now()->year;

            $nextNumber = DB::transaction(function () use ($year) {
                $latestRrNumber = self::query()
                    ->whereYear('created_at', $year)
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->value('rr_number');

                if (! $latestRrNumber) {
                    return 1;
                }

                $suffix = (int) Str::afterLast($latestRrNumber, '-');

                return $suffix + 1;
            });

            $model->rr_number = \sprintf('%d-%03d', $year, $nextNumber);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'status' => ReceivingReportStatus::class,
        ];
    }

    /**
     * Get the options for the activity log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * The purchase order this receiving report belongs to.
     *
     * @return BelongsTo<PurchaseOrder, ReceivingReport>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * The user who recorded this receiving report.
     *
     * @return BelongsTo<User, ReceivingReport>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_id');
    }

    /**
     * The line items of this receiving report.
     *
     * @return HasMany<ReceivingReportItem, ReceivingReport>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReceivingReportItem::class)->orderBy('id');
    }

    // Workflow relations provided by InteractsWithWorkflow.

    public function workflowDocumentType(): string
    {
        return 'RR';
    }

    public function defaultWorkflowKey(): string
    {
        return 'rr.default';
    }

    /**
     * @return Collection<int, User>
     */
    public function resolveReceivers(): Collection
    {
        return collect($this->receivedBy ? [$this->receivedBy] : []);
    }

    public function applyWorkflowStarted(): void
    {
        $this->forceFill(['status' => ReceivingReportStatus::PENDING])->save();
    }

    public function applyWorkflowStatus(WorkflowInstanceStatus $status): void
    {
        $mapped = match ($status) {
            WorkflowInstanceStatus::Approved => ReceivingReportStatus::VERIFIED,
            WorkflowInstanceStatus::Rejected => ReceivingReportStatus::REJECTED,
            WorkflowInstanceStatus::Cancelled => ReceivingReportStatus::CANCELLED,
            WorkflowInstanceStatus::Pending => null,
        };

        if ($mapped !== null) {
            $this->forceFill(['status' => $mapped])->save();

            if ($mapped === ReceivingReportStatus::VERIFIED) {
                ReceivingReportVerified::dispatch($this);
            }

            // Rejected/cancelled reports no longer count toward received quantity.
            if (\in_array($mapped, [ReceivingReportStatus::REJECTED, ReceivingReportStatus::CANCELLED], true)) {
                activity('allocation')
                    ->performedOn($this)
                    ->withProperties(['reason' => 'Receiving report '.mb_strtolower($mapped->value).' via workflow'])
                    ->log('deallocated');
            }

            $this->loadMissing('purchaseOrder');

            if ($this->purchaseOrder !== null) {
                app(ReceivingReportService::class)->syncPurchaseOrderReceivingStatus($this->purchaseOrder);
            }
        }
    }
}

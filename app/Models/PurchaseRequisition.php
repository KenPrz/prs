<?php

namespace App\Models;

use App\Concerns\HasNotes;
use App\Concerns\HasPriceTypeTotals;
use App\Concerns\InteractsWithWorkflow;
use App\Concerns\RegistersAttachmentMediaCollection;
use App\Contracts\WorkflowSubject;
use App\Enums\PriceType;
use App\Enums\PurchaseRequisitionStatus;
use App\Enums\PurposeType;
use App\Enums\WorkflowInstanceStatus;
use App\Enums\WorkflowStepType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable([
    'requestor_id',
    'to_be_ordered_by_id',
    'workflow_id',
    'workflow_instance_id',
    'document_id',
    'requisition_document_id',
    'title',
    'description',
    'delivery_date',
    'status',
    'price_type',
    'purpose_type',
    'expected_useful_life',
])]
class PurchaseRequisition extends Model implements HasMedia, WorkflowSubject
{
    /** @use HasFactory<PurchaseRequisitionFactory> */
    use HasFactory, HasNotes, HasPriceTypeTotals, InteractsWithWorkflow;

    use InteractsWithMedia, LogsActivity, RegistersAttachmentMediaCollection {
        RegistersAttachmentMediaCollection::registerMediaCollections insteadof InteractsWithMedia;
    }

    /**
     * The attributes that should be appended to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [];

    /**
     * Boot the model.
     */
    protected static function booted()
    {
        static::creating(function (PurchaseRequisition $model) {
            $year = now()->year;

            $nextNumber = DB::transaction(function () use ($year) {
                $latestPrNumber = self::query()
                    ->whereYear('created_at', $year)
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->value('pr_number');

                if (! $latestPrNumber) {
                    return 1;
                }

                $suffix = (int) Str::afterLast($latestPrNumber, '-');

                return $suffix + 1;
            });

            $model->pr_number = \sprintf('%d-%03d', $year, $nextNumber);
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
            'delivery_date' => 'date',
            'status' => PurchaseRequisitionStatus::class,
            'price_type' => PriceType::class,
            'purpose_type' => PurposeType::class,
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
     * The user who requested the purchase requisition.
     *
     * @return BelongsTo<User, PurchaseRequisition>
     */
    public function requestor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requestor_id');
    }

    /**
     * The user designated to place/receive the order for this requisition.
     *
     * @return BelongsTo<User, PurchaseRequisition>
     */
    public function toBeOrderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_be_ordered_by_id');
    }

    /**
     * The line items of the purchase requisition.
     *
     * @return HasMany<LineItem, PurchaseRequisition>
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(LineItem::class)->orderBy('id');
    }

    /**
     * The priced items used to compute this requisition's totals.
     *
     * @return Collection<int, LineItem>
     */
    protected function pricedItems(): Collection
    {
        return $this->lineItems->reject(fn (LineItem $item) => $item->is_omitted)->values();
    }

    /**
     * The requesting departments of the purchase requisition.
     *
     * @return BelongsToMany<Department, PurchaseRequisition>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'requesting_departments')
            ->using(RequestingDepartment::class)
            ->withTimestamps();
    }

    /**
     * The purchase orders of the purchase requisition.
     *
     * @return HasMany<PurchaseOrder, PurchaseRequisition>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * The receiving reports from all purchase orders of this requisition.
     *
     * @return HasManyThrough<ReceivingReport, PurchaseOrder, PurchaseRequisition>
     */
    public function receivingReports(): HasManyThrough
    {
        return $this->hasManyThrough(ReceivingReport::class, PurchaseOrder::class);
    }

    // Workflow relations (workflowDefinition/workflowInstance) and pointer
    // persistence are provided by the InteractsWithWorkflow trait.

    public function workflowDocumentType(): string
    {
        return 'PR';
    }

    public function defaultWorkflowKey(): string
    {
        return 'pr.default';
    }

    /**
     * Block submission when a Department-Head step is configured but a tagged
     * department has no head assigned to sign.
     *
     * @return array<int, string>
     */
    public function workflowStartErrors(WorkflowDefinition $definition): array
    {
        $hasDeptHeadStep = $definition->steps()
            ->where('is_active', true)
            ->where('step_type', WorkflowStepType::DepartmentHead->value)
            ->exists();

        if (! $hasDeptHeadStep) {
            return [];
        }

        $missing = $this->departments()->whereNull('department_head_id')->pluck('name');

        if ($missing->isEmpty()) {
            return [];
        }

        return ['These departments have no department head assigned and cannot sign the requisition: '
            .$missing->implode(', ').'.'];
    }

    /**
     * @return Collection<int, User>
     */
    public function resolveDepartmentHeads(): Collection
    {
        return $this->departments()
            ->with('departmentHead')
            ->get()
            ->map(fn (Department $department) => $department->departmentHead)
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    public function resolveReceivers(): Collection
    {
        $receiver = $this->toBeOrderedBy ?? $this->requestor;

        return collect($receiver ? [$receiver] : []);
    }

    public function applyWorkflowStarted(): void
    {
        $this->forceFill(['status' => PurchaseRequisitionStatus::REVIEWING])->save();
    }

    public function applyWorkflowStatus(WorkflowInstanceStatus $status): void
    {
        $mapped = match ($status) {
            WorkflowInstanceStatus::Approved => PurchaseRequisitionStatus::APPROVED,
            WorkflowInstanceStatus::Rejected => PurchaseRequisitionStatus::REJECTED,
            WorkflowInstanceStatus::Cancelled => PurchaseRequisitionStatus::CANCELLED,
            WorkflowInstanceStatus::Pending => null,
        };

        if ($mapped !== null) {
            $this->forceFill(['status' => $mapped])->save();
        }
    }

    /**
     * The printable document template associated with this requisition.
     *
     * @return BelongsTo<Document, PurchaseRequisition>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * The printable purchase requisition (PR) PDF template for this requisition.
     *
     * @return BelongsTo<Document, PurchaseRequisition>
     */
    public function requisitionDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'requisition_document_id');
    }
}

<?php

namespace App\Models;

use App\Concerns\HasNotes;
use App\Concerns\HasPriceTypeTotals;
use App\Concerns\InteractsWithWorkflow;
use App\Concerns\RegistersAttachmentMediaCollection;
use App\Contracts\WorkflowSubject;
use App\Enums\PriceType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\WorkflowInstanceStatus;
use App\Services\PurchaseOrderService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    'purchase_requisition_id',
    'supplier_id',
    'po_number',
    'expected_delivery_date',
    'status',
    'workflow_id',
    'workflow_instance_id',
    'document_id',
    'terms_and_conditions',
    'remarks',
    'bill_to_id',
    'ship_to_id',
    'payment_terms',
    'currency',
    'price_type',
])]
class PurchaseOrder extends Model implements HasMedia, WorkflowSubject
{
    use HasFactory, HasNotes, HasPriceTypeTotals, InteractsWithMedia, InteractsWithWorkflow, LogsActivity, RegistersAttachmentMediaCollection {
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
    protected static function booted(): void
    {
        static::creating(function (PurchaseOrder $model) {
            $year = now()->year;

            $nextNumber = DB::transaction(function () use ($year) {
                $latestPoNumber = self::query()
                    ->whereYear('created_at', $year)
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->value('po_number');

                if (! $latestPoNumber) {
                    return 1;
                }

                $suffix = (int) Str::afterLast($latestPoNumber, '-');

                return $suffix + 1;
            });

            $model->po_number = \sprintf('%d-%03d', $year, $nextNumber);
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
            'expected_delivery_date' => 'date',
            'status' => PurchaseOrderStatus::class,
            'price_type' => PriceType::class,
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
     * Scope to purchase orders that still count for accounting (not cancelled).
     *
     * @param  Builder<PurchaseOrder>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', '!=', PurchaseOrderStatus::CANCELLED);
    }

    /**
     * The purchase requisition this order was created from.
     *
     * @return BelongsTo<PurchaseRequisition, PurchaseOrder>
     */
    public function purchaseRequisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class);
    }

    /**
     * The supplier fulfilling this purchase order.
     *
     * @return BelongsTo<Supplier, PurchaseOrder>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * The line items of the purchase order.
     *
     * @return HasMany<PurchaseOrderItem, PurchaseOrder>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('id');
    }

    /**
     * The priced items used to compute this order's totals.
     *
     * @return Collection<int, PurchaseOrderItem>
     */
    protected function pricedItems(): Collection
    {
        return $this->items->reject(fn (PurchaseOrderItem $item) => $item->is_omitted)->values();
    }

    /**
     * The receiving reports for this purchase order.
     *
     * @return HasMany<ReceivingReport, PurchaseOrder>
     */
    public function receivingReports(): HasMany
    {
        return $this->hasMany(ReceivingReport::class);
    }

    /**
     * All receiving report items through the receiving reports.
     *
     * @return HasManyThrough<ReceivingReportItem, ReceivingReport, PurchaseOrder>
     */
    public function receivingReportItems(): HasManyThrough
    {
        return $this->hasManyThrough(ReceivingReportItem::class, ReceivingReport::class);
    }

    // Workflow relations provided by InteractsWithWorkflow.

    public function workflowDocumentType(): string
    {
        return 'PO';
    }

    public function defaultWorkflowKey(): string
    {
        return 'po.default';
    }

    public function applyWorkflowStarted(): void
    {
        $this->forceFill(['status' => PurchaseOrderStatus::PENDING_APPROVAL])->save();
    }

    public function applyWorkflowStatus(WorkflowInstanceStatus $status): void
    {
        $mapped = match ($status) {
            WorkflowInstanceStatus::Approved => PurchaseOrderStatus::APPROVED,
            WorkflowInstanceStatus::Rejected,
            WorkflowInstanceStatus::Cancelled => PurchaseOrderStatus::CANCELLED,
            WorkflowInstanceStatus::Pending => null,
        };

        if ($mapped !== null) {
            $this->forceFill(['status' => $mapped])->save();

            if ($mapped === PurchaseOrderStatus::CANCELLED) {
                activity('allocation')
                    ->performedOn($this)
                    ->withProperties(['reason' => 'Purchase order cancelled via workflow'])
                    ->log('deallocated');
            }

            if (\in_array($mapped, [PurchaseOrderStatus::APPROVED, PurchaseOrderStatus::CANCELLED], true)) {
                $this->loadMissing('purchaseRequisition');

                if ($this->purchaseRequisition !== null) {
                    app(PurchaseOrderService::class)->syncPurchaseRequisitionFulfillmentStatus($this->purchaseRequisition);
                }
            }
        }
    }

    /**
     * The printable document template associated with this purchase order.
     *
     * @return BelongsTo<Document, PurchaseOrder>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * The billing address for this purchase order.
     *
     * @return BelongsTo<Address, PurchaseOrder>
     */
    public function billToAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'bill_to_id');
    }

    /**
     * The shipping address for this purchase order.
     *
     * @return BelongsTo<Address, PurchaseOrder>
     */
    public function shipToAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'ship_to_id');
    }
}

<?php

namespace App\Models;

use App\Enums\ReceivingReportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'receiving_report_id',
    'purchase_order_item_id',
    'quantity_received',
    'quantity_rejected',
    'remarks',
])]
class ReceivingReportItem extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_received' => 'integer',
            'quantity_rejected' => 'integer',
        ];
    }

    /**
     * Scope to deliveries that count as *recorded* received quantity (anything not
     * rejected or cancelled — draft, pending and verified alike; draft selections
     * already reserve the quantity). Guards over-receipt and `quantity_remaining`.
     *
     * @param  Builder<ReceivingReportItem>  $query
     */
    public function scopeCounted(Builder $query): void
    {
        $query->whereHas('receivingReport', fn ($q) => $q->whereNotIn('status', [
            ReceivingReportStatus::REJECTED,
            ReceivingReportStatus::CANCELLED,
        ]));
    }

    /**
     * Scope to deliveries from *verified* (approved) reports only. These are what advance
     * a purchase order to fully received — pending receipts do not.
     *
     * @param  Builder<ReceivingReportItem>  $query
     */
    public function scopeVerified(Builder $query): void
    {
        $query->whereHas('receivingReport', fn ($q) => $q->whereIn('status', [
            ReceivingReportStatus::VERIFIED,
            ReceivingReportStatus::SUBMITTED_TO_ACCOUNTING,
        ]));
    }

    /**
     * The receiving report this item belongs to.
     *
     * @return BelongsTo<ReceivingReport, ReceivingReportItem>
     */
    public function receivingReport(): BelongsTo
    {
        return $this->belongsTo(ReceivingReport::class);
    }

    /**
     * The purchase order item this delivery corresponds to.
     *
     * @return BelongsTo<PurchaseOrderItem, ReceivingReportItem>
     */
    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }
}

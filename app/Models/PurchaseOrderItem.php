<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'purchase_order_id',
    'line_item_id',
    'quantity',
    'unit_id',
    'price',
    'omitted_at',
    'omit_reason',
])]
class PurchaseOrderItem extends Model
{
    /**
     * The attributes that should be appended to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'quantity_remaining',
        'quantity_received',
        'is_omitted',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price' => 'decimal:2',
            'omitted_at' => 'datetime',
        ];
    }

    /**
     * Whether this item has been omitted (short-closed) from receiving.
     */
    public function getIsOmittedAttribute(): bool
    {
        return $this->omitted_at !== null;
    }

    /**
     * The line item that the purchase order item belongs to.
     *
     * @return BelongsTo<LineItem, PurchaseOrderItem>
     */
    public function lineItem(): BelongsTo
    {
        return $this->belongsTo(LineItem::class);
    }

    /**
     * The purchase order that the item belongs to.
     *
     * @return BelongsTo<PurchaseOrder, PurchaseOrderItem>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * The receiving report items recording deliveries for this PO item.
     *
     * @return HasMany<ReceivingReportItem, PurchaseOrderItem>
     */
    public function receivingReportItems(): HasMany
    {
        return $this->hasMany(ReceivingReportItem::class);
    }

    /**
     * Scope to PO items that still count toward a PR line's allocation. Omitted
     * (short-closed) items and items on cancelled purchase orders no longer
     * reserve quantity — it returns to the requisition for re-ordering.
     *
     * @param  Builder<PurchaseOrderItem>  $query
     */
    public function scopeAllocating(Builder $query): void
    {
        $query->whereNull('omitted_at')
            ->whereRelation('purchaseOrder', 'status', '!=', PurchaseOrderStatus::CANCELLED);
    }

    /**
     * Get the total quantity recorded as received across all non-rejected receiving
     * reports (pending + verified). Used for over-receipt validation and remaining qty.
     */
    public function getQuantityReceivedAttribute(): int
    {
        return (int) $this->receivingReportItems()->counted()->sum('quantity_received');
    }

    /**
     * Get the quantity received via verified (approved) reports only. Drives the
     * purchase order's full-received status — pending receipts do not count here.
     */
    public function getQuantityVerifiedReceivedAttribute(): int
    {
        return (int) $this->receivingReportItems()->verified()->sum('quantity_received');
    }

    /**
     * Get the remaining quantity yet to be received.
     */
    public function getQuantityRemainingAttribute(): int
    {
        return $this->quantity - $this->quantity_received;
    }
}

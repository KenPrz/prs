<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'purchase_requisition_id',
    'name',
    'quantity',
    'unit_id',
    'price',
    'omitted_at',
    'omit_reason',
])]
class LineItem extends Model
{
    /**
     * The attributes that should be appended to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'quantity_unallocated',
        'quantity_allocated',
        'is_omitted',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price' => 'decimal:2',
            'omitted_at' => 'datetime',
        ];
    }

    /**
     * Whether this line item has been omitted (short-closed) from ordering.
     */
    public function getIsOmittedAttribute(): bool
    {
        return $this->omitted_at !== null;
    }

    /**
     * The purchase requisition this line item belongs to.
     *
     * @return BelongsTo<PurchaseRequisition, LineItem>
     */
    public function purchaseRequisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class);
    }

    /**
     * The unit of the line item.
     *
     * @return BelongsTo<ItemUnit, LineItem>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(ItemUnit::class);
    }

    /**
     * The purchase order items allocated from this PR line item.
     *
     * @return HasMany<PurchaseOrderItem, LineItem>
     */
    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Get the total quantity allocated across purchase orders. Omitted PO items
     * and cancelled purchase orders do not reserve quantity.
     */
    public function getQuantityAllocatedAttribute(): int
    {
        return (int) $this->purchaseOrderItems()->allocating()->sum('quantity');
    }

    /**
     * Get the remaining quantity available for allocation to new purchase orders.
     * An omitted (short-closed) line has nothing left to order.
     */
    public function getQuantityUnallocatedAttribute(): int
    {
        if ($this->is_omitted) {
            return 0;
        }

        return $this->quantity - $this->quantity_allocated;
    }
}

<?php

namespace App\Concerns;

use App\Enums\PriceType;
use Illuminate\Support\Collection;

/**
 * Computes document-level price totals from a single shared `price_type`,
 * applied once to the sum of all priced items rather than per item.
 *
 * @property PriceType $price_type
 */
trait HasPriceTypeTotals
{
    /**
     * The priced items (quantity + price) that make up this document's total.
     *
     * @return Collection<int, mixed>
     */
    abstract protected function pricedItems(): Collection;

    /**
     * The sum of quantity * price across all items, before any VAT conversion.
     */
    public function getRawTotalAttribute(): float
    {
        return $this->pricedItems()->reduce(
            fn (float $carry, $item) => $carry + ((float) $item->quantity * (float) $item->price),
            0.0,
        );
    }

    /**
     * The document total converted to NET (VAT-exclusive).
     */
    public function getNetTotalAttribute(): float
    {
        return $this->price_type?->toNet($this->raw_total) ?? 0.0;
    }

    /**
     * The document total converted to GROSS (VAT-inclusive).
     */
    public function getGrossTotalAttribute(): float
    {
        return $this->price_type?->toGross($this->raw_total) ?? 0.0;
    }

    /**
     * The VAT amount contained in or added to the document total.
     */
    public function getVatTotalAttribute(): float
    {
        return $this->price_type?->vatAmount($this->raw_total) ?? 0.0;
    }
}

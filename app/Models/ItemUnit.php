<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
])]
class ItemUnit extends Model
{
    /**
     * The line items that use this unit.
     *
     * @return HasMany<LineItem, ItemUnit>
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(LineItem::class, 'unit_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => 'string',
            'name' => 'string',
        ];
    }
}

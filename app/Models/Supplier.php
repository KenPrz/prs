<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'email',
    'phone',
    'address',
    'contact_person_1',
    'contact_person_2',
    'contact_person_3',
])]
class Supplier extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contact_person_1' => 'string',
            'contact_person_2' => 'string',
            'contact_person_3' => 'string',
        ];
    }

    /**
     * The purchase orders of the supplier.
     *
     * @return HasMany<PurchaseOrder, Supplier>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}

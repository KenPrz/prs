<?php

namespace App\Models;

use Database\Factories\LineItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LineItem extends Model
{
    /** @use HasFactory<LineItemFactory> */
    use HasFactory;

    protected $fillable = [
        'pr_id',
        'code',
        'unit_id',
        'name',
        'description',
        'quantity',
        'price',
    ];

    public function purchaseRequisition()
    {
        return $this->belongsTo(PurchaseRequisition::class, 'pr_id');
    }

    public function lineItemUnit()
    {
        return $this->belongsTo(LineItemUnit::class, 'line_item_unit_id');
    }
}

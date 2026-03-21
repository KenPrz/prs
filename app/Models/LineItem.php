<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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

    public static function boot()
    {
        parent::boot();

        static::creating(function (LineItem $model) {
            $model->code = $model->generateCode();
        });
    }

    public function purchaseRequisition()
    {
        return $this->belongsTo(PurchaseRequisition::class, 'pr_id');
    }

    public function lineItemUnit()
    {
        return $this->belongsTo(LineItemUnit::class, 'unit_id');
    }

    public function generateCode(): string
    {
        $number = 'LI-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));

        while (LineItem::where('code', $number)->exists()) {
            $number = 'LI-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
        }

        return $number;
    }
}

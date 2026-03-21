<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LineItemUnit extends Model
{
    protected $fillable = [
        'name',
        'code',
    ];

    public function lineItems()
    {
        return $this->hasMany(LineItem::class, 'unit_id');
    }
}

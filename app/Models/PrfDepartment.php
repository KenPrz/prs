<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable([
    'payment_request_form_id',
    'department_id',
])]
class PrfDepartment extends Pivot
{
    /**
     * The table associated with the pivot.
     */
    protected $table = 'prf_departments';
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class RequestingDepartment extends Pivot
{
    protected $table = 'requesting_departments';

    protected $fillable = [
        'pr_id',
        'department_id',
    ];

    /**
     * Get the purchase requisition for the requesting department.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<PurchaseRequisition, RequestingDepartment>
     */
    public function purchaseRequisition()
    {
        return $this->belongsTo(PurchaseRequisition::class, 'pr_id');
    }

    /**
     * Get the department for the requesting department.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Department, RequestingDepartment>
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['purchase_requisition_id', 'department_id'])]
class RequestingDepartment extends Pivot
{
    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_requisition_id' => 'integer',
            'department_id' => 'integer',
        ];
    }

    /**
     * The purchase requisition that this requesting department belongs to.
     *
     * @return BelongsTo<PurchaseRequisition, RequestingDepartment>
     */
    public function purchaseRequisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class);
    }

    /**
     * The department that this requesting department belongs to.
     *
     * @return BelongsTo<Department, RequestingDepartment>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}

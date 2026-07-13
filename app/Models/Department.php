<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'code', 'department_head_id'])]
class Department extends Model
{
    /**
     * The user who heads this department and must sign requisitions first.
     *
     * @return BelongsTo<User, Department>
     */
    public function departmentHead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'department_head_id');
    }

    /**
     * Users assigned to this department.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_departments')
            ->using(UserDepartment::class)
            ->withTimestamps();
    }

    /**
     * Purchase requisitions requesting this department.
     */
    public function purchaseRequisitions(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseRequisition::class, 'requesting_departments')
            ->using(RequestingDepartment::class)
            ->withTimestamps();
    }
}

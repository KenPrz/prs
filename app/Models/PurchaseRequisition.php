<?php

namespace App\Models;

use App\Enums\PurchaseRequisitionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequisition extends Model
{
    /** @use HasFactory<PurchaseRequisitionFactory> */
    use HasFactory;

    /** @var array<string, string> */
    protected $fillable = [
        'number',
        'title',
        'description',
        'status',
        'created_by',
    ];
    
    /** @var array<string, string> */
    protected $casts = [
        'status' => PurchaseRequisitionStatus::class,
    ];

    /**
     * Get the line items for the purchase requisition.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<LineItem, PurchaseRequisition>
     */
    public function lineItems()
    {
        return $this->hasMany(LineItem::class, 'pr_id');
    }

    /**
     * Get the user who created the purchase requisition.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, PurchaseRequisition>
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the requesting departments for the purchase requisition.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Department, PurchaseRequisition>
     */
    public function requestingDepartments()
    {
        return $this->belongsToMany(
            Department::class,
            'requesting_departments',
            'pr_id',
            'department_id'
        )->using(RequestingDepartment::class);
    }
}

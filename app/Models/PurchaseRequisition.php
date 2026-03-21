<?php

namespace App\Models;

use App\Enums\PurchaseRequisitionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * @return HasMany<LineItem, PurchaseRequisition>
     */
    public function lineItems()
    {
        return $this->hasMany(LineItem::class, 'pr_id');
    }

    /**
     * Get the user who created the purchase requisition.
     *
     * @return BelongsTo<User, PurchaseRequisition>
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the requesting departments for the purchase requisition.
     *
     * @return BelongsToMany<Department, PurchaseRequisition>
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

    public static function nextNumber(): string
    {
        $next = (int) static::query()->max('id') + 1;

        return 'PR-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}

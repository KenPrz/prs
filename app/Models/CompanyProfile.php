<?php

namespace App\Models;

use Database\Factories\CompanyProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'name',
    'mobile_no',
    'email',
    'tin',
    'currency',
    'default_received_by_user_id',
])]
class CompanyProfile extends Model
{
    /** @use HasFactory<CompanyProfileFactory> */
    use HasFactory;

    /**
     * @return HasOne<Address, CompanyProfile>
     */
    public function address(): HasOne
    {
        return $this->hasOne(Address::class);
    }

    /**
     * The default "received by" user for new requisitions.
     * When null, the requisition creator is used instead.
     *
     * @return BelongsTo<User, CompanyProfile>
     */
    public function defaultReceivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_received_by_user_id');
    }
}

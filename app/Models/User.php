<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * Departments assigned to the user.
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'user_departments')
            ->using(UserDepartment::class)
            ->withTimestamps();
    }

    /**
     * Signatures uploaded by the user.
     */
    public function signatures(): HasMany
    {
        return $this->hasMany(Signature::class);
    }

    /**
     * The user's active signature.
     *
     * @return HasOne<Signature, User>
     */
    public function activeSignature(): HasOne
    {
        return $this->hasOne(Signature::class)->where('is_active', true);
    }

    /**
     * Whether the user has a signature saved and set as active. Required
     * before the user is allowed to approve (sign) a document.
     */
    public function hasActiveSignature(): bool
    {
        return $this->signatures()->where('is_active', true)->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}

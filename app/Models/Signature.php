<?php

namespace App\Models;

use App\Concerns\RegistersAttachmentMediaCollection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['user_id', 'name', 'is_active'])]
class Signature extends Model implements HasMedia
{
    use InteractsWithMedia;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Boot the model to handle single active signature logic.
     */
    protected static function booted(): void
    {
        static::saved(function (Signature $signature) {
            if ($signature->is_active) {
                // Deactivate all other signatures for this user
                Signature::query()
                    ->where('user_id', $signature->user_id)
                    ->where('id', '!=', $signature->id)
                    ->update(['is_active' => false]);
            }
        });
    }

    /**
     * The user this signature belongs to.
     *
     * @return BelongsTo<User, Signature>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

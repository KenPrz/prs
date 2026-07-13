<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessLog extends Model
{
    use Prunable;

    // Rows are immutable audit records: created_at only, never updated.
    const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'status' => 'integer',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ponytail: 180-day default, ACCESS_LOG_RETENTION_DAYS overrides.
    public function prunable()
    {
        return static::where('created_at', '<', now()->subDays(config('access-log.retention_days')));
    }
}

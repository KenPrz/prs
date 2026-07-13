<?php

namespace App\Models;

use App\Settings\GeneralSettings;
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

    public function prunable()
    {
        return static::where('created_at', '<', now()->subDays(app(GeneralSettings::class)->access_log_retention_days));
    }
}

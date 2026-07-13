<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    /** Default page size for paginated lists (requests may override up to 100). */
    public int $records_per_page;

    public int $access_log_retention_days;

    public static function group(): string
    {
        return 'general';
    }
}

<?php

namespace App\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Trait to list enum cases with their values and titles.
 */
trait ListEnum
{
    /**
     * List the enum cases with their values as keys
     * and human-readable titles as values.
     * 
     * @return Collection<string, string>
     */
    public static function list($capitalize = true): Collection
    {
        return collect(static::cases())->mapWithKeys(fn($case) => [
            $case->value => $capitalize 
                ? Str::title(Str::lower($case->value))
                : Str::lower($case->value)
        ]);
    }
}
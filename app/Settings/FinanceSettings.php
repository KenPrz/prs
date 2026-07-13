<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class FinanceSettings extends Settings
{
    /** VAT rate as a fraction, e.g. 0.12 for 12%. */
    public float $vat_rate;

    public string $currency_symbol;

    public static function group(): string
    {
        return 'finance';
    }
}

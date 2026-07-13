<?php

namespace App\Enums;

use App\Settings\FinanceSettings;

enum PriceType: string
{
    /**
     * Non-VAT price.
     */
    case NON_VAT = 'NON_VAT';

    /**
     * VAT-exclusive price.
     */
    case VAT_EXCLUSIVE = 'VAT_EXCLUSIVE';

    /**
     * VAT-inclusive price.
     */
    case VAT_INCLUSIVE = 'VAT_INCLUSIVE';

    /**
     * Zero VAT sale (subject to VAT at 0%).
     */
    case ZERO_VAT = 'ZERO_VAT';

    /**
     * Convert the given price to NET (VAT-exclusive).
     *
     * @param  float|null  $vatRate  (e.g., 0.12 for 12%; defaults to the configured rate)
     */
    public function toNet(float $price, ?float $vatRate = null): float
    {
        $vatRate ??= self::configuredVatRate();

        return match ($this) {
            self::VAT_INCLUSIVE => $price / (1 + $vatRate),
            self::VAT_EXCLUSIVE => $price,
            self::NON_VAT => $price,
            self::ZERO_VAT => $price,
        };
    }

    /**
     * Convert the given price to GROSS (VAT-inclusive).
     *
     * @param  float|null  $vatRate  (e.g., 0.12 for 12%; defaults to the configured rate)
     */
    public function toGross(float $price, ?float $vatRate = null): float
    {
        $vatRate ??= self::configuredVatRate();

        return match ($this) {
            self::VAT_INCLUSIVE => $price,
            self::VAT_EXCLUSIVE => $price * (1 + $vatRate),
            self::NON_VAT => $price,
            self::ZERO_VAT => $price,
        };
    }

    /**
     * Extract VAT amount from the given price.
     */
    public function vatAmount(float $price, ?float $vatRate = null): float
    {
        $vatRate ??= self::configuredVatRate();

        return match ($this) {
            self::VAT_INCLUSIVE => $price - ($price / (1 + $vatRate)),
            self::VAT_EXCLUSIVE => $price * $vatRate,
            self::NON_VAT => 0.0,
            self::ZERO_VAT => 0.0,
        };
    }

    /**
     * Human-readable label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::VAT_INCLUSIVE => 'VAT Inclusive',
            self::VAT_EXCLUSIVE => 'VAT Exclusive',
            self::NON_VAT => 'Non-VAT',
            self::ZERO_VAT => 'Zero VAT (0%)',
        };
    }

    public static function configuredVatRate(): float
    {
        return app(FinanceSettings::class)->vat_rate;
    }
}

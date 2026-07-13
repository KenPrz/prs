<?php

namespace App\Enums;

enum PurposeType: string
{
    /**
     * Items consumed in normal operations.
     */
    case CONSUMABLE = 'CONSUMABLE';

    /**
     * Durable items not consumed in use.
     */
    case NON_CONSUMABLE = 'NON_CONSUMABLE';

    /**
     * Items intended for office use.
     */
    case OFFICE_USE = 'OFFICE_USE';

    /**
     * Items intended for production use.
     */
    case PRODUCTION_USE = 'PRODUCTION_USE';

    /**
     * Human-readable label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::CONSUMABLE => 'Consumable',
            self::NON_CONSUMABLE => 'Non-Consumable',
            self::OFFICE_USE => 'Office Use',
            self::PRODUCTION_USE => 'Production Use',
        };
    }
}

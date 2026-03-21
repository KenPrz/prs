<?php

namespace App\Enums;

use App\Traits\ListEnum;

/**
 * Purchase Requisition Status Enum
 */
enum PurchaseRequisitionStatus: string
{
    use ListEnum;

    /**
     * The purchase requisition is in draft status.
     */
    case DRAFT = 'DRAFT';

    /**
     * The purchase requisition is pending approval.
     */
    case PENDING = 'PENDING';

    /**
     * The purchase requisition is approved.
     */
    case APPROVED = 'APPROVED';

    /**
     * The purchase requisition is rejected.
     */
    case REJECTED = 'REJECTED';

    /**
     * The purchase requisition is cancelled.
     */
    case CANCELLED = 'CANCELLED';

    /**
     * The purchase requisition is closed.
     */
    case CLOSED = 'CLOSED';

    /**
     * Flux badge color name, or null for the default (zinc) treatment.
     */
    public function badgeColor(): ?string
    {
        return match ($this) {
            self::APPROVED => 'green',
            self::PENDING => 'amber',
            self::REJECTED => 'red',
            self::DRAFT => null,
            self::CANCELLED => null,
            self::CLOSED => null,
        };
    }
}

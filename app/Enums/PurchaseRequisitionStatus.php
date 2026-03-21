<?php

namespace App\Enums;

/**
 * Purchase Requisition Status Enum
 *
 */
enum PurchaseRequisitionStatus: string
{
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
}
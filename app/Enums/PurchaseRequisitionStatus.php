<?php

namespace App\Enums;

enum PurchaseRequisitionStatus: string
{
    /**
     * The purchase requisition is in draft status.
     */
    case DRAFT = 'DRAFT';

    /**
     * The purchase requisition is in reviewing status.
     */
    case REVIEWING = 'REVIEWING';

    /**
     * The purchase requisition is in approved status.
     */
    case APPROVED = 'APPROVED';

    /**
     * The PR is approved, its final document has been snapshotted, and POs may now be created.
     */
    case READY_FOR_PO = 'READY_FOR_PO';

    /**
     * The purchase requisition is in rejected status.
     */
    case REJECTED = 'REJECTED';

    /**
     * The purchase requisition is in cancelled status.
     */
    case CANCELLED = 'CANCELLED';

    /**
     * Some PR items have been allocated to purchase orders.
     */
    case PARTIALLY_ORDERED = 'PARTIALLY_ORDERED';

    /**
     * All PR line-item quantities are allocated to purchase orders, but not all POs are approved yet.
     */
    case FULLY_ALLOCATED = 'FULLY_ALLOCATED';

    /**
     * All PR items are fully allocated and every related PO has been approved.
     */
    case FULLY_ORDERED = 'FULLY_ORDERED';

    /**
     * The purchase requisition is closed (all POs received and processed).
     */
    case CLOSED = 'CLOSED';
}

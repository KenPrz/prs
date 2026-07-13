<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    /**
     * The purchase order is in draft status.
     */
    case DRAFT = 'DRAFT';

    /**
     * The purchase order is pending approval.
     */
    case PENDING_APPROVAL = 'PENDING_APPROVAL';

    /**
     * The purchase order has been approved.
     */
    case APPROVED = 'APPROVED';

    /**
     * The purchase order has been marked as ordered (sealed): its final PDF is
     * snapshotted and receiving reports may now be created against it.
     */
    case RELEASED = 'RELEASED';

    /**
     * Some items from the purchase order have been received.
     */
    case PARTIALLY_RECEIVED = 'PARTIALLY_RECEIVED';

    /**
     * All items from the purchase order have been received.
     */
    case FULLY_RECEIVED = 'FULLY_RECEIVED';

    /**
     * The purchase order has been cancelled.
     */
    case CANCELLED = 'CANCELLED';
}

<?php

namespace App\Enums;

enum PaymentRequestFormStatus: string
{
    /**
     * The payment request form is in draft status.
     */
    case DRAFT = 'DRAFT';

    /**
     * The payment request form is under review / approval.
     */
    case REVIEWING = 'REVIEWING';

    /**
     * The payment request form has been fully approved.
     */
    case APPROVED = 'APPROVED';

    /**
     * The payment request form has been rejected.
     */
    case REJECTED = 'REJECTED';

    /**
     * The payment request form has been cancelled.
     */
    case CANCELLED = 'CANCELLED';
}

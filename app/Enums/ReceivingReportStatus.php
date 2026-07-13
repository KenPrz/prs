<?php

namespace App\Enums;

enum ReceivingReportStatus: string
{
    /**
     * The receiving report is a draft; its quantities already count as allocated.
     */
    case DRAFT = 'DRAFT';

    /**
     * The receiving report is pending verification.
     */
    case PENDING = 'PENDING';

    /**
     * The receiving report has been verified.
     */
    case VERIFIED = 'VERIFIED';

    /**
     * The receiving report has been submitted to accounting.
     */
    case SUBMITTED_TO_ACCOUNTING = 'SUBMITTED_TO_ACCOUNTING';

    /**
     * The receiving report has been rejected.
     */
    case REJECTED = 'REJECTED';

    /**
     * The receiving report was cancelled mid-approval; its quantities return to the pool.
     */
    case CANCELLED = 'CANCELLED';
}

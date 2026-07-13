<?php

namespace App\Enums;

enum WorkflowAssignmentStatus: string
{
    /** Awaiting the assignee's decision. */
    case Pending = 'PENDING';

    /** The assignee approved. */
    case Approved = 'APPROVED';

    /** The assignee rejected. */
    case Rejected = 'REJECTED';

    /** The receiver acknowledged (Receive step). */
    case Received = 'RECEIVED';

    /** Reassigned to another user; a fresh pending assignment supersedes this one. */
    case Reassigned = 'REASSIGNED';

    /** Overridden by a Super Admin completing the step. */
    case Overridden = 'OVERRIDDEN';

    /** Terminated due to a workflow rejection or cancellation. */
    case Stopped = 'STOPPED';
}

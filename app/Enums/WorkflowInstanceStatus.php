<?php

namespace App\Enums;

enum WorkflowInstanceStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Cancelled = 'CANCELLED';
}

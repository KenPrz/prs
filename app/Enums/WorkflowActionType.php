<?php

namespace App\Enums;

/**
 * A recorded action against a workflow. User-driven actions (Approve, Reject,
 * Receive, Reassign, Cancel) plus system actions (Submit, Override, Escalate).
 */
enum WorkflowActionType: string
{
    case Submit = 'SUBMIT';
    case Approve = 'APPROVE';
    case Reject = 'REJECT';
    case Receive = 'RECEIVE';
    case Reassign = 'REASSIGN';
    case Override = 'OVERRIDE';
    case Cancel = 'CANCEL';
    case Escalate = 'ESCALATE';

    public function label(): string
    {
        return match ($this) {
            self::Submit => 'Submitted',
            self::Approve => 'Approved',
            self::Reject => 'Rejected',
            self::Receive => 'Received',
            self::Reassign => 'Reassigned',
            self::Override => 'Overridden',
            self::Cancel => 'Cancelled',
            self::Escalate => 'Escalated',
        };
    }

    /** Actions a participant may take on their active step assignment via the UI. */
    public static function actorActions(): array
    {
        return [self::Approve, self::Reject, self::Receive];
    }
}

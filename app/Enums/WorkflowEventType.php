<?php

namespace App\Enums;

/**
 * Append-only lifecycle timeline entries for a workflow instance. Captures both
 * user decisions and system events (skips, escalations, completion) so the full
 * history can be rendered for audit.
 */
enum WorkflowEventType: string
{
    case Started = 'STARTED';
    case StepActivated = 'STEP_ACTIVATED';
    case StepCompleted = 'STEP_COMPLETED';
    case StepSkipped = 'STEP_SKIPPED';
    case DecisionRecorded = 'DECISION_RECORDED';
    case Reassigned = 'REASSIGNED';
    case Escalated = 'ESCALATED';
    case Completed = 'COMPLETED';
    case Rejected = 'REJECTED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Started => 'Workflow started',
            self::StepActivated => 'Step activated',
            self::StepCompleted => 'Step completed',
            self::StepSkipped => 'Step skipped',
            self::DecisionRecorded => 'Decision recorded',
            self::Reassigned => 'Assignment reassigned',
            self::Escalated => 'Step escalated',
            self::Completed => 'Workflow approved',
            self::Rejected => 'Workflow rejected',
            self::Cancelled => 'Workflow cancelled',
        };
    }
}

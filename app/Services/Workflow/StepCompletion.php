<?php

namespace App\Services\Workflow;

use App\Enums\WorkflowAssignmentStatus;
use App\Enums\WorkflowCompletionStrategy;
use App\Models\WorkflowStepInstance;

/**
 * Decides whether a step instance has met its completion strategy.
 *
 * - ANY: at least one assignee responded positively (approved/received/overridden).
 * - ALL: no assignee is still pending (everyone has decided).
 */
class StepCompletion
{
    private const POSITIVE = [
        WorkflowAssignmentStatus::Approved->value,
        WorkflowAssignmentStatus::Received->value,
        WorkflowAssignmentStatus::Overridden->value,
    ];

    public function isComplete(WorkflowStepInstance $step): bool
    {
        $assignments = $step->assignments;

        if ($step->completion_strategy === WorkflowCompletionStrategy::Any) {
            return $assignments->contains(
                fn ($assignment) => in_array($assignment->status->value, self::POSITIVE, true),
            );
        }

        // ALL: complete once nobody is pending and at least one assignment exists.
        return $assignments->isNotEmpty()
            && ! $assignments->contains(
                fn ($assignment) => $assignment->status === WorkflowAssignmentStatus::Pending,
            );
    }
}

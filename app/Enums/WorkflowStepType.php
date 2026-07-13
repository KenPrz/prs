<?php

namespace App\Enums;

/**
 * The kind of workflow step. Determines what action completes it. Participant
 * resolution (user / role / department head / receiver) is configured
 * separately via WorkflowAssigneeType on each step's assignees.
 */
enum WorkflowStepType: string
{
    /** A standard approval step (one or more approvers approve/reject). */
    case Approval = 'APPROVAL';

    /** Each tagged department's head must approve. Assignees resolved at runtime. */
    case DepartmentHead = 'DEPARTMENT_HEAD';

    /** The designated receiver must acknowledge/receive the document. */
    case Receive = 'RECEIVE';

    public function label(): string
    {
        return match ($this) {
            self::Approval => 'Approval',
            self::DepartmentHead => 'Department Head',
            self::Receive => 'Receive',
        };
    }

    /** The decision action that completes this step type. */
    public function completingAction(): WorkflowActionType
    {
        return match ($this) {
            self::Receive => WorkflowActionType::Receive,
            default => WorkflowActionType::Approve,
        };
    }
}

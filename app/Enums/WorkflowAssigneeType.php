<?php

namespace App\Enums;

/**
 * How a step's participants are resolved into concrete users at the moment a
 * workflow instance is started. Static types (User/Role/Permission) read from
 * the assignee definition; dynamic types resolve from the workflow subject.
 */
enum WorkflowAssigneeType: string
{
    /** A specific user (assignee_definition.user_id). */
    case User = 'USER';

    /** All users holding a role (assignee_identifier = role name). */
    case Role = 'ROLE';

    /** All users holding a permission (assignee_identifier = permission name). */
    case Permission = 'PERMISSION';

    /** The heads of the subject's tagged departments (resolved from the subject). */
    case DepartmentHead = 'DEPARTMENT_HEAD';

    /** The subject's designated receiver(s) (resolved from the subject). */
    case Receiver = 'RECEIVER';

    /** The user who created/requested the subject (resolved from the subject). */
    case Creator = 'CREATOR';

    public function label(): string
    {
        return match ($this) {
            self::User => 'Specific User',
            self::Role => 'Role',
            self::Permission => 'Permission',
            self::DepartmentHead => 'Department Head',
            self::Receiver => 'Receiver',
            self::Creator => 'Creator',
        };
    }

    /** Dynamic types are resolved from the subject rather than the definition. */
    public function isDynamic(): bool
    {
        return match ($this) {
            self::DepartmentHead, self::Receiver, self::Creator => true,
            default => false,
        };
    }
}

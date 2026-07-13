<?php

namespace App\Services\Workflow\Resolvers;

use App\Contracts\WorkflowSubject;
use App\Models\User;
use App\Models\WorkflowStepAssigneeDefinition;
use Illuminate\Support\Collection;

/**
 * Resolves a step's assignee definition into concrete users at the moment a
 * workflow instance starts. One implementation per WorkflowAssigneeType.
 */
interface AssigneeResolver
{
    /**
     * @return Collection<int, User>
     */
    public function resolve(
        WorkflowStepAssigneeDefinition $assignee,
        WorkflowSubject $subject,
        ?User $startedBy,
    ): Collection;
}

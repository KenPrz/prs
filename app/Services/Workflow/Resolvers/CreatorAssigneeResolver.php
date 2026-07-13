<?php

namespace App\Services\Workflow\Resolvers;

use App\Contracts\WorkflowSubject;
use App\Models\User;
use App\Models\WorkflowStepAssigneeDefinition;
use Illuminate\Support\Collection;

class CreatorAssigneeResolver implements AssigneeResolver
{
    public function resolve(
        WorkflowStepAssigneeDefinition $assignee,
        WorkflowSubject $subject,
        ?User $startedBy,
    ): Collection {
        return collect($startedBy ? [$startedBy] : []);
    }
}

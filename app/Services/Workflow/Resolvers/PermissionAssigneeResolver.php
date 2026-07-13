<?php

namespace App\Services\Workflow\Resolvers;

use App\Contracts\WorkflowSubject;
use App\Models\User;
use App\Models\WorkflowStepAssigneeDefinition;
use Illuminate\Support\Collection;

class PermissionAssigneeResolver implements AssigneeResolver
{
    public function resolve(
        WorkflowStepAssigneeDefinition $assignee,
        WorkflowSubject $subject,
        ?User $startedBy,
    ): Collection {
        if (! $assignee->assignee_identifier) {
            return collect();
        }

        return User::query()
            ->whereHas('permissions', fn ($query) => $query->where('name', $assignee->assignee_identifier))
            ->get();
    }
}

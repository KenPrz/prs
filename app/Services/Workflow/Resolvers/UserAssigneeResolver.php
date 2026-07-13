<?php

namespace App\Services\Workflow\Resolvers;

use App\Contracts\WorkflowSubject;
use App\Models\User;
use App\Models\WorkflowStepAssigneeDefinition;
use Illuminate\Support\Collection;

class UserAssigneeResolver implements AssigneeResolver
{
    public function resolve(
        WorkflowStepAssigneeDefinition $assignee,
        WorkflowSubject $subject,
        ?User $startedBy,
    ): Collection {
        if ($assignee->user_id === null) {
            return collect();
        }

        $user = User::query()->find($assignee->user_id);

        return collect($user ? [$user] : []);
    }
}

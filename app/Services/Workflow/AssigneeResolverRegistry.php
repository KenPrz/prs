<?php

namespace App\Services\Workflow;

use App\Contracts\WorkflowSubject;
use App\Enums\WorkflowAssigneeType;
use App\Models\User;
use App\Models\WorkflowStepDefinition;
use App\Services\Workflow\Resolvers\AssigneeResolver;
use App\Services\Workflow\Resolvers\CreatorAssigneeResolver;
use App\Services\Workflow\Resolvers\DepartmentHeadAssigneeResolver;
use App\Services\Workflow\Resolvers\PermissionAssigneeResolver;
use App\Services\Workflow\Resolvers\ReceiverAssigneeResolver;
use App\Services\Workflow\Resolvers\RoleAssigneeResolver;
use App\Services\Workflow\Resolvers\UserAssigneeResolver;
use Illuminate\Support\Collection;

/**
 * Maps each WorkflowAssigneeType to its resolver and expands a step definition
 * into the concrete, deduplicated set of users (with audit source metadata)
 * who should be assigned when an instance starts.
 */
class AssigneeResolverRegistry
{
    /** @var array<string, AssigneeResolver> */
    private array $resolvers;

    public function __construct(
        UserAssigneeResolver $user,
        RoleAssigneeResolver $role,
        PermissionAssigneeResolver $permission,
        DepartmentHeadAssigneeResolver $departmentHead,
        ReceiverAssigneeResolver $receiver,
        CreatorAssigneeResolver $creator,
    ) {
        $this->resolvers = [
            WorkflowAssigneeType::User->value => $user,
            WorkflowAssigneeType::Role->value => $role,
            WorkflowAssigneeType::Permission->value => $permission,
            WorkflowAssigneeType::DepartmentHead->value => $departmentHead,
            WorkflowAssigneeType::Receiver->value => $receiver,
            WorkflowAssigneeType::Creator->value => $creator,
        ];
    }

    /**
     * Resolve all assignees for a step, deduplicated by user id.
     *
     * @return Collection<int, array{user_id: int, source_type: string, source_identifier: ?string}>
     */
    public function resolveStep(WorkflowStepDefinition $step, WorkflowSubject $subject, ?User $startedBy): Collection
    {
        $resolved = collect();

        foreach ($step->assignees as $assignee) {
            /** @var WorkflowAssigneeType $type */
            $type = $assignee->assignee_type;
            $resolver = $this->resolvers[$type->value] ?? null;

            if ($resolver === null) {
                continue;
            }

            $sourceIdentifier = match ($type) {
                WorkflowAssigneeType::User => (string) $assignee->user_id,
                WorkflowAssigneeType::Role, WorkflowAssigneeType::Permission => $assignee->assignee_identifier,
                default => null,
            };

            foreach ($resolver->resolve($assignee, $subject, $startedBy) as $user) {
                $resolved->put($user->id, [
                    'user_id' => $user->id,
                    'source_type' => $type->value,
                    'source_identifier' => $sourceIdentifier,
                ]);
            }
        }

        return $resolved->values();
    }
}

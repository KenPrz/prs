<?php

namespace App\Services\Workflow;

use App\Contracts\WorkflowSubject;
use App\Enums\WorkflowActionType;
use App\Enums\WorkflowAssignmentStatus;
use App\Enums\WorkflowInstanceStatus;
use App\Enums\WorkflowStepStatus;
use App\Models\User;
use App\Models\WorkflowAssignment;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Application-facing facade for the workflow subsystem. Controllers, seeders and
 * services talk to the manager; it resolves definitions, enforces business rules
 * (signatures, start validation, authorization of reassignment), and delegates
 * state transitions to the engine.
 */
class WorkflowManager
{
    public function __construct(
        private WorkflowEngine $engine,
        private WorkflowSummaryPresenter $presenter,
        private AssigneeResolverRegistry $resolvers,
        private ConditionEvaluator $conditions,
    ) {}

    /**
     * Start the subject's workflow. Resolves the active definition by key (or the
     * subject's default) and validates before snapshotting.
     *
     * @param  WorkflowSubject&Model  $subject
     */
    public function start(WorkflowSubject $subject, ?string $key = null, ?User $startedBy = null): WorkflowInstance
    {
        $definition = $this->resolveDefinition($subject, $key);

        $errors = $subject->workflowStartErrors($definition);
        if (! empty($errors)) {
            throw ValidationException::withMessages(['workflow' => $errors]);
        }

        $this->assertStepsResolveAssignees($definition, $subject, $startedBy);

        return $this->engine->start($subject, $definition, $startedBy);
    }

    /**
     * Record a participant's decision. Approvals and receipts require an active
     * signature.
     *
     * @param  WorkflowSubject&Model  $subject
     */
    public function act(WorkflowSubject $subject, User $actor, WorkflowActionType $action, ?string $comment = null): WorkflowInstance
    {
        $instance = $subject->workflowInstance;

        if ($instance === null) {
            abort(409, 'This document has no active workflow.');
        }

        if (
            in_array($action, [WorkflowActionType::Approve, WorkflowActionType::Receive], true)
            && ! $actor->hasActiveSignature()
        ) {
            abort(422, 'You must have a signature saved and set as active before approving.');
        }

        return $this->engine->act($instance, $subject, $actor, $action, $comment);
    }

    /**
     * Reassign a pending assignment on the current step to another user.
     *
     * @param  WorkflowSubject&Model  $subject
     */
    public function reassign(WorkflowSubject $subject, WorkflowAssignment $assignment, User $toUser, User $byUser): WorkflowInstance
    {
        $instance = $subject->workflowInstance;

        if ($instance === null || $instance->status !== WorkflowInstanceStatus::Pending) {
            abort(409, 'This document has no active workflow.');
        }

        $assignment->loadMissing('stepInstance');

        if (
            $assignment->stepInstance->workflow_instance_id !== $instance->id
            || $assignment->stepInstance->status !== WorkflowStepStatus::Active
            || $assignment->status !== WorkflowAssignmentStatus::Pending
        ) {
            abort(422, 'Only a pending assignment on the active step can be reassigned.');
        }

        // The engine's firstOrCreate would silently reuse a decided assignment,
        // leaving the step with no pending assignee — strandable only by override.
        $targetAlreadyDecided = WorkflowAssignment::query()
            ->where('workflow_step_instance_id', $assignment->workflow_step_instance_id)
            ->where('user_id', $toUser->id)
            ->where('status', '!=', WorkflowAssignmentStatus::Pending)
            ->exists();

        if ($targetAlreadyDecided) {
            abort(422, 'That user has already acted on this step.');
        }

        // A delegate must be able to see the document and hold an active
        // signature, or the step just stalls on them.
        $viewPermission = strtolower($subject->workflowDocumentType()).'.view';
        if (! $toUser->can($viewPermission)) {
            abort(422, 'That user cannot view this document type and cannot be assigned.');
        }

        if (! $toUser->hasActiveSignature()) {
            abort(422, 'That user has no active signature and cannot approve documents.');
        }

        return $this->engine->reassign($instance, $assignment, $toUser, $byUser);
    }

    /**
     * @param  WorkflowSubject&Model  $subject
     */
    public function cancel(WorkflowSubject $subject, User $byUser, ?string $comment = null): WorkflowInstance
    {
        $instance = $subject->workflowInstance;

        if ($instance === null) {
            abort(409, 'This document has no active workflow.');
        }

        return $this->engine->cancel($instance, $subject, $byUser, $comment);
    }

    /**
     * True when the user has a pending assignment on the instance's active step
     * (Super Admins may always act on a pending instance).
     *
     * @param  WorkflowSubject&Model  $subject
     */
    public function canAct(WorkflowSubject $subject, ?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $instance = $subject->workflowInstance;
        if ($instance === null || $instance->status !== WorkflowInstanceStatus::Pending) {
            return false;
        }

        if ($user->can('workflow.override')) {
            return true;
        }

        if ($instance->current_step_order === null) {
            return false;
        }

        $instance->loadMissing('steps.assignments');
        $step = $instance->steps->firstWhere('step_order', $instance->current_step_order);

        return $step !== null && $step->assignments->contains(
            fn ($assignment) => (int) $assignment->user_id === (int) $user->id
                && $assignment->status === WorkflowAssignmentStatus::Pending,
        );
    }

    /**
     * @param  WorkflowSubject&Model  $subject
     * @return array<string, mixed>|null
     */
    public function summary(WorkflowSubject $subject): ?array
    {
        return $this->presenter->present($subject);
    }

    /**
     * Fail loudly at submit time when any step that would run resolves zero
     * assignees — otherwise the engine silently skips it and an approval
     * level disappears (misconfigured role/permission name, missing
     * department head, etc.). Condition-skipped steps are exempt.
     *
     * @param  WorkflowSubject&Model  $subject
     */
    private function assertStepsResolveAssignees(WorkflowDefinition $definition, WorkflowSubject $subject, ?User $startedBy): void
    {
        $definition->loadMissing('steps.assignees');

        $unresolvable = [];

        foreach ($definition->steps->where('is_active', true) as $stepDef) {
            if (! $this->conditions->shouldRun($stepDef->condition, $subject)) {
                continue;
            }

            if ($this->resolvers->resolveStep($stepDef, $subject, $startedBy)->isEmpty()) {
                $unresolvable[] = $stepDef->name;
            }
        }

        if (! empty($unresolvable)) {
            throw ValidationException::withMessages([
                'workflow' => 'The following workflow step(s) have no resolvable approvers: '
                    .implode(', ', $unresolvable)
                    .'. Check the workflow configuration (role/permission names, department head, receiver) before submitting.',
            ]);
        }
    }

    /**
     * @param  WorkflowSubject&Model  $subject
     */
    private function resolveDefinition(WorkflowSubject $subject, ?string $key): WorkflowDefinition
    {
        return WorkflowDefinition::query()
            ->where('key', $key ?: $subject->defaultWorkflowKey())
            ->where('document_type', $subject->workflowDocumentType())
            ->where('is_active', true)
            ->firstOrFail();
    }
}

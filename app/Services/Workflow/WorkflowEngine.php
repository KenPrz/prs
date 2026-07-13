<?php

namespace App\Services\Workflow;

use App\Contracts\WorkflowSubject;
use App\Enums\WorkflowActionType;
use App\Enums\WorkflowAssignmentStatus;
use App\Enums\WorkflowEventType;
use App\Enums\WorkflowInstanceStatus;
use App\Enums\WorkflowStepStatus;
use App\Enums\WorkflowStepType;
use App\Events\Workflow\WorkflowCancelled;
use App\Events\Workflow\WorkflowCompleted;
use App\Events\Workflow\WorkflowDecisionRecorded;
use App\Events\Workflow\WorkflowReassigned;
use App\Events\Workflow\WorkflowRejected;
use App\Events\Workflow\WorkflowStarted;
use App\Events\Workflow\WorkflowStepActivated;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowAssignment;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowEvent;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Document-agnostic workflow state machine. Knows nothing about Purchase
 * Requisitions or any specific document — all per-document behaviour is
 * delegated to the WorkflowSubject contract and the assignee resolvers.
 *
 * Audit (WorkflowEvent) is written inside the same transaction as the state
 * change; notification/side-effect domain events are dispatched after commit.
 */
class WorkflowEngine
{
    public function __construct(
        private AssigneeResolverRegistry $resolvers,
        private ConditionEvaluator $conditions,
        private StepCompletion $completion,
    ) {}

    /**
     * Snapshot a definition into a running instance for the subject and activate
     * its first actionable step. Idempotent: returns the existing instance if one
     * already exists for the subject.
     *
     * @param  WorkflowSubject&Model  $subject
     */
    public function start(WorkflowSubject $subject, WorkflowDefinition $definition, ?User $startedBy = null): WorkflowInstance
    {
        /** @var array<int, callable> $afterCommit */
        $afterCommit = [];

        $instance = DB::transaction(function () use ($subject, $definition, $startedBy, &$afterCommit) {
            $existing = WorkflowInstance::query()
                ->where('subject_type', $subject::class)
                ->where('subject_id', $subject->getKey())
                ->first();

            if ($existing) {
                return $existing;
            }

            $definition->loadMissing('steps.assignees');

            /** @var WorkflowInstance $instance */
            $instance = WorkflowInstance::query()->create([
                'workflow_definition_id' => $definition->id,
                'version' => $definition->version,
                'subject_type' => $subject::class,
                'subject_id' => $subject->getKey(),
                'status' => WorkflowInstanceStatus::Pending,
                'current_step_order' => null,
                'started_by' => $startedBy?->id,
                'started_at' => now(),
            ]);

            $this->logEvent($instance, WorkflowEventType::Started, $startedBy);

            $order = 0;
            foreach ($definition->steps->where('is_active', true) as $stepDef) {
                $order++;
                /** @var WorkflowStepInstance $step */
                $step = WorkflowStepInstance::query()->create([
                    'workflow_instance_id' => $instance->id,
                    'step_order' => $order,
                    'name' => $stepDef->name,
                    'step_type' => $stepDef->step_type,
                    'completion_strategy' => $stepDef->completion_strategy,
                    'status' => WorkflowStepStatus::Pending,
                    'sla_hours' => $stepDef->sla_hours,
                ]);

                // Conditional routing: skip the step when its rule is not met.
                if (! $this->conditions->shouldRun($stepDef->condition, $subject)) {
                    $step->forceFill([
                        'status' => WorkflowStepStatus::Skipped,
                        'completed_at' => now(),
                    ])->save();
                    $this->logEvent($instance, WorkflowEventType::StepSkipped, null, $step, ['reason' => 'condition']);

                    continue;
                }

                foreach ($this->resolvers->resolveStep($stepDef, $subject, $startedBy) as $resolved) {
                    WorkflowAssignment::query()->firstOrCreate(
                        [
                            'workflow_step_instance_id' => $step->id,
                            'user_id' => $resolved['user_id'],
                        ],
                        [
                            'source_type' => $resolved['source_type'],
                            'source_identifier' => $resolved['source_identifier'],
                            'status' => WorkflowAssignmentStatus::Pending,
                        ],
                    );
                }
            }

            $subject->setWorkflowPointers($definition, $instance);
            $subject->applyWorkflowStarted();

            $this->advance($instance, 0, $subject, $afterCommit);

            return $instance->refresh();
        });

        $afterCommit[] = fn () => WorkflowStarted::dispatch($instance);
        $this->runAfterCommit($afterCommit);

        return $instance;
    }

    /**
     * Record an actor's decision (Approve / Reject / Receive) on the current step.
     *
     * @param  WorkflowSubject&Model  $subject
     */
    public function act(WorkflowInstance $instance, WorkflowSubject $subject, User $actor, WorkflowActionType $action, ?string $comment = null): WorkflowInstance
    {
        /** @var array<int, callable> $afterCommit */
        $afterCommit = [];

        $instance = DB::transaction(function () use ($instance, $subject, $actor, $action, $comment, &$afterCommit) {
            $instance->refresh();

            if ($instance->status !== WorkflowInstanceStatus::Pending || $instance->current_step_order === null) {
                return $instance;
            }

            /** @var WorkflowStepInstance $step */
            $step = WorkflowStepInstance::query()
                ->where('workflow_instance_id', $instance->id)
                ->where('step_order', $instance->current_step_order)
                ->firstOrFail();

            $canOverride = $actor->can('workflow.override');

            $assignment = WorkflowAssignment::query()
                ->where('workflow_step_instance_id', $step->id)
                ->where('user_id', $actor->id)
                ->lockForUpdate()
                ->first();

            if (! $assignment && $canOverride) {
                $assignment = WorkflowAssignment::query()->create([
                    'workflow_step_instance_id' => $step->id,
                    'user_id' => $actor->id,
                    'source_type' => 'override',
                    'source_identifier' => 'super_admin',
                    'status' => WorkflowAssignmentStatus::Pending,
                ]);
            }

            if (! $assignment) {
                abort(403, 'You are not assigned to act on this step.');
            }

            if ($assignment->status !== WorkflowAssignmentStatus::Pending) {
                return $instance;
            }

            $isReject = $action === WorkflowActionType::Reject;
            $positiveStatus = $step->step_type === WorkflowStepType::Receive
                ? WorkflowAssignmentStatus::Received
                : WorkflowAssignmentStatus::Approved;

            $assignment->update([
                'status' => $isReject ? WorkflowAssignmentStatus::Rejected : $positiveStatus,
                'decided_at' => now(),
            ]);

            /** @var WorkflowAction $record */
            $record = WorkflowAction::query()->create([
                'workflow_instance_id' => $instance->id,
                'workflow_step_instance_id' => $step->id,
                'workflow_assignment_id' => $assignment->id,
                'actor_user_id' => $actor->id,
                'action' => $action,
                'comment' => $comment,
                'metadata' => ['is_override' => $canOverride],
                'acted_at' => now(),
            ]);

            $this->logEvent($instance, WorkflowEventType::DecisionRecorded, $actor, $step, [
                'action' => $action->value,
                'step_order' => $step->step_order,
            ]);
            $afterCommit[] = fn () => WorkflowDecisionRecorded::dispatch($instance, $record);

            if ($isReject) {
                $this->rejectFrom($instance, $step, $subject);
                $afterCommit[] = fn () => WorkflowRejected::dispatch($instance);

                return $instance->refresh();
            }

            if ($canOverride) {
                $step->assignments()
                    ->where('status', WorkflowAssignmentStatus::Pending->value)
                    ->where('id', '!=', $assignment->id)
                    ->update([
                        'status' => WorkflowAssignmentStatus::Overridden->value,
                        'overridden_by' => $actor->id,
                        'overridden_at' => now(),
                    ]);
            }

            $step->load('assignments');

            if ($this->completion->isComplete($step) || $canOverride) {
                $step->forceFill([
                    'status' => $step->step_type === WorkflowStepType::Receive
                        ? WorkflowStepStatus::Received
                        : WorkflowStepStatus::Approved,
                    'completed_at' => now(),
                ])->save();
                $this->logEvent($instance, WorkflowEventType::StepCompleted, $actor, $step);

                $this->advance($instance, $step->step_order, $subject, $afterCommit);
            }

            return $instance->refresh();
        });

        $this->runAfterCommit($afterCommit);

        return $instance;
    }

    /**
     * Reassign a pending assignment to another user. The original is marked
     * Reassigned and a fresh pending assignment is created for the new user.
     */
    public function reassign(WorkflowInstance $instance, WorkflowAssignment $assignment, User $toUser, User $byUser): WorkflowInstance
    {
        $reassignedStep = DB::transaction(function () use ($instance, $assignment, $toUser, $byUser) {
            $step = $assignment->stepInstance;
            $fromUserId = $assignment->user_id;

            $assignment->update([
                'status' => WorkflowAssignmentStatus::Reassigned,
                'decided_at' => now(),
            ]);

            $new = WorkflowAssignment::query()->firstOrCreate(
                [
                    'workflow_step_instance_id' => $step->id,
                    'user_id' => $toUser->id,
                ],
                [
                    'source_type' => 'reassign',
                    'source_identifier' => (string) $fromUserId,
                    'status' => WorkflowAssignmentStatus::Pending,
                    'reassigned_from_user_id' => $fromUserId,
                ],
            );

            WorkflowAction::query()->create([
                'workflow_instance_id' => $instance->id,
                'workflow_step_instance_id' => $step->id,
                'workflow_assignment_id' => $new->id,
                'actor_user_id' => $byUser->id,
                'action' => WorkflowActionType::Reassign,
                'metadata' => ['from_user_id' => $fromUserId, 'to_user_id' => $toUser->id],
                'acted_at' => now(),
            ]);

            $this->logEvent($instance, WorkflowEventType::Reassigned, $byUser, $step, [
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUser->id,
            ]);

            return $step;
        });

        WorkflowReassigned::dispatch($instance, $reassignedStep, $toUser);

        return $instance->refresh();
    }

    /**
     * Cancel a pending instance, stopping all open steps and assignments.
     *
     * @param  WorkflowSubject&Model  $subject
     */
    public function cancel(WorkflowInstance $instance, WorkflowSubject $subject, User $byUser, ?string $comment = null): WorkflowInstance
    {
        $pendingUserIds = DB::transaction(function () use ($instance, $subject, $byUser, $comment) {
            $instance->refresh();

            if ($instance->status !== WorkflowInstanceStatus::Pending) {
                return null;
            }

            $openSteps = WorkflowStepInstance::query()
                ->where('workflow_instance_id', $instance->id)
                ->whereIn('status', [WorkflowStepStatus::Pending->value, WorkflowStepStatus::Active->value])
                ->with('assignments')
                ->get();

            // Captured before stopping: whose pending work is being withdrawn.
            $pendingUserIds = $openSteps
                ->flatMap(fn ($step) => $step->assignments
                    ->where('status', WorkflowAssignmentStatus::Pending)
                    ->pluck('user_id'))
                ->filter()->unique()->values()->all();

            foreach ($openSteps as $step) {
                $step->forceFill(['status' => WorkflowStepStatus::Stopped])->save();
                $step->assignments()
                    ->where('status', WorkflowAssignmentStatus::Pending->value)
                    ->update(['status' => WorkflowAssignmentStatus::Stopped->value]);
            }

            $instance->update([
                'status' => WorkflowInstanceStatus::Cancelled,
                'current_step_order' => null,
                'completed_at' => now(),
            ]);

            WorkflowAction::query()->create([
                'workflow_instance_id' => $instance->id,
                'workflow_step_instance_id' => null,
                'workflow_assignment_id' => null,
                'actor_user_id' => $byUser->id,
                'action' => WorkflowActionType::Cancel,
                'comment' => $comment,
                'acted_at' => now(),
            ]);

            $this->logEvent($instance, WorkflowEventType::Cancelled, $byUser);
            $subject->applyWorkflowStatus(WorkflowInstanceStatus::Cancelled);

            return $pendingUserIds;
        });

        if ($pendingUserIds !== null) {
            WorkflowCancelled::dispatch($instance, $pendingUserIds);
        }

        return $instance->refresh();
    }

    /**
     * Mark an active step as escalated (SLA breached) and record the event. The
     * step stays active; this is a notification/visibility signal.
     */
    public function escalate(WorkflowInstance $instance, WorkflowStepInstance $step): void
    {
        $this->logEvent($instance, WorkflowEventType::Escalated, null, $step, [
            'due_at' => $step->due_at?->toIso8601String(),
        ]);
    }

    /**
     * Advance to the next actionable step after $fromOrder, skipping steps with no
     * assignees. Finalizes the instance as Approved when none remain.
     *
     * @param  array<int, callable>  $afterCommit
     * @param  WorkflowSubject&Model  $subject
     */
    private function advance(WorkflowInstance $instance, int $fromOrder, WorkflowSubject $subject, array &$afterCommit): void
    {
        $steps = WorkflowStepInstance::query()
            ->where('workflow_instance_id', $instance->id)
            ->where('step_order', '>', $fromOrder)
            ->where('status', WorkflowStepStatus::Pending->value)
            ->orderBy('step_order')
            ->with('assignments')
            ->get();

        foreach ($steps as $step) {
            if ($step->assignments->isEmpty()) {
                $step->forceFill([
                    'status' => WorkflowStepStatus::Skipped,
                    'completed_at' => now(),
                ])->save();
                $this->logEvent($instance, WorkflowEventType::StepSkipped, null, $step, ['reason' => 'no_assignees']);

                continue;
            }

            $step->forceFill([
                'status' => WorkflowStepStatus::Active,
                'activated_at' => now(),
                'due_at' => $step->sla_hours ? now()->addHours($step->sla_hours) : null,
            ])->save();

            $instance->update(['current_step_order' => $step->step_order]);
            $this->logEvent($instance, WorkflowEventType::StepActivated, null, $step);
            $afterCommit[] = fn () => WorkflowStepActivated::dispatch($instance, $step);

            return;
        }

        // No further steps — the workflow is fully approved.
        $instance->update([
            'status' => WorkflowInstanceStatus::Approved,
            'current_step_order' => null,
            'completed_at' => now(),
        ]);
        $this->logEvent($instance, WorkflowEventType::Completed, null);
        $subject->applyWorkflowStatus(WorkflowInstanceStatus::Approved);
        $afterCommit[] = fn () => WorkflowCompleted::dispatch($instance);
    }

    /**
     * Reject the current step and stop everything downstream.
     *
     * @param  WorkflowSubject&Model  $subject
     */
    private function rejectFrom(WorkflowInstance $instance, WorkflowStepInstance $step, WorkflowSubject $subject): void
    {
        $step->assignments()
            ->where('status', WorkflowAssignmentStatus::Pending->value)
            ->update(['status' => WorkflowAssignmentStatus::Stopped->value]);
        $step->forceFill(['status' => WorkflowStepStatus::Rejected, 'completed_at' => now()])->save();

        $downstream = WorkflowStepInstance::query()
            ->where('workflow_instance_id', $instance->id)
            ->where('step_order', '>', $step->step_order)
            ->whereNotIn('status', [WorkflowStepStatus::Skipped->value])
            ->get();

        foreach ($downstream as $next) {
            $next->forceFill(['status' => WorkflowStepStatus::Stopped])->save();
            $next->assignments()
                ->where('status', WorkflowAssignmentStatus::Pending->value)
                ->update(['status' => WorkflowAssignmentStatus::Stopped->value]);
        }

        $instance->update([
            'status' => WorkflowInstanceStatus::Rejected,
            'current_step_order' => null,
            'completed_at' => now(),
        ]);
        $this->logEvent($instance, WorkflowEventType::Rejected, null);
        $subject->applyWorkflowStatus(WorkflowInstanceStatus::Rejected);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logEvent(WorkflowInstance $instance, WorkflowEventType $type, ?User $actor, ?WorkflowStepInstance $step = null, array $payload = []): void
    {
        WorkflowEvent::query()->create([
            'workflow_instance_id' => $instance->id,
            'workflow_step_instance_id' => $step?->id,
            'event_type' => $type,
            'actor_user_id' => $actor?->id,
            'payload' => $payload ?: null,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<int, callable>  $callbacks
     */
    private function runAfterCommit(array $callbacks): void
    {
        foreach ($callbacks as $callback) {
            $callback();
        }
    }
}

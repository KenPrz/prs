<?php

namespace App\Listeners;

use App\Enums\WorkflowActionType;
use App\Enums\WorkflowAssignmentStatus;
use App\Enums\WorkflowStepStatus;
use App\Events\Workflow\WorkflowCancelled;
use App\Events\Workflow\WorkflowCompleted;
use App\Events\Workflow\WorkflowDecisionRecorded;
use App\Events\Workflow\WorkflowReassigned;
use App\Events\Workflow\WorkflowRejected;
use App\Events\Workflow\WorkflowStarted;
use App\Events\Workflow\WorkflowStepActivated;
use App\Events\Workflow\WorkflowStepEscalated;
use App\Models\PurchaseRequisition;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepInstance;
use App\Notifications\AddedToSigningChain;
use App\Notifications\ApprovalActionRequired;
use App\Notifications\DocumentOutcome;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Maps every workflow lifecycle event to its notifications. The in-app badge
 * only ever comes from action-required items (your turn / overdue /
 * reassigned) and outcomes — chain membership is email-only.
 */
class WorkflowNotificationSubscriber
{
    public function subscribe(Dispatcher $events): array
    {
        return [
            WorkflowStarted::class => 'onStarted',
            WorkflowStepActivated::class => 'onStepActivated',
            WorkflowDecisionRecorded::class => 'onDecisionRecorded',
            WorkflowStepEscalated::class => 'onStepEscalated',
            WorkflowReassigned::class => 'onReassigned',
            WorkflowCompleted::class => 'onCompleted',
            WorkflowRejected::class => 'onRejected',
            WorkflowCancelled::class => 'onCancelled',
        ];
    }

    /** Every signee in the chain learns they are part of it (except the submitter). */
    public function onStarted(WorkflowStarted $event): void
    {
        $subject = $event->instance->subject;
        if ($subject === null) {
            return;
        }

        $event->instance->loadMissing('steps.assignments');

        $userIds = $event->instance->steps
            ->flatMap(fn ($step) => $step->assignments->pluck('user_id'))
            ->filter()
            ->unique()
            ->reject(fn ($id) => $id === $event->instance->started_by)
            ->values();

        $this->notify($userIds, new AddedToSigningChain($subject));
    }

    /** The step's pending assignees: it is now their turn. */
    public function onStepActivated(WorkflowStepActivated $event): void
    {
        $this->notifyPendingAssignees($event->instance, $event->step, 'your_turn');
    }

    /**
     * A decision left the step still active (ALL strategy, partially complete):
     * the next pending assignee is now up and gets their action-required email.
     */
    public function onDecisionRecorded(WorkflowDecisionRecorded $event): void
    {
        $subject = $event->instance->subject;
        $step = $event->action->stepInstance;

        if ($subject === null || $step === null || $step->status !== WorkflowStepStatus::Active) {
            return;
        }

        $this->notify(
            $this->getEmailEligibleUserIds($step),
            new ApprovalActionRequired($subject, $step->name, 'your_turn', sendMail: true),
        );
    }

    /** SLA breached: remind the step's pending assignees. */
    public function onStepEscalated(WorkflowStepEscalated $event): void
    {
        $this->notifyPendingAssignees($event->instance, $event->step, 'overdue');
    }

    /** The delegate now owns the pending decision. */
    public function onReassigned(WorkflowReassigned $event): void
    {
        $subject = $event->instance->subject;
        if ($subject === null) {
            return;
        }

        $event->toUser->notify(new ApprovalActionRequired($subject, $event->step->name, 'reassigned'));
    }

    /** Fully approved: tell the submitter (and the PR's designated orderer). */
    public function onCompleted(WorkflowCompleted $event): void
    {
        $subject = $event->instance->subject;
        if ($subject === null) {
            return;
        }

        $userIds = collect([$event->instance->started_by]);

        if ($subject instanceof PurchaseRequisition) {
            $userIds->push($subject->to_be_ordered_by_id);
        }

        $this->notify($userIds->filter()->unique()->values(), new DocumentOutcome($subject, 'approved'));
    }

    /** Rejected: tell the submitter, with the rejection comment. */
    public function onRejected(WorkflowRejected $event): void
    {
        $subject = $event->instance->subject;
        if ($subject === null || $event->instance->started_by === null) {
            return;
        }

        $comment = $event->instance->actions()
            ->where('action', WorkflowActionType::Reject)
            ->latest('acted_at')
            ->first()
            ?->comment;

        $this->notify(collect([$event->instance->started_by]), new DocumentOutcome($subject, 'rejected', $comment));
    }

    /** Cancelled mid-approval: pending assignees no longer need to act (in-app only). */
    public function onCancelled(WorkflowCancelled $event): void
    {
        $subject = $event->instance->subject;
        if ($subject === null) {
            return;
        }

        $this->notify(collect($event->pendingUserIds)->unique()->values(), new DocumentOutcome($subject, 'cancelled'));
    }

    private function notifyPendingAssignees(WorkflowInstance $instance, WorkflowStepInstance $step, string $context): void
    {
        $subject = $instance->subject;
        if ($subject === null) {
            return;
        }

        $allPendingIds = $step->assignments()
            ->where('status', WorkflowAssignmentStatus::Pending->value)
            ->pluck('user_id')
            ->filter()->unique()->values();

        if ($allPendingIds->isEmpty()) {
            return;
        }

        $emailEligibleIds = $this->getEmailEligibleUserIds($step);

        // Send to email-eligible users with mail + database
        $this->notify($emailEligibleIds, new ApprovalActionRequired($subject, $step->name, $context, sendMail: true));

        // Send to remaining pending users with database-only
        $databaseOnlyIds = $allPendingIds->diff($emailEligibleIds);
        if ($databaseOnlyIds->isNotEmpty()) {
            $this->notify($databaseOnlyIds, new ApprovalActionRequired($subject, $step->name, $context, sendMail: false));
        }
    }

    private function getEmailEligibleUserIds(WorkflowStepInstance $step): Collection
    {
        $pendingAssignments = $step->assignments()
            ->where('status', WorkflowAssignmentStatus::Pending->value)
            ->orderBy('id')
            ->get();

        if ($pendingAssignments->isEmpty()) {
            return collect();
        }

        // For ANY strategy, all pending assignees get email
        if ($step->completion_strategy->value === 'ANY') {
            return $pendingAssignments->pluck('user_id')->unique();
        }

        // For ALL strategy, only the first pending assignee gets email
        return collect([$pendingAssignments->first()->user_id]);
    }

    /**
     * @param  Collection<int, int>  $userIds
     */
    private function notify($userIds, mixed $notification): void
    {
        if ($userIds->isEmpty()) {
            return;
        }

        Notification::send(User::query()->whereIn('id', $userIds)->get(), $notification);
    }
}

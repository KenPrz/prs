<?php

namespace App\Services\Workflow;

use App\Contracts\WorkflowSubject;
use Illuminate\Database\Eloquent\Model;

/**
 * Builds the read model consumed by the approval panel for any workflow subject.
 * Single implementation replacing the per-controller buildApprovalSummary().
 */
class WorkflowSummaryPresenter
{
    /**
     * @param  WorkflowSubject&Model  $subject
     * @return array<string, mixed>|null
     */
    public function present(WorkflowSubject $subject): ?array
    {
        $instance = $subject->workflowInstance;

        if ($instance === null) {
            return null;
        }

        $instance->loadMissing([
            'definition',
            'steps.assignments.user',
            'steps.assignments.overriddenByUser',
            'actions.actor',
            'events.actor',
        ]);

        return [
            'workflow_label' => $instance->definition?->name,
            'workflow_key' => $instance->definition?->key,
            'workflow_version' => $instance->version,
            'instance_status' => $instance->status->value,
            'current_step_order' => $instance->current_step_order,
            'steps' => $instance->steps->map(fn ($step) => [
                'step_order' => $step->step_order,
                'name' => $step->name,
                'step_type' => $step->step_type->value,
                'completion_strategy' => $step->completion_strategy->value,
                'status' => $step->status->value,
                'due_at' => $step->due_at?->toIso8601String(),
                'assignments' => $step->assignments->map(fn ($assignment) => [
                    'id' => $assignment->id,
                    'user_id' => $assignment->user_id,
                    'user_name' => $assignment->user?->name ?? 'Unknown',
                    'status' => $assignment->status->value,
                    'source_type' => $assignment->source_type,
                    'overridden_by_name' => $assignment->overriddenByUser?->name,
                    'overridden_at' => $assignment->overridden_at?->toIso8601String(),
                ])->all(),
            ])->all(),
            'decisions' => $instance->actions->map(fn ($action) => [
                'actor_name' => $action->actor?->name ?? 'Unknown',
                'action' => $action->action->value,
                'comment' => $action->comment,
                'acted_at' => $action->acted_at?->toIso8601String(),
                'is_override' => $action->metadata['is_override'] ?? false,
            ])->all(),
            'history' => $instance->events->map(fn ($event) => [
                'event_type' => $event->event_type->value,
                'label' => $event->event_type->label(),
                'actor_name' => $event->actor?->name,
                'payload' => $event->payload,
                'created_at' => $event->created_at?->toIso8601String(),
            ])->all(),
        ];
    }
}

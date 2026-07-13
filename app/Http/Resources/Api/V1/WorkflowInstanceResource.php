<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowInstanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'current_step_order' => $this->current_step_order,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'steps' => $this->whenLoaded('steps', fn () => $this->steps->map(fn ($step) => [
                'id' => $step->id,
                'step_order' => $step->step_order,
                'name' => $step->name,
                'step_type' => $step->step_type->value,
                'completion_strategy' => $step->completion_strategy->value,
                'status' => $step->status->value,
                'due_at' => $step->due_at,
                'assignments' => $step->relationLoaded('assignments')
                    ? $step->assignments->map(fn ($assignment) => [
                        'id' => $assignment->id,
                        'user' => $assignment->relationLoaded('user') && $assignment->user
                            ? new UserResource($assignment->user)
                            : null,
                        'status' => $assignment->status->value,
                        'decided_at' => $assignment->decided_at,
                    ])
                    : [],
            ])),
            'actions' => $this->whenLoaded('actions', fn () => $this->actions->map(fn ($action) => [
                'id' => $action->id,
                'actor' => $action->relationLoaded('actor') && $action->actor
                    ? new UserResource($action->actor)
                    : null,
                'action' => $action->action->value,
                'comment' => $action->comment,
                'acted_at' => $action->acted_at,
            ])),
        ];
    }
}

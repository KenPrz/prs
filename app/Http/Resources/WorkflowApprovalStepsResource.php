<?php

namespace App\Http\Resources;

use App\Models\WorkflowInstance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowInstance
 */
class WorkflowApprovalStepsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'instance_id' => $this->id,
            'instance_status' => $this->status->value,
            'current_step_order' => $this->current_step_order,
            'steps' => $this->whenLoaded(
                'steps',
                fn () => $this->steps->map(function ($step) use ($request): array {
                    $approvals = [];
                    if ($step->relationLoaded('assignments')) {
                        $approvals = $step->assignments->map(function ($assignment) use ($request): array {
                            $approver = null;
                            if ($assignment->relationLoaded('user') && $assignment->user !== null) {
                                $approver = (new UserSummaryResource($assignment->user))->toArray($request);
                            }

                            return [
                                'approver' => $approver,
                                'state' => $assignment->status->value,
                            ];
                        })->values()->all();
                    }

                    return [
                        'step_order' => $step->step_order,
                        'name' => $step->name,
                        'step_status' => $step->status->value,
                        'approvals' => $approvals,
                    ];
                })->values()->all(),
            ),
        ];
    }
}

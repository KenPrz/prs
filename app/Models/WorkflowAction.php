<?php

namespace App\Models;

use App\Enums\WorkflowActionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workflow_instance_id',
    'workflow_step_instance_id',
    'workflow_assignment_id',
    'actor_user_id',
    'action',
    'comment',
    'metadata',
    'acted_at',
])]
class WorkflowAction extends Model
{
    protected function casts(): array
    {
        return [
            'action' => WorkflowActionType::class,
            'metadata' => 'array',
            'acted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WorkflowInstance, WorkflowAction>
     */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    /**
     * @return BelongsTo<WorkflowStepInstance, WorkflowAction>
     */
    public function stepInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowStepInstance::class, 'workflow_step_instance_id');
    }

    /**
     * @return BelongsTo<WorkflowAssignment, WorkflowAction>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(WorkflowAssignment::class, 'workflow_assignment_id');
    }

    /**
     * @return BelongsTo<User, WorkflowAction>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}

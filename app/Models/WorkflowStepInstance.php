<?php

namespace App\Models;

use App\Enums\WorkflowCompletionStrategy;
use App\Enums\WorkflowStepStatus;
use App\Enums\WorkflowStepType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'workflow_instance_id',
    'step_order',
    'name',
    'step_type',
    'completion_strategy',
    'status',
    'sla_hours',
    'due_at',
    'activated_at',
    'completed_at',
])]
class WorkflowStepInstance extends Model
{
    protected function casts(): array
    {
        return [
            'step_type' => WorkflowStepType::class,
            'completion_strategy' => WorkflowCompletionStrategy::class,
            'status' => WorkflowStepStatus::class,
            'sla_hours' => 'integer',
            'due_at' => 'datetime',
            'activated_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WorkflowInstance, WorkflowStepInstance>
     */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    /**
     * @return HasMany<WorkflowAssignment, WorkflowStepInstance>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(WorkflowAssignment::class, 'workflow_step_instance_id');
    }
}

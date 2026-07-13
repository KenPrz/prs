<?php

namespace App\Models;

use App\Enums\WorkflowCompletionStrategy;
use App\Enums\WorkflowStepType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'workflow_definition_id',
    'step_order',
    'name',
    'step_type',
    'completion_strategy',
    'sla_hours',
    'condition',
    'is_active',
    'meta',
])]
class WorkflowStepDefinition extends Model
{
    protected function casts(): array
    {
        return [
            'step_type' => WorkflowStepType::class,
            'completion_strategy' => WorkflowCompletionStrategy::class,
            'sla_hours' => 'integer',
            'condition' => 'array',
            'is_active' => 'boolean',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<WorkflowDefinition, WorkflowStepDefinition>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    /**
     * @return HasMany<WorkflowStepAssigneeDefinition, WorkflowStepDefinition>
     */
    public function assignees(): HasMany
    {
        return $this->hasMany(WorkflowStepAssigneeDefinition::class);
    }
}

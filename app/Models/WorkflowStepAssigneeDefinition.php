<?php

namespace App\Models;

use App\Enums\WorkflowAssigneeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workflow_step_definition_id',
    'assignee_type',
    'assignee_identifier',
    'user_id',
])]
class WorkflowStepAssigneeDefinition extends Model
{
    protected function casts(): array
    {
        return [
            'assignee_type' => WorkflowAssigneeType::class,
        ];
    }

    /**
     * @return BelongsTo<WorkflowStepDefinition, WorkflowStepAssigneeDefinition>
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStepDefinition::class, 'workflow_step_definition_id');
    }

    /**
     * @return BelongsTo<User, WorkflowStepAssigneeDefinition>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

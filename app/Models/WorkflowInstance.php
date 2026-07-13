<?php

namespace App\Models;

use App\Enums\WorkflowInstanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'workflow_definition_id',
    'version',
    'subject_type',
    'subject_id',
    'status',
    'current_step_order',
    'started_by',
    'started_at',
    'completed_at',
])]
class WorkflowInstance extends Model
{
    protected function casts(): array
    {
        return [
            'status' => WorkflowInstanceStatus::class,
            'version' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WorkflowDefinition, WorkflowInstance>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    /**
     * @return MorphTo<Model, WorkflowInstance>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<WorkflowStepInstance, WorkflowInstance>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStepInstance::class)->orderBy('step_order');
    }

    /**
     * @return HasMany<WorkflowAction, WorkflowInstance>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(WorkflowAction::class);
    }

    /**
     * @return HasMany<WorkflowEvent, WorkflowInstance>
     */
    public function events(): HasMany
    {
        return $this->hasMany(WorkflowEvent::class)->orderBy('created_at');
    }

    /**
     * @return BelongsTo<User, WorkflowInstance>
     */
    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }
}

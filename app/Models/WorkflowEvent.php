<?php

namespace App\Models;

use App\Enums\WorkflowEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workflow_instance_id',
    'workflow_step_instance_id',
    'event_type',
    'actor_user_id',
    'payload',
    'created_at',
])]
class WorkflowEvent extends Model
{
    /** This is an append-only log; only created_at is tracked. */
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'event_type' => WorkflowEventType::class,
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WorkflowInstance, WorkflowEvent>
     */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    /**
     * @return BelongsTo<WorkflowStepInstance, WorkflowEvent>
     */
    public function stepInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowStepInstance::class, 'workflow_step_instance_id');
    }

    /**
     * @return BelongsTo<User, WorkflowEvent>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}

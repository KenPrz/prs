<?php

namespace App\Models;

use App\Enums\WorkflowAssignmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workflow_step_instance_id',
    'user_id',
    'source_type',
    'source_identifier',
    'status',
    'decided_at',
    'reassigned_from_user_id',
    'overridden_by',
    'overridden_at',
])]
class WorkflowAssignment extends Model
{
    protected function casts(): array
    {
        return [
            'status' => WorkflowAssignmentStatus::class,
            'decided_at' => 'datetime',
            'overridden_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WorkflowStepInstance, WorkflowAssignment>
     */
    public function stepInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowStepInstance::class, 'workflow_step_instance_id');
    }

    /**
     * @return BelongsTo<User, WorkflowAssignment>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, WorkflowAssignment>
     */
    public function reassignedFromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reassigned_from_user_id');
    }

    /**
     * @return BelongsTo<User, WorkflowAssignment>
     */
    public function overriddenByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }
}

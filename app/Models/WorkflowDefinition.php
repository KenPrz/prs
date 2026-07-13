<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'key',
    'name',
    'description',
    'document_type',
    'version',
    'is_active',
])]
class WorkflowDefinition extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    /**
     * @return HasMany<WorkflowStepDefinition, WorkflowDefinition>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStepDefinition::class)->orderBy('step_order');
    }

    /**
     * @return HasMany<WorkflowInstance, WorkflowDefinition>
     */
    public function instances(): HasMany
    {
        return $this->hasMany(WorkflowInstance::class);
    }
}

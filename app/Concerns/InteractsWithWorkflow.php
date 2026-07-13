<?php

namespace App\Concerns;

use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Shared workflow plumbing for document models. Provides the workflow relations
 * and pointer persistence; each model still declares its own document type,
 * default key, status mapping, and dynamic participant resolution to satisfy
 * the WorkflowSubject contract.
 *
 * Default implementations of resolveDepartmentHeads()/resolveReceivers() return
 * empty collections; documents override what applies to them.
 */
trait InteractsWithWorkflow
{
    /**
     * @return BelongsTo<WorkflowDefinition, covariant self>
     */
    public function workflowDefinition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_id');
    }

    /**
     * @return BelongsTo<WorkflowInstance, covariant self>
     */
    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function setWorkflowPointers(WorkflowDefinition $definition, WorkflowInstance $instance): void
    {
        $this->forceFill([
            'workflow_id' => $definition->id,
            'workflow_instance_id' => $instance->id,
        ])->save();
    }

    /**
     * @return array<int, string>
     */
    public function workflowStartErrors(\App\Models\WorkflowDefinition $definition): array
    {
        return [];
    }

    /**
     * @return Collection<int, \App\Models\User>
     */
    public function resolveDepartmentHeads(): Collection
    {
        return collect();
    }

    /**
     * @return Collection<int, \App\Models\User>
     */
    public function resolveReceivers(): Collection
    {
        return collect();
    }
}

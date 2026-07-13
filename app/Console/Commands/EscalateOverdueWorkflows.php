<?php

namespace App\Console\Commands;

use App\Enums\WorkflowEventType;
use App\Enums\WorkflowStepStatus;
use App\Events\Workflow\WorkflowStepEscalated;
use App\Models\WorkflowStepInstance;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Console\Command;

class EscalateOverdueWorkflows extends Command
{
    protected $signature = 'workflow:escalate';

    protected $description = 'Escalate active workflow steps whose SLA (due_at) has elapsed.';

    public function handle(WorkflowEngine $engine): int
    {
        $overdue = WorkflowStepInstance::query()
            ->where('status', WorkflowStepStatus::Active->value)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            // Don't re-escalate a step that already has an escalation event.
            ->whereDoesntHave('instance.events', function ($query) {
                $query->where('event_type', WorkflowEventType::Escalated->value)
                    ->whereColumn('workflow_step_instance_id', 'workflow_step_instances.id');
            })
            ->with('instance.subject')
            ->get();

        foreach ($overdue as $step) {
            $engine->escalate($step->instance, $step);
            WorkflowStepEscalated::dispatch($step->instance, $step);
        }

        $this->info("Escalated {$overdue->count()} overdue step(s).");

        return self::SUCCESS;
    }
}

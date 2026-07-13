<?php

namespace Database\Seeders;

use App\Enums\WorkflowAssigneeType;
use App\Enums\WorkflowCompletionStrategy;
use App\Enums\WorkflowStepType;
use App\Models\WorkflowDefinition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkflowSeeder extends Seeder
{
    /**
     * Default, fully-configurable workflow definitions. Department-Head and
     * Receive are ordinary, reorderable steps — not hardcoded behaviour.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $role = fn (string $name): array => ['type' => WorkflowAssigneeType::Role, 'identifier' => $name];

            // Purchase Requisition: department heads check first, the approval
            // chain signs, then the designated receiver acknowledges receipt.
            $this->seed('pr.default', 'Purchase Requisition Default', 'PR', [
                ['Checked By (Department Heads)', WorkflowStepType::DepartmentHead, WorkflowCompletionStrategy::All, [['type' => WorkflowAssigneeType::DepartmentHead]]],
                ['Finance Manager', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [$role('finance_manager')]],
                ['General Manager', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [$role('general_manager')]],
                ['Director', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [$role('director')]],
                ['President', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [$role('president')]],
                ['Received By', WorkflowStepType::Receive, WorkflowCompletionStrategy::Any, [['type' => WorkflowAssigneeType::Receiver]]],
            ]);

            $this->seed('po.default', 'Purchase Order Default', 'PO', [
                ['Finance Manager', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [$role('finance_manager')]],
                ['General Manager', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [$role('general_manager')]],
                ['Director', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [$role('director')]],
                ['President', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [$role('president')]],
            ]);

            $this->seed('rr.default', 'Receiving Report Default', 'RR', [
                ['Preparer', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [$role('logistics')]],
                ['Finance Manager', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [$role('finance_manager')]],
                ['Director', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [$role('director')]],
            ]);

            $this->seed('prf.default', 'Payment Request Form Default', 'PRF', [
                ['Finance Manager', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [$role('finance_manager')]],
                ['General Manager', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [$role('general_manager')]],
                ['Director', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [$role('director')]],
                ['President', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [$role('president')]],
            ]);
        });
    }

    /**
     * @param  array<int, array{0: string, 1: WorkflowStepType, 2: WorkflowCompletionStrategy, 3: array<int, array{type: WorkflowAssigneeType, identifier?: string, user_id?: int}>}>  $steps
     */
    private function seed(string $key, string $name, string $documentType, array $steps): void
    {
        $definition = WorkflowDefinition::query()->updateOrCreate(
            ['key' => $key],
            ['name' => $name, 'document_type' => $documentType, 'version' => 1, 'is_active' => true],
        );

        $definition->steps()->delete();

        foreach ($steps as $order => [$stepName, $type, $strategy, $assignees]) {
            $step = $definition->steps()->create([
                'step_order' => $order + 1,
                'name' => $stepName,
                'step_type' => $type,
                'completion_strategy' => $strategy,
                'is_active' => true,
            ]);

            foreach ($assignees as $assignee) {
                $step->assignees()->create([
                    'assignee_type' => $assignee['type'],
                    'assignee_identifier' => $assignee['identifier'] ?? null,
                    'user_id' => $assignee['user_id'] ?? null,
                ]);
            }
        }
    }
}

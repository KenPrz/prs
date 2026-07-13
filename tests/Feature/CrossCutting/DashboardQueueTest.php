<?php

use App\Enums\WorkflowAssigneeType;
use App\Enums\WorkflowCompletionStrategy;
use App\Enums\WorkflowStepType;
use App\Services\Workflow\WorkflowManager;

// The queue lists only assignments on the running instance's active step, with
// a link that matches the subject's document type.
test('the action queue shows a pending assignment with the correct document link', function () {
    $finance = roleApprover('finance_manager');
    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);
    $pr = makePr();
    app(WorkflowManager::class)->start($pr, null, $pr->requestor);

    $this->actingAs($finance)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('queue.0.doc_type', 'PR')
            ->where('queue.0.href', "/purchase-requisitions/{$pr->id}")
            ->where('queue.0.number', $pr->pr_number)
            ->where('queue.0.step_name', 'Finance'));
});

test('the action queue drops items once the workflow is decided', function () {
    $finance = roleApprover('finance_manager');
    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);
    $pr = makePr();
    app(WorkflowManager::class)->start($pr, null, $pr->requestor);

    $this->actingAs($finance)
        ->postJson(route('workflows.act', ['type' => 'pr', 'id' => $pr->id]), ['action' => 'APPROVE'])
        ->assertOk();

    $this->actingAs($finance)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('queue', []));
});

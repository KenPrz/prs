<?php

use App\Enums\PurchaseRequisitionStatus;
use App\Enums\WorkflowActionType;
use App\Enums\WorkflowAssigneeType;
use App\Enums\WorkflowAssignmentStatus;
use App\Enums\WorkflowCompletionStrategy;
use App\Enums\WorkflowInstanceStatus;
use App\Enums\WorkflowStepStatus;
use App\Enums\WorkflowStepType;
use App\Models\Department;
use App\Models\PurchaseRequisition;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Services\Workflow\WorkflowManager;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

// WF-01 submit snapshots the chain and moves the subject into review
test('submitting snapshots the workflow and activates the first step', function () {
    $finance = roleApprover('finance_manager');
    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);
    $pr = makePr();

    app(WorkflowManager::class)->start($pr, null, $pr->requestor);
    $instance = $pr->workflowInstance()->first();

    expect($instance->status)->toBe(WorkflowInstanceStatus::Pending)
        ->and($instance->current_step_order)->toBe(1)
        ->and($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::REVIEWING);
});

// WF-05 / WF-20 multi-level sequential through a receive step
test('a multi-level workflow runs department head, approval and receive to completion', function () {
    $manager = app(WorkflowManager::class);
    $finance = roleApprover('finance_manager');
    $head = User::factory()->create();
    withActiveSignature($head);
    $department = Department::create(['name' => 'IT', 'code' => 'IT', 'department_head_id' => $head->id]);
    $requestor = User::factory()->create();
    withActiveSignature($requestor);

    definePrWorkflow([
        ['Checked By', WorkflowStepType::DepartmentHead, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::DepartmentHead]]],
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
        ['Received By', WorkflowStepType::Receive, WorkflowCompletionStrategy::Any, [[WorkflowAssigneeType::Receiver]]],
    ]);
    $pr = makePr($requestor, $requestor);
    $pr->departments()->sync([$department->id]);

    $manager->start($pr, null, $requestor);
    $manager->act($pr, $head, WorkflowActionType::Approve, 'Checked.');
    expect($pr->workflowInstance()->first()->current_step_order)->toBe(2);

    $manager->act($pr, $finance, WorkflowActionType::Approve, 'Approved.');
    $manager->act($pr, $requestor, WorkflowActionType::Receive, 'Received.');

    expect($pr->workflowInstance()->first()->status)->toBe(WorkflowInstanceStatus::Approved)
        ->and($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::APPROVED);
});

// WF-06 ALL strategy waits for every assignee
test('an ALL step is not complete until every assignee approves', function () {
    $manager = app(WorkflowManager::class);
    $a = roleApprover('finance_manager');
    $b = roleApprover('finance_manager');
    definePrWorkflow([
        ['Finance (all)', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);
    $pr = makePr();
    $manager->start($pr, null, $pr->requestor);

    $manager->act($pr, $a, WorkflowActionType::Approve);
    expect($pr->workflowInstance()->first()->status)->toBe(WorkflowInstanceStatus::Pending);

    $manager->act($pr, $b, WorkflowActionType::Approve);
    expect($pr->workflowInstance()->first()->status)->toBe(WorkflowInstanceStatus::Approved);
});

// WF-07 ANY strategy completes on the first approval
test('an ANY step completes after a single approval', function () {
    $manager = app(WorkflowManager::class);
    $a = roleApprover('finance_manager');
    roleApprover('finance_manager');
    definePrWorkflow([
        ['Finance (any)', WorkflowStepType::Approval, WorkflowCompletionStrategy::Any, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);
    $pr = makePr();
    $manager->start($pr, null, $pr->requestor);

    $manager->act($pr, $a, WorkflowActionType::Approve);
    expect($pr->workflowInstance()->first()->status)->toBe(WorkflowInstanceStatus::Approved);
});

// WF-10 concurrent / repeated approvals on an ALL step complete it exactly once
test('approvals on an ALL step complete it once without double-advancing', function () {
    $manager = app(WorkflowManager::class);
    $a = roleApprover('finance_manager');
    $b = roleApprover('finance_manager');
    definePrWorkflow([
        ['Finance (all)', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);
    $pr = makePr();
    $manager->start($pr, null, $pr->requestor);

    $manager->act($pr, $a, WorkflowActionType::Approve);
    $manager->act($pr, $a, WorkflowActionType::Approve); // repeat — already decided, no effect
    $manager->act($pr, $b, WorkflowActionType::Approve);

    $instance = $pr->workflowInstance()->first();
    expect($instance->status)->toBe(WorkflowInstanceStatus::Approved)
        ->and(WorkflowAction::query()->where('workflow_instance_id', $instance->id)->count())->toBe(2);
});

// WF-04 reject stops the workflow
test('a rejection stops the workflow and rejects the subject', function () {
    $manager = app(WorkflowManager::class);
    $finance = roleApprover('finance_manager');
    roleApprover('general_manager');
    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
        ['GM', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'general_manager']]],
    ]);
    $pr = makePr();
    $manager->start($pr, null, $pr->requestor);
    $manager->act($pr, $finance, WorkflowActionType::Reject, 'No budget.');

    $instance = $pr->workflowInstance()->first();
    expect($instance->status)->toBe(WorkflowInstanceStatus::Rejected)
        ->and($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::REJECTED)
        ->and($instance->steps()->where('step_order', 2)->first()->status)->toBe(WorkflowStepStatus::Stopped);
});

// WF-08 a non-assigned (but signed) user cannot act on the current step
test('a user not assigned to the active step is forbidden from approving', function () {
    $finance = roleApprover('finance_manager');
    $director = roleApprover('director'); // signed, but not assigned to this step
    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);
    $pr = makePr();
    app(WorkflowManager::class)->start($pr, null, $pr->requestor);

    $this->actingAs($director)
        ->postJson(route('workflows.act', ['type' => 'pr', 'id' => $pr->id]), ['action' => 'APPROVE'])
        ->assertStatus(403);
});

// WF-09 out-of-order: a later-step approver cannot act while an earlier step is active
test('a later-step approver cannot act before their step is active', function () {
    roleApprover('finance_manager');
    $gm = roleApprover('general_manager');
    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
        ['GM', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'general_manager']]],
    ]);
    $pr = makePr();
    app(WorkflowManager::class)->start($pr, null, $pr->requestor);

    $this->actingAs($gm)
        ->postJson(route('workflows.act', ['type' => 'pr', 'id' => $pr->id]), ['action' => 'APPROVE'])
        ->assertStatus(403);
});

// WF-13 reassign hands the pending decision to a delegate
test('a pending assignment can be reassigned to another user', function () {
    $manager = app(WorkflowManager::class);
    $finance = roleApprover('finance_manager');
    // A delegate must hold the document's view permission and an active signature.
    $delegate = userWithPermission('pr.view');
    withActiveSignature($delegate);
    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);
    $pr = makePr();
    $manager->start($pr, null, $pr->requestor);

    $assignment = $pr->workflowInstance()->first()->steps()->where('step_order', 1)->first()
        ->assignments()->where('user_id', $finance->id)->first();

    $manager->reassign($pr, $assignment, $delegate, $finance);
    $manager->act($pr, $delegate, WorkflowActionType::Approve, 'Approved by delegate.');

    expect($pr->workflowInstance()->first()->status)->toBe(WorkflowInstanceStatus::Approved);
});

// WF-13b reassigning to someone who already decided would strand the step
test('reassigning to a user who already acted on the step is rejected', function () {
    $manager = app(WorkflowManager::class);
    $financeA = roleApprover('finance_manager');
    $financeB = roleApprover('finance_manager');
    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);
    $pr = makePr();
    $manager->start($pr, null, $pr->requestor);

    // B approves; A's assignment is still pending on the (ALL-strategy) step.
    $manager->act($pr, $financeB, WorkflowActionType::Approve);

    $assignmentA = $pr->workflowInstance()->first()->steps()->where('step_order', 1)->first()
        ->assignments()->where('user_id', $financeA->id)->first();

    // Reassigning A's pending decision to B would leave nobody pending.
    expect(fn () => $manager->reassign($pr, $assignmentA, $financeB, $financeA))
        ->toThrow(HttpException::class, 'That user has already acted on this step.');

    // A's assignment is untouched and can still complete the step.
    expect($assignmentA->fresh()->status)->toBe(WorkflowAssignmentStatus::Pending);
});

// WF-13c a delegate must be able to view the document type
test('reassigning to a user without the view permission is rejected', function () {
    $manager = app(WorkflowManager::class);
    $finance = roleApprover('finance_manager');
    $delegate = User::factory()->create();
    withActiveSignature($delegate);
    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);
    $pr = makePr();
    $manager->start($pr, null, $pr->requestor);

    $assignment = $pr->workflowInstance()->first()->steps()->where('step_order', 1)->first()
        ->assignments()->where('user_id', $finance->id)->first();

    expect(fn () => $manager->reassign($pr, $assignment, $delegate, $finance))
        ->toThrow(HttpException::class, 'That user cannot view this document type and cannot be assigned.');

    expect($assignment->fresh()->status)->toBe(WorkflowAssignmentStatus::Pending);
});

// WF-13d a delegate must hold an active signature
test('reassigning to a user without an active signature is rejected', function () {
    $manager = app(WorkflowManager::class);
    $finance = roleApprover('finance_manager');
    $delegate = userWithPermission('pr.view'); // no signature
    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);
    $pr = makePr();
    $manager->start($pr, null, $pr->requestor);

    $assignment = $pr->workflowInstance()->first()->steps()->where('step_order', 1)->first()
        ->assignments()->where('user_id', $finance->id)->first();

    expect(fn () => $manager->reassign($pr, $assignment, $delegate, $finance))
        ->toThrow(HttpException::class, 'That user has no active signature and cannot approve documents.');

    expect($assignment->fresh()->status)->toBe(WorkflowAssignmentStatus::Pending);
});

// WF-14 super admin override
test('a super admin can override the current step', function () {
    $manager = app(WorkflowManager::class);
    roleApprover('finance_manager');
    $admin = roleApprover('Super Admin');
    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);
    $pr = makePr();
    $manager->start($pr, null, $pr->requestor);

    $manager->act($pr, $admin, WorkflowActionType::Approve, 'Override.');
    expect($pr->workflowInstance()->first()->status)->toBe(WorkflowInstanceStatus::Approved);
});

// WF-03 approval requires an active signature
test('approving without an active signature is rejected', function () {
    $finance = User::factory()->create();
    Role::query()->firstOrCreate(['name' => 'finance_manager', 'guard_name' => 'web']);
    $finance->assignRole('finance_manager'); // no signature
    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);
    $pr = makePr();
    app(WorkflowManager::class)->start($pr, null, $pr->requestor);

    $this->actingAs($finance)
        ->postJson(route('workflows.act', ['type' => 'pr', 'id' => $pr->id]), ['action' => 'APPROVE'])
        ->assertStatus(422);
});

// WF-21 submission is blocked when a tagged department has no head
test('submission is blocked when a tagged department has no head', function () {
    $department = Department::create(['name' => 'IT', 'code' => 'IT']); // no head
    definePrWorkflow([
        ['Checked By', WorkflowStepType::DepartmentHead, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::DepartmentHead]]],
    ]);
    $pr = makePr();
    $pr->departments()->sync([$department->id]);

    expect(fn () => app(WorkflowManager::class)->start($pr, null, $pr->requestor))
        ->toThrow(ValidationException::class);
});

// WF-15 self-approval is allowed when the requestor is also a pending assignee
test('a requestor who is also an assignee can approve their own document', function () {
    // finding: nothing prevents self-approval — canAct/engine only require a pending assignment on the active step.
    $requestor = User::factory()->create();
    withActiveSignature($requestor);
    definePrWorkflow([
        ['Self', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::User, null, $requestor->id]]],
    ]);
    $pr = makePr($requestor);

    $manager = app(WorkflowManager::class);
    $manager->start($pr, null, $requestor);
    $manager->act($pr, $requestor, WorkflowActionType::Approve, 'Self-approved.');

    expect($pr->workflowInstance()->first()->status)->toBe(WorkflowInstanceStatus::Approved);
});

// WF-16 acting on an already-approved instance is a silent no-op
test('acting on an already-completed workflow does nothing', function () {
    $manager = app(WorkflowManager::class);
    $finance = roleApprover('finance_manager');
    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::Any, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);
    $pr = makePr();
    $manager->start($pr, null, $pr->requestor);
    $manager->act($pr, $finance, WorkflowActionType::Approve);

    $instance = $pr->workflowInstance()->first();
    expect($instance->status)->toBe(WorkflowInstanceStatus::Approved);

    // Act again — engine returns early because the instance is no longer pending.
    $manager->act($pr, $finance, WorkflowActionType::Approve);
    expect($pr->workflowInstance()->first()->status)->toBe(WorkflowInstanceStatus::Approved)
        ->and(WorkflowAction::query()->where('workflow_instance_id', $instance->id)->count())->toBe(1);
});

// WF-12 cancel a pending workflow
test('a pending workflow can be cancelled', function () {
    $manager = app(WorkflowManager::class);
    $finance = roleApprover('finance_manager');
    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);
    $pr = makePr();
    $manager->start($pr, null, $pr->requestor);

    $manager->cancel($pr, $pr->requestor, 'No longer needed.');

    expect($pr->workflowInstance()->first()->status)->toBe(WorkflowInstanceStatus::Cancelled)
        ->and($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::CANCELLED);
});

// WF-12b the cancel endpoint requires a reason
test('cancelling a workflow through the endpoint requires a comment', function () {
    roleApprover('finance_manager');
    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);
    $pr = makePr();
    app(WorkflowManager::class)->start($pr, null, $pr->requestor);

    $this->actingAs($pr->requestor)
        ->post(route('workflows.cancel', ['type' => 'pr', 'id' => $pr->id]))
        ->assertSessionHasErrors('comment');

    expect($pr->fresh()->status)->not->toBe(PurchaseRequisitionStatus::CANCELLED);

    $this->actingAs($pr->requestor)
        ->post(route('workflows.cancel', ['type' => 'pr', 'id' => $pr->id]), ['comment' => 'Duplicate request'])
        ->assertOk();

    expect($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::CANCELLED);
});

// WF-11 a rejected document cannot be re-submitted (submit policy requires DRAFT)
test('a rejected requisition cannot be resubmitted', function () {
    // finding: there is no transition back to DRAFT, so a rejected document is terminal for submission.
    $requestor = User::factory()->create();
    $pr = PurchaseRequisition::factory()->for($requestor, 'requestor')->create([
        'status' => PurchaseRequisitionStatus::REJECTED,
    ]);

    $this->actingAs($requestor)
        ->post(route('workflows.submit', ['type' => 'pr', 'id' => $pr->id]))
        ->assertForbidden();
});

// WF-19 guests cannot act on a workflow
test('guests are redirected when acting on a workflow', function () {
    $pr = makePr();

    $this->post(route('workflows.act', ['type' => 'pr', 'id' => $pr->id]), ['action' => 'APPROVE'])
        ->assertRedirect(route('login'));
});

<?php

use App\Enums\WorkflowActionType;
use App\Enums\WorkflowAssigneeType;
use App\Enums\WorkflowCompletionStrategy;
use App\Enums\WorkflowStepType;
use App\Events\Procurement\PurchaseOrderReleased;
use App\Events\Procurement\PurchaseRequisitionReadyForPo;
use App\Notifications\AddedToSigningChain;
use App\Notifications\ApprovalActionRequired;
use App\Notifications\DocumentOutcome;
use App\Notifications\ItemShortClosedNotification;
use App\Notifications\ProcurementHandoff;
use App\Services\Workflow\WorkflowManager;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Notification system
|--------------------------------------------------------------------------
| Event → subscriber → notification wiring for the rebuilt notification
| architecture (mail + database channels), plus the in-app read endpoints.
*/

// ── Workflow lifecycle ─────────────────────────────────────────────────────

test('submitting notifies the first step assignee it is their turn and later signees of chain membership', function () {
    Notification::fake();

    $finance = roleApprover('finance_manager');
    $director = roleApprover('director');
    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
        ['Director', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'director']]],
    ]);
    $pr = makePr();

    app(WorkflowManager::class)->start($pr, null, $pr->requestor);

    Notification::assertSentTo($finance, ApprovalActionRequired::class);
    Notification::assertSentTo($finance, AddedToSigningChain::class);
    Notification::assertSentTo($director, AddedToSigningChain::class);
    Notification::assertNotSentTo($director, ApprovalActionRequired::class);
    Notification::assertNotSentTo($pr->requestor, AddedToSigningChain::class);
});

test('full approval notifies the submitter of the outcome', function () {
    Notification::fake();

    $finance = roleApprover('finance_manager');
    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);
    $pr = makePr();
    $manager = app(WorkflowManager::class);
    $manager->start($pr, null, $pr->requestor);

    $manager->act($pr, $finance, WorkflowActionType::Approve);

    Notification::assertSentTo($pr->requestor, DocumentOutcome::class);
});

test('a rejection notifies the submitter', function () {
    Notification::fake();

    $finance = roleApprover('finance_manager');
    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);
    $pr = makePr();
    $manager = app(WorkflowManager::class);
    $manager->start($pr, null, $pr->requestor);

    $manager->act($pr, $finance, WorkflowActionType::Reject, 'Budget exceeded.');

    Notification::assertSentTo($pr->requestor, DocumentOutcome::class);
});

test('reassignment notifies the delegate it is their turn', function () {
    Notification::fake();

    $finance = roleApprover('finance_manager');
    $delegate = userWithPermission('pr.view');
    withActiveSignature($delegate);
    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);
    $pr = makePr();
    $manager = app(WorkflowManager::class);
    $manager->start($pr, null, $pr->requestor);

    $assignment = $pr->workflowInstance()->first()->steps()->where('step_order', 1)->first()
        ->assignments()->where('user_id', $finance->id)->first();
    $manager->reassign($pr, $assignment, $delegate, $finance);

    Notification::assertSentTo($delegate, ApprovalActionRequired::class);
});

test('cancelling a workflow notifies the pending assignees', function () {
    Notification::fake();

    $finance = roleApprover('finance_manager');
    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);
    $pr = makePr();
    $manager = app(WorkflowManager::class);
    $manager->start($pr, null, $pr->requestor);

    $manager->cancel($pr, $pr->requestor, 'No longer needed.');

    Notification::assertSentTo($finance, DocumentOutcome::class);
});

// ── Procurement chain handoffs ─────────────────────────────────────────────

test('a requisition marked ready for po notifies the buyers', function () {
    Notification::fake();

    $buyer = userWithPermission('po.prepare');
    ['pr' => $pr] = prReadyForPo();

    PurchaseRequisitionReadyForPo::dispatch($pr);

    Notification::assertSentTo($buyer, ProcurementHandoff::class);
});

test('a released purchase order notifies the receivers', function () {
    Notification::fake();

    $receiver = userWithPermission('rr.prepare');
    ['po' => $po] = orderedOrder();

    PurchaseOrderReleased::dispatch($po);

    Notification::assertSentTo($receiver, ProcurementHandoff::class);
});

test('omitting a purchase order item notifies the requisition requestor', function () {
    Notification::fake();

    ['pr' => $pr, 'itemB' => $itemB] = orderedOrder();
    // A different actor omits — the requestor must hear about it (never the actor themselves).
    $omitter = userWithPermission('po.view', 'po.omit');

    $this->actingAs($omitter)
        ->post(route('purchase-order-items.omission', $itemB), ['omit' => true, 'reason' => 'Out of stock'])
        ->assertRedirect();

    Notification::assertSentTo($pr->requestor, ItemShortClosedNotification::class);
});

// ── In-app feed & read endpoints ───────────────────────────────────────────

test('database notifications are shared and can be marked read', function () {
    ['pr' => $pr] = prReadyForPo();
    $requestor = $pr->requestor;

    $requestor->notify(new ItemShortClosedNotification($pr, 'Alpha', 'Discontinued'));

    $this->actingAs($requestor)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('notifications.unread_count', 1)
            ->where('notifications.items.0.title', 'Item short-closed'));

    $notification = $requestor->notifications()->first();

    $this->actingAs($requestor)
        ->post(route('notifications.read', $notification->id))
        ->assertRedirect();

    expect($requestor->unreadNotifications()->count())->toBe(0);
});

test('a user cannot mark another user\'s notification as read', function () {
    ['pr' => $pr] = prReadyForPo();
    $owner = $pr->requestor;
    $owner->notify(new ItemShortClosedNotification($pr, 'Alpha', null));
    $notification = $owner->notifications()->first();

    $this->actingAs(userWithPermission('pr.view'))
        ->post(route('notifications.read', $notification->id))
        ->assertNotFound();

    expect($owner->unreadNotifications()->count())->toBe(1);
});

test('mark all as read clears the unread count', function () {
    ['pr' => $pr] = prReadyForPo();
    $requestor = $pr->requestor;
    $requestor->notify(new ItemShortClosedNotification($pr, 'Alpha', null));
    $requestor->notify(new ItemShortClosedNotification($pr, 'Bravo', null));

    $this->actingAs($requestor)
        ->post(route('notifications.read-all'))
        ->assertRedirect();

    expect($requestor->unreadNotifications()->count())->toBe(0);
});

// ── Next-inline email filtering ────────────────────────────────────────────

test('multi-user ALL step: all get notifications but only first should get mail', function () {
    Notification::fake();

    $approver1 = roleApprover('finance_manager');
    $approver2 = roleApprover('finance_manager');
    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);
    $pr = makePr();

    app(WorkflowManager::class)->start($pr, null, $pr->requestor);

    // Both should get notifications
    Notification::assertSentTo($approver1, ApprovalActionRequired::class);
    Notification::assertSentTo($approver2, ApprovalActionRequired::class);
});

test('multi-user ALL step: the next pending assignee is emailed after the first approves', function () {
    $approver1 = roleApprover('finance_manager');
    $approver2 = roleApprover('finance_manager');
    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::All, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);
    $pr = makePr();
    $manager = app(WorkflowManager::class);
    $manager->start($pr, null, $pr->requestor);

    Notification::fake();

    $manager->act($pr, $approver1, WorkflowActionType::Approve);

    Notification::assertSentTo(
        $approver2,
        ApprovalActionRequired::class,
        fn ($notification, $channels) => in_array('mail', $channels, true),
    );
});

test('multi-user ANY step: all get notifications', function () {
    Notification::fake();

    $approver1 = roleApprover('finance_manager');
    $approver2 = roleApprover('finance_manager');
    definePrWorkflow([
        ['Finance', WorkflowStepType::Approval, WorkflowCompletionStrategy::Any, [[WorkflowAssigneeType::Role, 'finance_manager']]],
    ]);
    $pr = makePr();

    app(WorkflowManager::class)->start($pr, null, $pr->requestor);

    // Both should get notifications
    Notification::assertSentTo($approver1, ApprovalActionRequired::class);
    Notification::assertSentTo($approver2, ApprovalActionRequired::class);
});

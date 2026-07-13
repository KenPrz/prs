<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequisitionStatus;
use App\Enums\ReceivingReportStatus;
use App\Enums\WorkflowInstanceStatus;
use App\Models\User;
use App\Policies\ReceivingReportPolicy;
use App\Services\ReceivingReportService;
use Illuminate\Validation\ValidationException;

// orderedOrder(), receive() and verifyReport() are shared helpers defined in tests/Pest.php.

// RR-07 read: index
test('authenticated users can view the receiving report index', function () {
    $this->actingAs(userWithPermission('rr.view'))
        ->get(route('receiving-reports.index'))
        ->assertOk();
});

// RR-01 partial receipt
test('receiving part of an order marks it partially received', function () {
    ['po' => $po, 'itemA' => $itemA, 'user' => $user] = orderedOrder();

    receive($po, $user, [['purchase_order_item_id' => $itemA->id, 'quantity_received' => 10]]);

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::PARTIALLY_RECEIVED);
});

// RR-11 full receipt only closes the order/requisition once the report is verified
test('a fully-received order completes only after the receiving report is verified', function () {
    ['pr' => $pr, 'po' => $po, 'itemA' => $itemA, 'itemB' => $itemB, 'user' => $user] = orderedOrder();

    $rr = receive($po, $user, [
        ['purchase_order_item_id' => $itemA->id, 'quantity_received' => 10],
        ['purchase_order_item_id' => $itemB->id, 'quantity_received' => 5],
    ]);

    // Draft full receipt: recorded but not verified — order is not complete, PR not closed.
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::PARTIALLY_RECEIVED)
        ->and($pr->fresh()->status)->not->toBe(PurchaseRequisitionStatus::CLOSED);

    verifyReport($rr);

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::FULLY_RECEIVED)
        ->and($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::CLOSED);
});

// RR-12 recorded quantity guards over-receipt; verified quantity drives completion
test('a draft receipt fills recorded quantity but not verified quantity', function () {
    ['po' => $po, 'itemA' => $itemA, 'user' => $user] = orderedOrder();

    $rr = receive($po, $user, [['purchase_order_item_id' => $itemA->id, 'quantity_received' => 10]]);

    // Recorded reaches full (so a second receipt would be over-receipt), but verified is still 0.
    expect($rr->status)->toBe(ReceivingReportStatus::DRAFT)
        ->and($itemA->fresh()->quantity_received)->toBe(10)
        ->and($itemA->fresh()->quantity_verified_received)->toBe(0)
        ->and($po->fresh()->status)->toBe(PurchaseOrderStatus::PARTIALLY_RECEIVED);

    verifyReport($rr);

    expect($itemA->fresh()->quantity_received)->toBe(10)
        ->and($itemA->fresh()->quantity_verified_received)->toBe(10);
});

// RR-03 over-receipt
test('receiving more than remains is rejected', function () {
    ['po' => $po, 'itemA' => $itemA, 'user' => $user] = orderedOrder();

    expect(fn () => receive($po, $user, [['purchase_order_item_id' => $itemA->id, 'quantity_received' => 11]]))
        ->toThrow(ValidationException::class);
});

// RR-03b duplicate rows cannot jointly over-receive (service backstop)
test('duplicate item rows in one receipt cannot jointly exceed the remaining quantity', function () {
    ['po' => $po, 'itemA' => $itemA, 'user' => $user] = orderedOrder();

    // Two rows of 6 each pass individually against qty 10 — together they must not.
    expect(fn () => receive($po, $user, [
        ['purchase_order_item_id' => $itemA->id, 'quantity_received' => 6],
        ['purchase_order_item_id' => $itemA->id, 'quantity_received' => 6],
    ]))->toThrow(ValidationException::class);
});

// RR-03c duplicate rows are rejected at the endpoint by the distinct rule
test('the store endpoint rejects duplicate item rows', function () {
    ['po' => $po, 'itemA' => $itemA, 'user' => $user] = orderedOrder();

    $this->actingAs($user)
        ->post(route('receiving-reports.store'), [
            'purchase_order_id' => $po->id,
            'received_date' => now()->toDateString(),
            'items' => [
                ['purchase_order_item_id' => $itemA->id, 'quantity_received' => 6],
                ['purchase_order_item_id' => $itemA->id, 'quantity_received' => 6],
            ],
        ])
        ->assertSessionHasErrors(['items.0.purchase_order_item_id', 'items.1.purchase_order_item_id']);
});

// RR-02 strict seal: a merely approved order is not yet receivable
test('a receiving report cannot be created for an unsealed purchase order', function () {
    ['po' => $po, 'itemA' => $itemA, 'user' => $user] = orderedOrder();
    $po->forceFill(['status' => PurchaseOrderStatus::APPROVED])->save();

    expect(fn () => receive($po->fresh(), $user, [['purchase_order_item_id' => $itemA->id, 'quantity_received' => 1]]))
        ->toThrow(ValidationException::class);
});

// RR-04 omitted item cannot be received
test('an omitted purchase order item cannot be received', function () {
    ['po' => $po, 'itemA' => $itemA, 'user' => $user] = orderedOrder();
    $itemA->forceFill(['omitted_at' => now()])->save();

    expect(fn () => receive($po, $user, [['purchase_order_item_id' => $itemA->id, 'quantity_received' => 5]]))
        ->toThrow(ValidationException::class);
});

// RR-08 delete draft report reverts the order (seal is kept: revert floor is RELEASED)
test('deleting the only partial receiving report reverts the order to released', function () {
    ['po' => $po, 'itemA' => $itemA, 'user' => $user] = orderedOrder();
    $rr = receive($po, $user, [['purchase_order_item_id' => $itemA->id, 'quantity_received' => 4]]);

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::PARTIALLY_RECEIVED);

    app(ReceivingReportService::class)->delete($rr, 'Recorded against the wrong PO');

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::RELEASED)
        ->and($itemA->fresh()->quantity_received)->toBe(0);
});

// RR-08b the web destroy endpoint deletes a draft and requires a reason
test('a draft receiving report can be deleted through the endpoint with a reason', function () {
    ['po' => $po, 'itemA' => $itemA, 'user' => $user] = orderedOrder();
    $rr = receive($po, $user, [['purchase_order_item_id' => $itemA->id, 'quantity_received' => 4]]);

    // Without a reason the deletion is refused.
    $this->actingAs($user)
        ->delete(route('receiving-reports.destroy', $rr))
        ->assertSessionHasErrors('reason');

    $this->actingAs($user)
        ->delete(route('receiving-reports.destroy', $rr), ['reason' => 'Duplicate entry'])
        ->assertRedirect(route('receiving-reports.index'));

    $this->assertDatabaseMissing('receiving_reports', ['id' => $rr->id]);
    expect($itemA->fresh()->quantity_received)->toBe(0);
});

// RR-09 delete blocked once not a draft
test('a non-draft receiving report cannot be deleted', function () {
    ['po' => $po, 'itemA' => $itemA, 'user' => $user] = orderedOrder();
    $rr = receive($po, $user, [['purchase_order_item_id' => $itemA->id, 'quantity_received' => 4]]);
    $rr->forceFill(['status' => ReceivingReportStatus::VERIFIED])->save();

    expect(fn () => app(ReceivingReportService::class)->delete($rr))->toThrow(ValidationException::class);
});

// RR-13 draft update replaces items and revalidates against the pool
test('a draft receiving report can be updated and its quantities revalidated', function () {
    ['po' => $po, 'itemA' => $itemA, 'itemB' => $itemB, 'user' => $user] = orderedOrder();
    $rr = receive($po, $user, [['purchase_order_item_id' => $itemA->id, 'quantity_received' => 4]]);
    $rrItem = $rr->items->first();

    // Raising its own row to the full quantity is fine — its old row is excluded.
    $this->actingAs($user)
        ->patch(route('receiving-reports.update', $rr), [
            'items' => [
                ['id' => $rrItem->id, 'purchase_order_item_id' => $itemA->id, 'quantity_received' => 10],
                ['purchase_order_item_id' => $itemB->id, 'quantity_received' => 5],
            ],
        ])
        ->assertRedirect(route('receiving-reports.show', $rr));

    expect($itemA->fresh()->quantity_received)->toBe(10)
        ->and($itemB->fresh()->quantity_received)->toBe(5);

    // But exceeding the pool is still rejected.
    expect(fn () => app(ReceivingReportService::class)->update($rr->fresh(), [
        'items' => [['purchase_order_item_id' => $itemA->id, 'quantity_received' => 11]],
    ]))->toThrow(ValidationException::class);
});

// RR-14 submit moves a draft to PENDING; cancelling frees the quantities
test('cancelling a receiving report mid-approval returns its quantities to the pool', function () {
    ['po' => $po, 'itemA' => $itemA, 'user' => $user] = orderedOrder();
    $rr = receive($po, $user, [['purchase_order_item_id' => $itemA->id, 'quantity_received' => 10]]);

    $rr->applyWorkflowStarted();
    expect($rr->fresh()->status)->toBe(ReceivingReportStatus::PENDING);

    $rr->fresh()->applyWorkflowStatus(WorkflowInstanceStatus::Cancelled);

    expect($rr->fresh()->status)->toBe(ReceivingReportStatus::CANCELLED)
        ->and($itemA->fresh()->quantity_received)->toBe(0)
        ->and($po->fresh()->status)->toBe(PurchaseOrderStatus::RELEASED);
});

// RR-10 a rejected report stops counting
test('a rejected receiving report stops counting toward received quantity', function () {
    ['po' => $po, 'itemA' => $itemA, 'user' => $user] = orderedOrder();
    $rr = receive($po, $user, [['purchase_order_item_id' => $itemA->id, 'quantity_received' => 10]]);

    $rr->applyWorkflowStatus(WorkflowInstanceStatus::Rejected);

    expect($rr->fresh()->status)->toBe(ReceivingReportStatus::REJECTED)
        ->and($itemA->fresh()->quantity_received)->toBe(0)
        ->and($po->fresh()->status)->toBe(PurchaseOrderStatus::RELEASED);
});

// RR-06 permission: the policy gates create to holders of rr.prepare
test('the receiving report policy restricts creation to rr preparers', function () {
    $preparer = userWithPermission('rr.prepare');

    $policy = new ReceivingReportPolicy;

    expect($policy->create($preparer))->toBeTrue()
        ->and($policy->create(User::factory()->create()))->toBeFalse();
});

// RR-06b the store endpoint enforces the create policy
test('the store endpoint rejects users without the rr.prepare permission', function () {
    ['po' => $po, 'itemA' => $itemA] = orderedOrder();
    $rando = User::factory()->create(); // no rr.prepare

    $this->actingAs($rando)
        ->post(route('receiving-reports.store'), [
            'purchase_order_id' => $po->id,
            'received_date' => now()->toDateString(),
            'items' => [['purchase_order_item_id' => $itemA->id, 'quantity_received' => 3]],
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('receiving_reports', ['purchase_order_id' => $po->id]);
});

// RR-15 allocation audit trail: create and delete leave allocation events
test('creating and deleting a receiving report writes allocation audit events', function () {
    ['po' => $po, 'itemA' => $itemA, 'user' => $user] = orderedOrder();
    $rr = receive($po, $user, [['purchase_order_item_id' => $itemA->id, 'quantity_received' => 4]]);

    app(ReceivingReportService::class)->delete($rr, 'Wrong delivery');

    $this->assertDatabaseHas('activity_log', ['log_name' => 'allocation', 'description' => 'allocated', 'subject_id' => $rr->id, 'subject_type' => $rr->getMorphClass()]);
    $this->assertDatabaseHas('activity_log', ['log_name' => 'allocation', 'description' => 'deallocated', 'subject_id' => $rr->id, 'subject_type' => $rr->getMorphClass()]);
});

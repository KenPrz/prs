<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequisitionStatus;
use App\Enums\WorkflowInstanceStatus;
use App\Models\PurchaseOrder;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| Item accounting lifecycle
|--------------------------------------------------------------------------
| End-to-end accounting across PR → PO → RR → verify. The per-document CRUD
| and validation rules live in the PurchaseOrder/ReceivingReport suites; this
| file asserts the *quantity bookkeeping* (allocated / unallocated / received /
| verified-received / remaining) and the cross-document status cascade,
| including the rule that a PO is only FULLY_RECEIVED once its receipts are
| verified. Fixture (prReadyForPo): Alpha qty 10, Bravo qty 5.
|
| Shared helpers (prReadyForPo, makeOrder, receive, verifyReport) live in
| tests/Pest.php.
*/

/**
 * Push a draft PO through approval (→ APPROVED), re-sync its requisition, then
 * seal it as ordered (→ RELEASED) so receiving reports can be created against it.
 */
function approveOrder(PurchaseOrder $po): void
{
    $po->applyWorkflowStatus(WorkflowInstanceStatus::Approved);
    sealOrder($po);
}

// ACC-01 the full happy path: allocate → order → receive → verify → closed
test('a fully ordered and verified-received requisition closes with no remainder', function () {
    ['pr' => $pr, 'a' => $a, 'b' => $b, 'unit' => $unit, 'supplier' => $supplier, 'user' => $user] = prReadyForPo();

    $po = makeOrder($pr, $supplier, [
        ['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100],
        ['line_item_id' => $b->id, 'quantity' => 5, 'unit_id' => $unit->id, 'price' => 50],
    ]);

    // Full allocation: nothing left to order.
    expect($a->fresh()->quantity_allocated)->toBe(10)
        ->and($a->fresh()->quantity_unallocated)->toBe(0)
        ->and($b->fresh()->quantity_unallocated)->toBe(0);

    approveOrder($po);
    expect($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::FULLY_ORDERED);

    $itemA = $po->items->firstWhere('line_item_id', $a->id);
    $itemB = $po->items->firstWhere('line_item_id', $b->id);

    $rr = receive($po, $user, [
        ['purchase_order_item_id' => $itemA->id, 'quantity_received' => 10],
        ['purchase_order_item_id' => $itemB->id, 'quantity_received' => 5],
    ]);

    // Recorded but unverified → not complete.
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::PARTIALLY_RECEIVED)
        ->and($pr->fresh()->status)->not->toBe(PurchaseRequisitionStatus::CLOSED);

    verifyReport($rr);

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::FULLY_RECEIVED)
        ->and($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::CLOSED)
        ->and($itemA->fresh()->quantity_remaining)->toBe(0)
        ->and($itemB->fresh()->quantity_remaining)->toBe(0);
});

// ACC-02 one line split across two purchase orders
test('a line item split across two purchase orders allocates fully', function () {
    ['pr' => $pr, 'a' => $a, 'b' => $b, 'unit' => $unit, 'supplier' => $supplier] = prReadyForPo();

    $po1 = makeOrder($pr, $supplier, [
        ['line_item_id' => $a->id, 'quantity' => 6, 'unit_id' => $unit->id, 'price' => 100],
    ]);

    expect($a->fresh()->quantity_allocated)->toBe(6)
        ->and($a->fresh()->quantity_unallocated)->toBe(4)
        ->and($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::PARTIALLY_ORDERED);

    $po2 = makeOrder($pr->fresh(), $supplier, [
        ['line_item_id' => $a->id, 'quantity' => 4, 'unit_id' => $unit->id, 'price' => 100],
        ['line_item_id' => $b->id, 'quantity' => 5, 'unit_id' => $unit->id, 'price' => 50],
    ]);

    expect($a->fresh()->quantity_allocated)->toBe(10)
        ->and($a->fresh()->quantity_unallocated)->toBe(0)
        ->and($b->fresh()->quantity_unallocated)->toBe(0);

    approveOrder($po1);
    approveOrder($po2);

    expect($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::FULLY_ORDERED);
});

// ACC-03 one PO item received over several reports
test('a purchase order item received across multiple reports completes only when all are verified', function () {
    ['pr' => $pr, 'a' => $a, 'unit' => $unit, 'supplier' => $supplier, 'user' => $user] = prReadyForPo();

    $po = makeOrder($pr, $supplier, [
        ['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100],
    ]);
    approveOrder($po);
    $itemA = $po->items->firstWhere('line_item_id', $a->id);

    $rr1 = receive($po, $user, [['purchase_order_item_id' => $itemA->id, 'quantity_received' => 4]]);
    verifyReport($rr1);

    expect($itemA->fresh()->quantity_received)->toBe(4)
        ->and($itemA->fresh()->quantity_verified_received)->toBe(4)
        ->and($po->fresh()->status)->toBe(PurchaseOrderStatus::PARTIALLY_RECEIVED);

    $rr2 = receive($po, $user, [['purchase_order_item_id' => $itemA->id, 'quantity_received' => 6]]);

    // Recorded reaches full, but the second report is still pending → not complete.
    expect($itemA->fresh()->quantity_received)->toBe(10)
        ->and($itemA->fresh()->quantity_verified_received)->toBe(4)
        ->and($po->fresh()->status)->toBe(PurchaseOrderStatus::PARTIALLY_RECEIVED);

    verifyReport($rr2);

    expect($itemA->fresh()->quantity_verified_received)->toBe(10)
        ->and($itemA->fresh()->quantity_remaining)->toBe(0)
        ->and($po->fresh()->status)->toBe(PurchaseOrderStatus::FULLY_RECEIVED);
});

// ACC-04 a rejected report releases everything it recorded
test('rejecting a receiving report releases its recorded quantity', function () {
    ['pr' => $pr, 'a' => $a, 'unit' => $unit, 'supplier' => $supplier, 'user' => $user] = prReadyForPo();

    $po = makeOrder($pr, $supplier, [
        ['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100],
    ]);
    approveOrder($po);
    $itemA = $po->items->firstWhere('line_item_id', $a->id);

    $rr = receive($po, $user, [['purchase_order_item_id' => $itemA->id, 'quantity_received' => 10]]);
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::PARTIALLY_RECEIVED);

    $rr->applyWorkflowStatus(WorkflowInstanceStatus::Rejected);

    expect($itemA->fresh()->quantity_received)->toBe(0)
        ->and($itemA->fresh()->quantity_verified_received)->toBe(0)
        ->and($itemA->fresh()->quantity_remaining)->toBe(10)
        ->and($po->fresh()->status)->toBe(PurchaseOrderStatus::RELEASED);
});

// ACC-05 a partially-rejected delivery leaves the shortfall on the order
test('a delivery that rejects some units leaves the shortfall remaining', function () {
    ['pr' => $pr, 'a' => $a, 'unit' => $unit, 'supplier' => $supplier, 'user' => $user] = prReadyForPo();

    $po = makeOrder($pr, $supplier, [
        ['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100],
    ]);
    approveOrder($po);
    $itemA = $po->items->firstWhere('line_item_id', $a->id);

    $rr = receive($po, $user, [
        ['purchase_order_item_id' => $itemA->id, 'quantity_received' => 8, 'quantity_rejected' => 2],
    ]);
    verifyReport($rr);

    // Only accepted units count; the order is verified but not complete.
    expect($itemA->fresh()->quantity_received)->toBe(8)
        ->and($itemA->fresh()->quantity_verified_received)->toBe(8)
        ->and($itemA->fresh()->quantity_remaining)->toBe(2)
        ->and($po->fresh()->status)->toBe(PurchaseOrderStatus::PARTIALLY_RECEIVED);
});

// ACC-06 omitting a PO item short-closes it out of completion and frees its allocation
test('an omitted purchase order item is excluded from full receipt', function () {
    ['pr' => $pr, 'a' => $a, 'b' => $b, 'unit' => $unit, 'supplier' => $supplier, 'user' => $user] = prReadyForPo();

    $po = makeOrder($pr, $supplier, [
        ['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100],
        ['line_item_id' => $b->id, 'quantity' => 5, 'unit_id' => $unit->id, 'price' => 50],
    ]);
    approveOrder($po);

    $itemA = $po->items->firstWhere('line_item_id', $a->id);
    $itemB = $po->items->firstWhere('line_item_id', $b->id);
    $itemB->forceFill(['omitted_at' => now()])->save();

    $rr = receive($po, $user, [['purchase_order_item_id' => $itemA->id, 'quantity_received' => 10]]);
    verifyReport($rr);

    // Bravo never received, but it is short-closed → the order still completes.
    // Its allocation returns to the requisition, which therefore stays open.
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::FULLY_RECEIVED)
        ->and($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::PARTIALLY_ORDERED)
        ->and($b->fresh()->quantity_unallocated)->toBe(5);
});

// ACC-10 the allocation give-back on PO-item omission (the other half of ACC-06)
test('omitting a purchase order item releases its allocation back to the requisition', function () {
    ['pr' => $pr, 'po' => $po, 'itemB' => $itemB, 'user' => $user] = orderedOrder();
    $b = $itemB->lineItem;

    $this->actingAs($user)
        ->post(route('purchase-order-items.omission', $itemB), ['omit' => true, 'reason' => 'Supplier stockout'])
        ->assertRedirect();

    // The 5 Bravo units are re-orderable and the PR reopens.
    expect($b->fresh()->quantity_allocated)->toBe(0)
        ->and($b->fresh()->quantity_unallocated)->toBe(5)
        ->and($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::PARTIALLY_ORDERED);

    // A second PO can now order Bravo without over-allocating.
    $po2 = makeOrder($pr->fresh(), $itemB->purchaseOrder->supplier, [
        ['line_item_id' => $b->id, 'quantity' => 5, 'unit_id' => $b->unit_id, 'price' => 50],
    ]);

    expect($b->fresh()->quantity_allocated)->toBe(5)
        ->and($po2->items()->count())->toBe(1);
});

// ACC-11 restoring an omitted PO item re-reserves the quantity and reopens the order
test('restoring an omitted purchase order item reopens the order and requisition', function () {
    ['pr' => $pr, 'po' => $po, 'itemA' => $itemA, 'itemB' => $itemB, 'user' => $user] = orderedOrder();

    $this->actingAs($user)
        ->post(route('purchase-order-items.omission', $itemB), ['omit' => true])
        ->assertRedirect();

    $rr = receive($po->fresh(), $user, [['purchase_order_item_id' => $itemA->id, 'quantity_received' => 10]]);
    verifyReport($rr);
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::FULLY_RECEIVED);

    $this->actingAs($user)
        ->post(route('purchase-order-items.omission', $itemB), ['omit' => false])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    // Bravo is expected again: the order reopens and the PR is fully ordered.
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::PARTIALLY_RECEIVED)
        ->and($itemB->fresh()->is_omitted)->toBeFalse()
        ->and($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::FULLY_ORDERED);
});

// ACC-12 restore is rejected when the freed quantity was re-ordered meanwhile
test('restoring an omitted purchase order item is rejected when the freed quantity was re-ordered', function () {
    ['pr' => $pr, 'itemB' => $itemB, 'user' => $user] = orderedOrder();
    $b = $itemB->lineItem;

    $this->actingAs($user)
        ->post(route('purchase-order-items.omission', $itemB), ['omit' => true])
        ->assertRedirect();

    // The freed 5 Bravo units get ordered on a second PO.
    makeOrder($pr->fresh(), $itemB->purchaseOrder->supplier, [
        ['line_item_id' => $b->id, 'quantity' => 5, 'unit_id' => $b->unit_id, 'price' => 50],
    ]);

    $this->actingAs($user)
        ->post(route('purchase-order-items.omission', $itemB), ['omit' => false])
        ->assertSessionHasErrors('omit');

    // No over-allocation: the item stays omitted and Bravo stays fully booked.
    expect($itemB->fresh()->is_omitted)->toBeTrue()
        ->and($b->fresh()->quantity_allocated)->toBe(5);
});

// ACC-13 restoring a PO item on an omitted line re-reserves quantity consistently
// (the line's waived requested total tracks its allocations, so this stays sound)
test('restoring a purchase order item works when its requisition line is omitted', function () {
    ['pr' => $pr, 'itemB' => $itemB, 'user' => $user] = orderedOrder();
    $b = $itemB->lineItem;

    $this->actingAs($user)
        ->post(route('purchase-order-items.omission', $itemB), ['omit' => true])
        ->assertRedirect();

    $b->forceFill(['omitted_at' => now()])->save();

    $this->actingAs($user)
        ->post(route('purchase-order-items.omission', $itemB), ['omit' => false])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    // The item is expected again; the omitted line counts it as both requested
    // (waived remainder excluded) and allocated — accounting stays balanced.
    // The fixture PO is already RELEASED, so full allocation reads as ordered.
    expect($itemB->fresh()->is_omitted)->toBeFalse()
        ->and($b->fresh()->quantity_allocated)->toBe(5)
        ->and($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::FULLY_ORDERED);
});

// ACC-14 omitting a partially allocated line waives only the unallocated remainder
test('omitting a partially allocated line short-closes only the remainder', function () {
    ['pr' => $pr, 'a' => $a, 'b' => $b, 'unit' => $unit, 'supplier' => $supplier, 'user' => $user] = prReadyForPo();

    // Alpha fully ordered (10/10), Bravo partially ordered (3/5).
    makeOrder($pr, $supplier, [
        ['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100],
        ['line_item_id' => $b->id, 'quantity' => 3, 'unit_id' => $unit->id, 'price' => 50],
    ]);
    expect($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::PARTIALLY_ORDERED);

    // Short-close Bravo's remaining 2: the 3 on order stay ordered.
    $this->actingAs($user)
        ->post(route('line-items.omission', $b), ['omit' => true, 'reason' => 'Remainder no longer needed'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($b->fresh()->is_omitted)->toBeTrue()
        ->and($b->fresh()->quantity_allocated)->toBe(3)
        ->and($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::FULLY_ALLOCATED);

    // Restoring the line puts the remaining 2 back on the books.
    $this->actingAs($user)
        ->post(route('line-items.omission', $b), ['omit' => false])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::PARTIALLY_ORDERED);
});

// ACC-14b an omitted line still refuses NEW allocations (only existing ones count)
test('an omitted line cannot receive new allocations', function () {
    ['pr' => $pr, 'a' => $a, 'b' => $b, 'unit' => $unit, 'supplier' => $supplier, 'user' => $user] = prReadyForPo();

    $this->actingAs($user)
        ->post(route('line-items.omission', $b), ['omit' => true])
        ->assertRedirect();

    expect(fn () => makeOrder($pr->fresh(), $supplier, [
        ['line_item_id' => $b->id, 'quantity' => 2, 'unit_id' => $unit->id, 'price' => 50],
    ]))->toThrow(ValidationException::class);

    expect($b->fresh()->quantity_allocated)->toBe(0);
});

// ACC-15 the un-omit trap: closing a PR by omission must stay reversible
test('omitting the last remaining line can be reverted after the requisition closes', function () {
    ['pr' => $pr, 'a' => $a, 'b' => $b, 'user' => $user] = prReadyForPo();

    $this->actingAs($user)
        ->post(route('line-items.omission', $a), ['omit' => true])
        ->assertSessionHasNoErrors();
    $this->actingAs($user)
        ->post(route('line-items.omission', $b), ['omit' => true])
        ->assertSessionHasNoErrors();

    expect($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::CLOSED);

    // Previously a 403: CLOSED was outside the omit policy's whitelist.
    $this->actingAs($user)
        ->post(route('line-items.omission', $b), ['omit' => false])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($b->fresh()->is_omitted)->toBeFalse()
        ->and($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::READY_FOR_PO);
});

// ACC-16 the toggle also works on a FULLY_ORDERED requisition
test('line omission toggle works on a fully ordered requisition', function () {
    ['pr' => $pr, 'a' => $a, 'b' => $b, 'unit' => $unit, 'supplier' => $supplier, 'user' => $user] = prReadyForPo();

    $b->forceFill(['omitted_at' => now()])->save();

    $po = makeOrder($pr, $supplier, [
        ['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100],
    ]);
    approveOrder($po);
    expect($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::FULLY_ORDERED);

    $this->actingAs($user)
        ->post(route('line-items.omission', $b), ['omit' => false])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    // Bravo is wanted again → the requisition drops back to partially ordered.
    expect($b->fresh()->is_omitted)->toBeFalse()
        ->and($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::PARTIALLY_ORDERED);
});

// ACC-07 omitting a PR line excludes it from closure
test('an omitted requisition line is excluded from closure', function () {
    ['pr' => $pr, 'a' => $a, 'b' => $b, 'unit' => $unit, 'supplier' => $supplier, 'user' => $user] = prReadyForPo();

    $b->forceFill(['omitted_at' => now()])->save();

    $po = makeOrder($pr, $supplier, [
        ['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100],
    ]);
    approveOrder($po);

    // Bravo is short-closed, so ordering Alpha alone fully allocates the requisition.
    expect($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::FULLY_ORDERED);

    $itemA = $po->items->firstWhere('line_item_id', $a->id);
    $rr = receive($po, $user, [['purchase_order_item_id' => $itemA->id, 'quantity_received' => 10]]);
    verifyReport($rr);

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::FULLY_RECEIVED)
        ->and($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::CLOSED);
});

// ACC-09 duplicate payload rows cannot jointly over-allocate a PR line (service backstop)
test('duplicate line item rows in one purchase order cannot jointly exceed the pool', function () {
    ['pr' => $pr, 'a' => $a, 'unit' => $unit, 'supplier' => $supplier] = prReadyForPo();

    // Two rows of 6 each pass individually against qty 10 — together they must not.
    expect(fn () => makeOrder($pr, $supplier, [
        ['line_item_id' => $a->id, 'quantity' => 6, 'unit_id' => $unit->id, 'price' => 100],
        ['line_item_id' => $a->id, 'quantity' => 6, 'unit_id' => $unit->id, 'price' => 100],
    ]))->toThrow(ValidationException::class);

    expect($a->fresh()->quantity_allocated)->toBe(0);
});

// ACC-08 the invariant: pending recorded quantity still guards over-receipt
test('a pending receipt still guards against over-receiving on a second report', function () {
    ['pr' => $pr, 'a' => $a, 'unit' => $unit, 'supplier' => $supplier, 'user' => $user] = prReadyForPo();

    $po = makeOrder($pr, $supplier, [
        ['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100],
    ]);
    approveOrder($po);
    $itemA = $po->items->firstWhere('line_item_id', $a->id);

    // First report records the full quantity but is left unverified.
    receive($po, $user, [['purchase_order_item_id' => $itemA->id, 'quantity_received' => 10]]);

    expect($itemA->fresh()->quantity_received)->toBe(10)
        ->and($itemA->fresh()->quantity_verified_received)->toBe(0);

    // A second report cannot claim the same units even though none are verified yet.
    expect(fn () => receive($po, $user, [['purchase_order_item_id' => $itemA->id, 'quantity_received' => 1]]))
        ->toThrow(ValidationException::class);
});

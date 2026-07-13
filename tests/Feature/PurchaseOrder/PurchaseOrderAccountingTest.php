<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequisitionStatus;
use App\Enums\WorkflowInstanceStatus;
use App\Models\ItemUnit;
use App\Services\PurchaseOrderService;
use Illuminate\Validation\ValidationException;

// PO-01 (accounting): full allocation drives the PR status
test('ordering every line allocates the requisition fully', function () {
    ['pr' => $pr, 'a' => $a, 'b' => $b, 'unit' => $unit, 'supplier' => $supplier] = prReadyForPo();

    makeOrder($pr, $supplier, [
        ['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100],
        ['line_item_id' => $b->id, 'quantity' => 5, 'unit_id' => $unit->id, 'price' => 50],
    ]);

    expect($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::FULLY_ALLOCATED);
});

// PO-01 (accounting): a partial allocation
test('ordering part of a line leaves the requisition partially ordered', function () {
    ['pr' => $pr, 'a' => $a, 'unit' => $unit, 'supplier' => $supplier] = prReadyForPo();

    makeOrder($pr, $supplier, [['line_item_id' => $a->id, 'quantity' => 4, 'unit_id' => $unit->id, 'price' => 100]]);

    expect($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::PARTIALLY_ORDERED)
        ->and($a->fresh()->quantity_allocated)->toBe(4)
        ->and($a->fresh()->quantity_unallocated)->toBe(6);
});

// PO-03 over-allocation
test('a second order cannot over-allocate a fully ordered line', function () {
    ['pr' => $pr, 'a' => $a, 'unit' => $unit, 'supplier' => $supplier] = prReadyForPo();
    makeOrder($pr, $supplier, [['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100]]);

    expect(fn () => makeOrder($pr, $supplier, [['line_item_id' => $a->id, 'quantity' => 1, 'unit_id' => $unit->id, 'price' => 100]]))
        ->toThrow(ValidationException::class);
});

// PO-04 unit mismatch
test('ordering with a unit different from the requisition line is rejected', function () {
    ['pr' => $pr, 'a' => $a, 'supplier' => $supplier] = prReadyForPo();
    $box = ItemUnit::create(['name' => 'Box', 'code' => 'box']);

    expect(fn () => makeOrder($pr, $supplier, [['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $box->id, 'price' => 100]]))
        ->toThrow(ValidationException::class);
});

// PO-05 omitted line cannot be ordered
test('an omitted requisition line cannot be ordered', function () {
    ['pr' => $pr, 'a' => $a, 'unit' => $unit, 'supplier' => $supplier] = prReadyForPo();
    $a->forceFill(['omitted_at' => now()])->save();

    expect(fn () => makeOrder($pr, $supplier, [['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100]]))
        ->toThrow(ValidationException::class);
});

// PO-08 update a draft order
test('a draft purchase order can be updated', function () {
    ['pr' => $pr, 'a' => $a, 'unit' => $unit, 'supplier' => $supplier] = prReadyForPo();
    $po = makeOrder($pr, $supplier, [['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100]]);

    app(PurchaseOrderService::class)->update($po, [
        'items' => [['line_item_id' => $a->id, 'quantity' => 6, 'unit_id' => $unit->id, 'price' => 100]],
    ]);

    expect($a->fresh()->quantity_allocated)->toBe(6);
});

// PO-09 update blocked once out of draft
test('a non-draft purchase order cannot be updated', function () {
    ['pr' => $pr, 'a' => $a, 'unit' => $unit, 'supplier' => $supplier] = prReadyForPo();
    $po = makeOrder($pr, $supplier, [['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100]]);
    $po->forceFill(['status' => PurchaseOrderStatus::APPROVED])->save();

    expect(fn () => app(PurchaseOrderService::class)->update($po, [
        'items' => [['line_item_id' => $a->id, 'quantity' => 6, 'unit_id' => $unit->id, 'price' => 100]],
    ]))->toThrow(ValidationException::class);
});

// PO-11 cancelled order frees its allocation
test('a cancelled purchase order frees its allocation and unlocks the requisition', function () {
    ['pr' => $pr, 'a' => $a, 'b' => $b, 'unit' => $unit, 'supplier' => $supplier] = prReadyForPo();
    $po = makeOrder($pr, $supplier, [
        ['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100],
        ['line_item_id' => $b->id, 'quantity' => 5, 'unit_id' => $unit->id, 'price' => 50],
    ]);

    expect($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::FULLY_ALLOCATED);

    $po->applyWorkflowStatus(WorkflowInstanceStatus::Cancelled);

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::CANCELLED)
        ->and($a->fresh()->quantity_allocated)->toBe(0)
        ->and($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::READY_FOR_PO);

    // The freed quantity can be re-ordered (reload: the cancel re-synced a separate instance).
    makeOrder($pr->fresh(), $supplier, [['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100]]);
    expect($a->fresh()->quantity_allocated)->toBe(10);
});

// PO-10 (accounting): deleting a draft order frees its allocation
test('deleting a draft purchase order reverts the requisition status', function () {
    ['pr' => $pr, 'a' => $a, 'unit' => $unit, 'supplier' => $supplier] = prReadyForPo();
    $po = makeOrder($pr, $supplier, [['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100]]);

    app(PurchaseOrderService::class)->delete($po);

    expect($a->fresh()->quantity_allocated)->toBe(0)
        ->and($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::READY_FOR_PO);
});

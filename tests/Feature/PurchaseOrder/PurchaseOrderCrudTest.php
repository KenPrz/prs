<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequisitionStatus;
use App\Models\Address;
use App\Models\ItemUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\Supplier;
use App\Models\User;
use App\Pdf\BuildPurchaseOrderPdf;
use Inertia\Testing\AssertableInertia as Assert;

/** A valid PO store payload against the prReadyForPo() fixture. */
function poStorePayload(PurchaseRequisition $pr, int $supplierId, int $lineItemId, int $unitId, array $overrides = []): array
{
    $billTo = Address::factory()->create();
    $shipTo = Address::factory()->create();

    return array_replace([
        'purchase_requisition_id' => $pr->id,
        'supplier_id' => $supplierId,
        'bill_to_id' => $billTo->id,
        'ship_to_id' => $shipTo->id,
        'payment_terms' => 'Net 30',
        'currency' => 'PHP',
        'price_type' => 'VAT_INCLUSIVE',
        'items' => [['line_item_id' => $lineItemId, 'quantity' => 10, 'unit_id' => $unitId, 'price' => 100]],
    ], $overrides);
}

// PO-07 read: index
test('authenticated users can view the purchase order index', function () {
    $this->actingAs(userWithPermission('po.view'))
        ->get(route('purchase-orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('purchase-order/index'));
});

// PO-01 create happy path (HTTP)
test('a purchase order can be created from a ready requisition', function () {
    ['pr' => $pr, 'a' => $a, 'unit' => $unit, 'supplier' => $supplier, 'user' => $user] = prReadyForPo();

    $this->actingAs($user)
        ->post(route('purchase-orders.store'), poStorePayload($pr, $supplier->id, $a->id, $unit->id))
        ->assertRedirect();

    expect(PurchaseOrder::where('purchase_requisition_id', $pr->id)->count())->toBe(1);
});

// PO-12 price is mandatory on purchase order items
test('a purchase order item requires a price', function () {
    ['pr' => $pr, 'a' => $a, 'unit' => $unit, 'supplier' => $supplier, 'user' => $user] = prReadyForPo();

    $this->actingAs($user)
        ->post(route('purchase-orders.store'), poStorePayload($pr, $supplier->id, $a->id, $unit->id, [
            'items' => [['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => null]],
        ]))
        ->assertSessionHasErrors('items.0.price');
});

// PO-02 validation: required fields
test('creating a purchase order requires supplier and items', function () {
    ['pr' => $pr, 'a' => $a, 'unit' => $unit, 'supplier' => $supplier, 'user' => $user] = prReadyForPo();

    $this->actingAs($user)
        ->post(route('purchase-orders.store'), poStorePayload($pr, $supplier->id, $a->id, $unit->id, [
            'supplier_id' => null,
            'items' => [],
        ]))
        ->assertSessionHasErrors(['supplier_id', 'items']);
});

// PO-02 edge: PR must be in an orderable status
test('a purchase order cannot be created from a non-orderable requisition', function () {
    $user = userWithPermission('po.prepare');
    $pr = PurchaseRequisition::factory()->for($user, 'requestor')->create([
        'status' => PurchaseRequisitionStatus::DRAFT,
    ]);
    $supplier = Supplier::factory()->create();
    $unit = ItemUnit::create(['name' => 'Pieces', 'code' => 'pcs']);
    $line = $pr->lineItems()->create(['name' => 'X', 'quantity' => 5, 'unit_id' => $unit->id, 'price' => 10]);

    $this->actingAs($user)
        ->post(route('purchase-orders.store'), poStorePayload($pr, $supplier->id, $line->id, $unit->id))
        ->assertSessionHasErrors('purchase_requisition_id');
});

// PO-10 delete draft (a reason is required and the allocation returns to the pool)
test('a draft purchase order can be deleted with a reason', function () {
    ['pr' => $pr, 'a' => $a, 'unit' => $unit, 'supplier' => $supplier, 'user' => $user] = prReadyForPo();
    $po = makeOrder($pr, $supplier, [['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100]]);

    // Without a reason the deletion is refused.
    $this->actingAs($user)
        ->delete(route('purchase-orders.destroy', $po))
        ->assertSessionHasErrors('reason');

    $this->actingAs($user)
        ->delete(route('purchase-orders.destroy', $po), ['reason' => 'Ordered from the wrong supplier'])
        ->assertRedirect();

    $this->assertDatabaseMissing('purchase_orders', ['id' => $po->id]);
    expect($a->fresh()->quantity_unallocated)->toBe(10);
});

// PO-11 duplicate rows are rejected at the endpoint by the distinct rule
test('creating a purchase order rejects duplicate line item rows', function () {
    ['pr' => $pr, 'a' => $a, 'unit' => $unit, 'supplier' => $supplier, 'user' => $user] = prReadyForPo();

    $this->actingAs($user)
        ->post(route('purchase-orders.store'), poStorePayload($pr, $supplier->id, $a->id, $unit->id, [
            'items' => [
                ['line_item_id' => $a->id, 'quantity' => 6, 'unit_id' => $unit->id, 'price' => 100],
                ['line_item_id' => $a->id, 'quantity' => 6, 'unit_id' => $unit->id, 'price' => 100],
            ],
        ]))
        ->assertSessionHasErrors(['items.0.line_item_id', 'items.1.line_item_id']);
});

// PO-12 permission: creating requires po.prepare
test('a user without the po.prepare permission cannot create a purchase order', function () {
    ['pr' => $pr, 'a' => $a, 'unit' => $unit, 'supplier' => $supplier] = prReadyForPo();
    $rando = User::factory()->create();

    $this->actingAs($rando)
        ->post(route('purchase-orders.store'), poStorePayload($pr, $supplier->id, $a->id, $unit->id))
        ->assertForbidden();

    expect(PurchaseOrder::count())->toBe(0);
});

// PO-13 seal: an approved order can be marked as ordered (final PDF bound)
test('a preparer can mark an approved purchase order as ordered', function () {
    ['pr' => $pr, 'a' => $a, 'unit' => $unit, 'supplier' => $supplier, 'user' => $user] = prReadyForPo();
    $po = makeOrder($pr, $supplier, [['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100]]);
    $po->forceFill(['status' => PurchaseOrderStatus::APPROVED])->save();

    $this->mock(BuildPurchaseOrderPdf::class, fn ($m) => $m->shouldReceive('build')->andReturn('%PDF-1.4 fake'));

    $this->actingAs($user)
        ->post(route('purchase-orders.mark-as-ordered', $po))
        ->assertRedirect(route('purchase-orders.show', $po));

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::RELEASED)
        ->and($po->fresh()->getMedia('final_document'))->toHaveCount(1);
});

// PO-14 seal blocked for drafts and for users without the permission
test('marking as ordered is refused for drafts and unauthorized users', function () {
    ['pr' => $pr, 'a' => $a, 'unit' => $unit, 'supplier' => $supplier, 'user' => $user] = prReadyForPo();
    $po = makeOrder($pr, $supplier, [['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100]]);

    // Still a draft → refused even for the preparer.
    $this->actingAs($user)
        ->post(route('purchase-orders.mark-as-ordered', $po))
        ->assertForbidden();

    $po->forceFill(['status' => PurchaseOrderStatus::APPROVED])->save();

    $this->actingAs(User::factory()->create())
        ->post(route('purchase-orders.mark-as-ordered', $po))
        ->assertForbidden();
});

// PO-15 an approved order can be cancelled with a reason instead of sealed
test('a preparer can cancel an approved purchase order with a reason', function () {
    ['pr' => $pr, 'a' => $a, 'unit' => $unit, 'supplier' => $supplier, 'user' => $user] = prReadyForPo();
    $po = makeOrder($pr, $supplier, [['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100]]);
    $po->forceFill(['status' => PurchaseOrderStatus::APPROVED])->save();

    // Without a reason the cancellation is refused.
    $this->actingAs($user)
        ->post(route('purchase-orders.cancel', $po))
        ->assertSessionHasErrors('reason');

    $this->actingAs($user)
        ->post(route('purchase-orders.cancel', $po), ['reason' => 'Supplier can no longer fulfil'])
        ->assertRedirect(route('purchase-orders.show', $po));

    // The cancelled order releases its allocation back to the requisition pool.
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::CANCELLED)
        ->and($a->fresh()->quantity_unallocated)->toBe(10)
        ->and($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::READY_FOR_PO);
});

// PO-09 delete blocked once out of draft (service draft-assertion → 422, not policy)
test('a non-draft purchase order cannot be deleted', function () {
    ['pr' => $pr, 'a' => $a, 'unit' => $unit, 'supplier' => $supplier, 'user' => $user] = prReadyForPo();
    $po = makeOrder($pr, $supplier, [['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100]]);
    $po->forceFill(['status' => PurchaseOrderStatus::APPROVED])->save();

    $this->actingAs($user)->delete(route('purchase-orders.destroy', $po));

    $this->assertDatabaseHas('purchase_orders', ['id' => $po->id]);
});

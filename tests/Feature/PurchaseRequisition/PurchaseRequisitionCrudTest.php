<?php

use App\Enums\PurchaseRequisitionStatus;
use App\Models\Department;
use App\Models\ItemUnit;
use App\Models\PurchaseRequisition;
use App\Models\User;
use App\Pdf\BuildPurchaseRequisitionPdf;
use Inertia\Testing\AssertableInertia as Assert;

/** A valid store payload; pass $overrides to break one field for validation tests. */
function prStorePayload(int $departmentId, int $unitId, array $overrides = []): array
{
    return array_replace([
        'title' => 'Lab supplies',
        'description' => 'Quarterly order',
        'delivery_date' => now()->addWeek()->format('Y-m-d'),
        'department_ids' => [$departmentId],
        'price_type' => 'VAT_INCLUSIVE',
        'purpose_type' => 'OFFICE_USE',
        'line_items' => [[
            'name' => 'Microscope Slides',
            'quantity' => 10,
            'unit_id' => $unitId,
            'price' => 150.00,
        ]],
        'notes' => [['content' => 'Urgent']],
    ], $overrides);
}

// PR-06 read: index
test('authenticated users can view the requisition index', function () {
    $this->actingAs(userWithPermission('pr.view'))
        ->get(route('purchase-requisitions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('purchase-requisition/index')->has('purchaseRequisitions'));
});

// PR-01 create happy path
test('a user can create a requisition', function () {
    $user = userWithPermission('pr.prepare');
    $dept = Department::create(['name' => 'IT', 'code' => 'IT']);
    $unit = ItemUnit::create(['name' => 'Pieces', 'code' => 'pcs']);

    $this->actingAs($user)
        ->post(route('purchase-requisitions.store'), prStorePayload($dept->id, $unit->id))
        ->assertRedirect();

    $pr = PurchaseRequisition::firstWhere('title', 'Lab supplies');
    expect($pr)->not->toBeNull()
        ->and($pr->status)->toBe(PurchaseRequisitionStatus::DRAFT)
        ->and($pr->requestor_id)->toBe($user->id)
        ->and($pr->lineItems)->toHaveCount(1);
});

// PR-01b permission: only pr.prepare holders can create a requisition
test('a user without the pr.prepare permission cannot create a requisition', function () {
    $dept = Department::create(['name' => 'IT', 'code' => 'IT']);
    $unit = ItemUnit::create(['name' => 'Pieces', 'code' => 'pcs']);

    $this->actingAs(User::factory()->create())
        ->post(route('purchase-requisitions.store'), prStorePayload($dept->id, $unit->id))
        ->assertForbidden();

    expect(PurchaseRequisition::count())->toBe(0);
});

// PR-02 validation: missing required fields
test('creating a requisition requires a title, department and line items', function () {
    $user = userWithPermission('pr.prepare');
    $dept = Department::create(['name' => 'IT', 'code' => 'IT']);
    $unit = ItemUnit::create(['name' => 'Pieces', 'code' => 'pcs']);

    $this->actingAs($user)
        ->post(route('purchase-requisitions.store'), prStorePayload($dept->id, $unit->id, [
            'title' => '',
            'department_ids' => [],
            'line_items' => [],
        ]))
        ->assertSessionHasErrors(['title', 'department_ids', 'line_items']);

    expect(PurchaseRequisition::count())->toBe(0);
});

// PR-04 boundary: quantity must be >= 1
test('creating a requisition rejects a zero or negative quantity', function () {
    $user = userWithPermission('pr.prepare');
    $dept = Department::create(['name' => 'IT', 'code' => 'IT']);
    $unit = ItemUnit::create(['name' => 'Pieces', 'code' => 'pcs']);

    $this->actingAs($user)
        ->post(route('purchase-requisitions.store'), prStorePayload($dept->id, $unit->id, [
            'line_items' => [['name' => 'X', 'quantity' => 0, 'unit_id' => $unit->id]],
        ]))
        ->assertSessionHasErrors('line_items.0.quantity');
});

// PR-13 price is optional on requisition lines (pricing is finalized on the PO)
test('a requisition line can be created without a price', function () {
    $user = userWithPermission('pr.prepare');
    $dept = Department::create(['name' => 'IT', 'code' => 'IT']);
    $unit = ItemUnit::create(['name' => 'Pieces', 'code' => 'pcs']);

    $this->actingAs($user)
        ->post(route('purchase-requisitions.store'), prStorePayload($dept->id, $unit->id, [
            'line_items' => [['name' => 'Unpriced Item', 'quantity' => 5, 'unit_id' => $unit->id, 'price' => null]],
        ]))
        ->assertSessionHasNoErrors();

    expect(PurchaseRequisition::query()->latest('id')->first()->lineItems()->first()->price)->toBeNull();
});

// PR-05 boundary: delivery date cannot be in the past
test('creating a requisition rejects a past delivery date', function () {
    $user = userWithPermission('pr.prepare');
    $dept = Department::create(['name' => 'IT', 'code' => 'IT']);
    $unit = ItemUnit::create(['name' => 'Pieces', 'code' => 'pcs']);

    $this->actingAs($user)
        ->post(route('purchase-requisitions.store'), prStorePayload($dept->id, $unit->id, [
            'delivery_date' => now()->subDay()->format('Y-m-d'),
        ]))
        ->assertSessionHasErrors('delivery_date');
});

// PR-07 read: show
test('a requisition can be viewed', function () {
    $user = userWithPermission('pr.view');
    $pr = PurchaseRequisition::factory()->for($user, 'requestor')->create(['status' => PurchaseRequisitionStatus::DRAFT]);

    $this->actingAs($user)
        ->get(route('purchase-requisitions.show', $pr))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('purchase-requisition/show'));
});

// PR-08 read: 404
test('viewing a missing requisition returns 404', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('purchase-requisitions.show', 999999))
        ->assertNotFound();
});

// PR-09 update draft happy path
test('the requestor can update their draft requisition', function () {
    $user = userWithPermission('pr.view', 'pr.prepare');
    $dept = Department::create(['name' => 'IT', 'code' => 'IT']);
    $unit = ItemUnit::create(['name' => 'Pieces', 'code' => 'pcs']);
    $pr = PurchaseRequisition::factory()->for($user, 'requestor')->create([
        'title' => 'Original', 'status' => PurchaseRequisitionStatus::DRAFT,
    ]);
    $pr->departments()->sync([$dept->id]);
    $line = $pr->lineItems()->create(['name' => 'Item A', 'quantity' => 1, 'unit_id' => $unit->id, 'price' => 10]);

    $this->actingAs($user)
        ->patch(route('purchase-requisitions.update', $pr), [
            'title' => 'Updated title',
            'status' => PurchaseRequisitionStatus::DRAFT->value,
            'department_ids' => [$dept->id],
            'price_type' => 'VAT_INCLUSIVE',
            'purpose_type' => 'OFFICE_USE',
            'line_items' => [['id' => $line->id, 'name' => 'Item A', 'quantity' => 2, 'unit_id' => $unit->id, 'price' => 10]],
            'notes' => [],
        ])
        ->assertRedirect(route('purchase-requisitions.show', $pr));

    expect($pr->fresh()->title)->toBe('Updated title');
});

// PR-10 / PR-12 permission: only the owner of a draft can update
test('a non-owner cannot update a requisition', function () {
    $pr = PurchaseRequisition::factory()->for(User::factory()->create(), 'requestor')->create([
        'status' => PurchaseRequisitionStatus::DRAFT,
    ]);

    $this->actingAs(User::factory()->create())
        ->patch(route('purchase-requisitions.update', $pr), ['title' => 'Hacked'])
        ->assertForbidden();
});

// PR-10 update blocked once out of draft
test('a non-draft requisition cannot be updated', function () {
    $user = User::factory()->create();
    $pr = PurchaseRequisition::factory()->for($user, 'requestor')->create([
        'status' => PurchaseRequisitionStatus::APPROVED,
    ]);

    $this->actingAs($user)
        ->patch(route('purchase-requisitions.update', $pr), ['title' => 'Nope'])
        ->assertForbidden();
});

// PR-13 delete draft (a reason is required)
test('the requestor can delete a draft requisition with a reason', function () {
    $user = userWithPermission('pr.prepare');
    $pr = PurchaseRequisition::factory()->for($user, 'requestor')->create(['status' => PurchaseRequisitionStatus::DRAFT]);

    // Without a reason the deletion is refused.
    $this->actingAs($user)
        ->delete(route('purchase-requisitions.destroy', $pr))
        ->assertSessionHasErrors('reason');

    $this->actingAs($user)
        ->delete(route('purchase-requisitions.destroy', $pr), ['reason' => 'No longer needed'])
        ->assertRedirect(route('purchase-requisitions.index'));

    $this->assertDatabaseMissing('purchase_requisitions', ['id' => $pr->id]);
});

// PR-14 delete blocked once out of draft
test('a non-draft requisition cannot be deleted', function () {
    $user = User::factory()->create();
    $pr = PurchaseRequisition::factory()->for($user, 'requestor')->create(['status' => PurchaseRequisitionStatus::APPROVED]);

    $this->actingAs($user)->delete(route('purchase-requisitions.destroy', $pr))->assertForbidden();
});

// PR-11 duplicate handling: numbers are sequential, never colliding
test('requisition numbers are generated sequentially', function () {
    $user = User::factory()->create();
    $one = PurchaseRequisition::factory()->for($user, 'requestor')->create();
    $two = PurchaseRequisition::factory()->for($user, 'requestor')->create();

    expect($one->pr_number)->not->toBe($two->pr_number)
        ->and($one->pr_number)->toMatch('/^\d{4}-\d{3}$/');
});

// PR-15 mark-ready-for-po (happy path; PDF build is mocked)
test('the designated orderer can mark an approved requisition ready for PO', function () {
    $orderer = userWithPermission('pr.finalize');
    $pr = PurchaseRequisition::factory()->for(User::factory()->create(), 'requestor')->create([
        'status' => PurchaseRequisitionStatus::APPROVED,
        'to_be_ordered_by_id' => $orderer->id,
    ]);

    $this->mock(BuildPurchaseRequisitionPdf::class, fn ($m) => $m->shouldReceive('build')->andReturn('%PDF-1.4 fake'));

    $this->actingAs($orderer)
        ->post(route('purchase-requisitions.mark-ready-for-po', $pr))
        ->assertRedirect(route('purchase-requisitions.show', $pr));

    expect($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::READY_FOR_PO);
});

// PR-16 mark-ready unauthorized
test('a non-orderer cannot mark a requisition ready for PO', function () {
    $pr = PurchaseRequisition::factory()->for(User::factory()->create(), 'requestor')->create([
        'status' => PurchaseRequisitionStatus::APPROVED,
        'to_be_ordered_by_id' => User::factory()->create()->id,
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('purchase-requisitions.mark-ready-for-po', $pr))
        ->assertForbidden();
});

// PR-17 an approved requisition with no orders can be cancelled with a reason
test('the requestor can cancel an approved requisition with a reason', function () {
    $user = userWithPermission('pr.cancel');
    $pr = PurchaseRequisition::factory()->for($user, 'requestor')->create([
        'status' => PurchaseRequisitionStatus::APPROVED,
    ]);

    // Without a reason the cancellation is refused.
    $this->actingAs($user)
        ->post(route('purchase-requisitions.cancel', $pr))
        ->assertSessionHasErrors('reason');

    $this->actingAs($user)
        ->post(route('purchase-requisitions.cancel', $pr), ['reason' => 'Budget withdrawn'])
        ->assertRedirect(route('purchase-requisitions.show', $pr));

    expect($pr->fresh()->status)->toBe(PurchaseRequisitionStatus::CANCELLED);
});

// PR-18 cancellation is blocked while active purchase orders exist
test('a requisition with active purchase orders cannot be cancelled', function () {
    ['pr' => $pr, 'a' => $a, 'unit' => $unit, 'supplier' => $supplier, 'user' => $user] = prReadyForPo();
    makeOrder($pr, $supplier, [['line_item_id' => $a->id, 'quantity' => 5, 'unit_id' => $unit->id, 'price' => 100]]);

    $this->actingAs($user)
        ->post(route('purchase-requisitions.cancel', $pr->fresh()), ['reason' => 'Changed my mind'])
        ->assertForbidden();
});

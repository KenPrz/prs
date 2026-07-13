<?php

use App\Enums\PaymentRequestFormStatus;
use App\Models\Department;
use App\Models\PaymentRequestForm;
use App\Models\Supplier;
use App\Models\User;

/** A valid PRF store payload; pass $overrides to break one field for validation tests. */
function prfStorePayload(int $supplierId, int $departmentId, array $overrides = []): array
{
    return array_replace([
        'supplier_id' => $supplierId,
        'amount' => 15000.50,
        'description' => 'Internet bill for March 2026',
        'invoice_number' => 'INV-001',
        'due_date' => '2026-04-30',
        'department_ids' => [$departmentId],
        'notes' => [['content' => 'Monthly recurring']],
    ], $overrides);
}

// PRF-04 read: index
test('the payment request form index renders', function () {
    $user = userWithPermission('prf.view');
    PaymentRequestForm::factory()->count(2)->for($user, 'requestor')->create();

    $this->actingAs($user)
        ->get(route('payment-request-forms.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('payment-request-form/index')->has('paymentRequestForms.data', 2));
});

// PRF-04 read: create page
test('the payment request form create page renders', function () {
    $this->actingAs(userWithPermission('prf.prepare'))
        ->get(route('payment-request-forms.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('payment-request-form/create')->has('suppliers')->has('departments'));
});

// PRF-04b permission: only prf.prepare holders can create
test('a user without the prf.prepare permission cannot create a payment request form', function () {
    $supplier = Supplier::factory()->create();
    $dept = Department::create(['name' => 'Finance', 'code' => 'FIN']);

    $this->actingAs(User::factory()->create())
        ->post(route('payment-request-forms.store'), prfStorePayload($supplier->id, $dept->id))
        ->assertForbidden();

    expect(PaymentRequestForm::count())->toBe(0);
});

// PRF-01 create happy path
test('a draft payment request form can be created', function () {
    $user = userWithPermission('prf.prepare');
    $supplier = Supplier::factory()->create();
    $dept = Department::create(['name' => 'Finance', 'code' => 'FIN']);

    $this->actingAs($user)
        ->post(route('payment-request-forms.store'), prfStorePayload($supplier->id, $dept->id))
        ->assertRedirect();

    $prf = PaymentRequestForm::query()->latest()->first();
    expect($prf->status)->toBe(PaymentRequestFormStatus::DRAFT)
        ->and($prf->requestor_id)->toBe($user->id)
        ->and($prf->departments)->toHaveCount(1);
});

// PRF-02 validation: required fields
test('creating a payment request form requires a supplier, amount and department', function () {
    $user = userWithPermission('prf.prepare');
    $supplier = Supplier::factory()->create();
    $dept = Department::create(['name' => 'Finance', 'code' => 'FIN']);

    $this->actingAs($user)
        ->post(route('payment-request-forms.store'), prfStorePayload($supplier->id, $dept->id, [
            'supplier_id' => null,
            'amount' => null,
            'department_ids' => [],
        ]))
        ->assertSessionHasErrors(['supplier_id', 'amount', 'department_ids']);
});

// PRF-03 boundary: amount must be at least 0.01
test('creating a payment request form rejects a zero amount', function () {
    $user = userWithPermission('prf.prepare');
    $supplier = Supplier::factory()->create();
    $dept = Department::create(['name' => 'Finance', 'code' => 'FIN']);

    $this->actingAs($user)
        ->post(route('payment-request-forms.store'), prfStorePayload($supplier->id, $dept->id, ['amount' => 0]))
        ->assertSessionHasErrors('amount');
});

// PRF-04 read: show
test('a payment request form can be viewed', function () {
    $user = userWithPermission('prf.view');
    $prf = PaymentRequestForm::factory()->for($user, 'requestor')->create();

    $this->actingAs($user)
        ->get(route('payment-request-forms.show', $prf))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('payment-request-form/show')->has('paymentRequestForm'));
});

// PRF-05 update draft
test('the requestor can update a draft payment request form', function () {
    $user = userWithPermission('prf.prepare');
    $supplier = Supplier::factory()->create();
    $dept = Department::create(['name' => 'Finance', 'code' => 'FIN']);
    $prf = PaymentRequestForm::factory()->for($user, 'requestor')->create(['status' => PaymentRequestFormStatus::DRAFT]);

    $this->actingAs($user)
        ->put(route('payment-request-forms.update', $prf), [
            'supplier_id' => $supplier->id,
            'amount' => 25000,
            'description' => 'Updated description',
            'department_ids' => [$dept->id],
        ])
        ->assertRedirect();

    expect($prf->fresh()->amount)->toBe('25000.00')
        ->and($prf->fresh()->description)->toBe('Updated description');
});

// PRF-06 update blocked once out of draft
test('a non-draft payment request form cannot be updated', function () {
    $user = User::factory()->create();
    $prf = PaymentRequestForm::factory()->for($user, 'requestor')->create(['status' => PaymentRequestFormStatus::REVIEWING]);

    $this->actingAs($user)
        ->put(route('payment-request-forms.update', $prf), ['amount' => 1])
        ->assertForbidden();
});

// PRF-09 permission: non-owner cannot update
test('another user cannot update someone elses draft payment request form', function () {
    $prf = PaymentRequestForm::factory()->for(User::factory()->create(), 'requestor')->create(['status' => PaymentRequestFormStatus::DRAFT]);

    $this->actingAs(User::factory()->create())
        ->put(route('payment-request-forms.update', $prf), ['amount' => 1])
        ->assertForbidden();
});

// PRF-07 destroy draft (a reason is required)
test('the requestor can delete a draft payment request form with a reason', function () {
    $user = userWithPermission('prf.prepare');
    $prf = PaymentRequestForm::factory()->for($user, 'requestor')->create(['status' => PaymentRequestFormStatus::DRAFT]);

    // Without a reason the deletion is refused.
    $this->actingAs($user)
        ->delete(route('payment-request-forms.destroy', $prf))
        ->assertSessionHasErrors('reason');

    $this->actingAs($user)
        ->delete(route('payment-request-forms.destroy', $prf), ['reason' => 'Duplicate entry'])
        ->assertRedirect(route('payment-request-forms.index'));

    $this->assertDatabaseMissing('payment_request_forms', ['id' => $prf->id]);
});

// PRF-07 destroy blocked once out of draft
test('a non-draft payment request form cannot be deleted', function () {
    $user = User::factory()->create();
    $prf = PaymentRequestForm::factory()->for($user, 'requestor')->create(['status' => PaymentRequestFormStatus::APPROVED]);

    $this->actingAs($user)->delete(route('payment-request-forms.destroy', $prf))->assertForbidden();
});

// PRF-01 number generation
test('a payment request form number is auto-generated', function () {
    $prf = PaymentRequestForm::factory()->for(User::factory()->create(), 'requestor')->create();

    expect($prf->prf_number)->toMatch('/^\d{4}-\d{3}$/');
});

// PRF-10 the requestor can cancel an approved payment request with a reason
test('the requestor can cancel an approved payment request form with a reason', function () {
    $user = userWithPermission('prf.cancel');
    $prf = PaymentRequestForm::factory()->for($user, 'requestor')->create(['status' => PaymentRequestFormStatus::APPROVED]);

    // Without a reason the cancellation is refused.
    $this->actingAs($user)
        ->post(route('payment-request-forms.cancel', $prf))
        ->assertSessionHasErrors('reason');

    $this->actingAs($user)
        ->post(route('payment-request-forms.cancel', $prf), ['reason' => 'Payment no longer needed'])
        ->assertRedirect(route('payment-request-forms.show', $prf));

    expect($prf->fresh()->status)->toBe(PaymentRequestFormStatus::CANCELLED);
});

// PRF-11 only the requestor can cancel; not a draft or another user
test('cancelling an approved payment request is restricted to the requestor', function () {
    $prf = PaymentRequestForm::factory()->for(User::factory()->create(), 'requestor')->create(['status' => PaymentRequestFormStatus::APPROVED]);

    $this->actingAs(User::factory()->create())
        ->post(route('payment-request-forms.cancel', $prf), ['reason' => 'Not mine'])
        ->assertForbidden();

    // Draft is not cancellable via this endpoint (delete it instead).
    $draft = PaymentRequestForm::factory()->for(User::factory()->create(), 'requestor')->create(['status' => PaymentRequestFormStatus::DRAFT]);
    $this->actingAs($draft->requestor)
        ->post(route('payment-request-forms.cancel', $draft), ['reason' => 'nope'])
        ->assertForbidden();
});

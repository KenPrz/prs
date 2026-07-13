<?php

use App\Models\Signature;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Production-gate remediation
|--------------------------------------------------------------------------
| Covers the fixes from the pre-release audit: render-endpoint authorization,
| private-media URL emission, signature image serving, destroy guards, and
| the dashboard signature-status prop.
*/

// ── PDF render authorization ───────────────────────────────────────────────

test('rendering a requisition pdf requires the view permission', function () {
    ['pr' => $pr] = prReadyForPo();

    $this->actingAs(User::factory()->create())
        ->get(route('purchase-requisitions.render', $pr))
        ->assertForbidden();
});

test('rendering a purchase order pdf requires the view permission', function () {
    ['po' => $po] = orderedOrder();

    $this->actingAs(User::factory()->create())
        ->get(route('purchase-orders.render', $po))
        ->assertForbidden();
});

// ── Private media: API emits the authorized download route ────────────────

test('the api serves attachment urls through the authorized download route', function () {
    ['pr' => $pr, 'user' => $user] = prReadyForPo();
    $pr->addMediaFromString('%PDF-1.4 fake')
        ->usingFileName('quote.pdf')
        ->toMediaCollection('attachments');

    $response = $this->actingAs($user)
        ->getJson("/api/v1/purchase-requisitions/{$pr->id}")
        ->assertOk();

    $url = $response->json('data.attachments.0.url');

    expect($url)->toContain('/attachments/')
        ->and($url)->not->toContain('/storage/');
});

// ── Signature image route ──────────────────────────────────────────────────

test('a signature image is served to its owner only', function () {
    $owner = User::factory()->create();
    $signature = Signature::query()->create(['user_id' => $owner->id, 'is_active' => true]);
    $signature->addMediaFromString('fake-png-bytes')
        ->usingFileName('sig.png')
        ->toMediaCollection('attachments');

    $this->actingAs($owner)
        ->get(route('signatures.image', $signature))
        ->assertOk();

    $this->actingAs(User::factory()->create())
        ->get(route('signatures.image', $signature))
        ->assertForbidden();
});

// ── Destroy guards ─────────────────────────────────────────────────────────

test('a supplier referenced by purchase orders cannot be deleted', function () {
    ['po' => $po] = orderedOrder();
    $supplier = $po->supplier;
    $admin = userWithPermission('config.suppliers.manage');

    $this->actingAs($admin)
        ->from(route('admin.config.suppliers.index'))
        ->delete(route('admin.config.suppliers.destroy', $supplier))
        ->assertRedirect(route('admin.config.suppliers.index'))
        ->assertSessionHas('error');

    expect($supplier->fresh())->not->toBeNull();
});

test('an item unit in use cannot be deleted', function () {
    ['a' => $a] = prReadyForPo();
    $unit = $a->unit;
    $admin = userWithPermission('config.item_units.manage');

    $this->actingAs($admin)
        ->from(route('admin.config.item-units.index'))
        ->delete(route('admin.config.item-units.destroy', $unit))
        ->assertRedirect(route('admin.config.item-units.index'))
        ->assertSessionHas('error');

    expect($unit->fresh())->not->toBeNull();
});

test('a user with documents cannot be deleted from admin config', function () {
    $pr = makePr();
    $requestor = $pr->requestor;
    $admin = userWithPermission('access.users.manage');

    $this->actingAs($admin)
        ->from(route('admin.config.users.index'))
        ->delete(route('admin.config.users.destroy', $requestor))
        ->assertRedirect(route('admin.config.users.index'))
        ->assertSessionHas('error');

    expect($requestor->fresh())->not->toBeNull();
});

test('self-deletion with documents is blocked and keeps the session', function () {
    $pr = makePr();
    $requestor = $pr->requestor;
    $requestor->update(['password' => bcrypt('password')]);

    $this->actingAs($requestor)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('error');

    expect($requestor->fresh())->not->toBeNull();
    $this->assertAuthenticatedAs($requestor);
});

// ── Super Admin god-mode cannot force invalid state transitions ────────────

test('a super admin cannot seal a purchase order that is not approved', function () {
    ['po' => $po] = orderedOrder(); // already RELEASED
    $admin = User::factory()->create();
    $admin->assignRole(Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']));

    $this->actingAs($admin)
        ->from(route('purchase-orders.show', $po))
        ->post(route('purchase-orders.mark-as-ordered', $po))
        ->assertRedirect(route('purchase-orders.show', $po))
        ->assertSessionHas('error');

    expect($po->fresh()->status)->toBe(App\Enums\PurchaseOrderStatus::RELEASED);
});

test('the seal button prop is status-honest even for super admins', function () {
    ['po' => $po] = orderedOrder(); // RELEASED — nothing left to seal
    $admin = User::factory()->create();
    $admin->assignRole(Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']));

    $this->actingAs($admin)
        ->get(route('purchase-orders.show', $po))
        ->assertInertia(fn ($page) => $page
            ->where('canMarkAsOrdered', false)
            ->where('canCancel', false));
});

test('a super admin cannot cancel a draft purchase requisition through the post-approval endpoint', function () {
    $pr = makePr(); // DRAFT
    $admin = User::factory()->create();
    $admin->assignRole(Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']));

    $this->actingAs($admin)
        ->from(route('purchase-requisitions.show', $pr))
        ->post(route('purchase-requisitions.cancel', $pr), ['reason' => 'Testing'])
        ->assertSessionHas('error');

    expect($pr->fresh()->status)->toBe(App\Enums\PurchaseRequisitionStatus::DRAFT);
});

// ── Dashboard signature status ─────────────────────────────────────────────

test('the dashboard reports the signature status', function () {
    $bare = User::factory()->create();

    $this->actingAs($bare)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('signature.uploaded', false)
            ->where('signature.active', false));

    withActiveSignature($bare);

    $this->actingAs($bare)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('signature.uploaded', true)
            ->where('signature.active', true));
});

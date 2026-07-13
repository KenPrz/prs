<?php

use App\Models\PurchaseRequisition;
use App\Models\User;
use Spatie\Permission\Models\Role;

// ── View gating ───────────────────────────────────────────────────────────

test('a user without pr.view cannot list or view requisitions', function () {
    $pr = PurchaseRequisition::factory()->for(User::factory()->create(), 'requestor')->create();

    $this->actingAs(User::factory()->create())
        ->get(route('purchase-requisitions.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->get(route('purchase-requisitions.show', $pr))
        ->assertForbidden();
});

test('a user with pr.view can list and view requisitions', function () {
    $viewer = userWithPermission('pr.view');
    $pr = PurchaseRequisition::factory()->for(User::factory()->create(), 'requestor')->create();

    $this->actingAs($viewer)->get(route('purchase-requisitions.index'))->assertOk();
    $this->actingAs($viewer)->get(route('purchase-requisitions.show', $pr))->assertOk();
});

test('view permissions are document-specific', function () {
    // Holding pr.view does not grant access to the purchase order module.
    $this->actingAs(userWithPermission('pr.view'))
        ->get(route('purchase-orders.index'))
        ->assertForbidden();
});

// ── Admin config: granular per-resource ────────────────────────────────────

test('a user with no admin permission cannot enter the config area', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/config/suppliers')
        ->assertForbidden();
});

test('config permissions are scoped to their own resource', function () {
    $refDataAdmin = userWithPermission('config.suppliers.manage');

    // Permitted section loads.
    $this->actingAs($refDataAdmin)
        ->get(route('admin.config.suppliers.index'))
        ->assertOk();

    // A different section (needs access.users.manage) is denied.
    $this->actingAs($refDataAdmin)
        ->get(route('admin.config.users.index'))
        ->assertForbidden();
});

test('the config landing redirects to the first permitted section', function () {
    $this->actingAs(userWithPermission('access.users.manage'))
        ->get('/admin/config')
        ->assertRedirect(route('admin.config.users.index'));
});

test('a super admin can reach every config section', function () {
    $super = User::factory()->create();
    $super->assignRole(
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web'])
    );

    $this->actingAs($super)->get(route('admin.config.suppliers.index'))->assertOk();
    $this->actingAs($super)->get(route('admin.config.users.index'))->assertOk();
});

// ── Shared permissions to the frontend ─────────────────────────────────────

test('the authenticated user permission list is shared with the frontend', function () {
    $user = userWithPermission('pr.view', 'po.view');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.permissions', fn ($perms) => collect($perms)->contains('pr.view')
                && collect($perms)->contains('po.view')));
});

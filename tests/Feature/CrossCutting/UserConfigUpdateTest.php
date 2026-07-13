<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('admin can update a user name and email', function () {
    $admin = userWithPermission('access.users.manage');
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.config.users.update', $user), [
            'name' => 'New Name',
            'email' => 'new-email@example.com',
            'roles' => [],
            'departments' => [],
        ])
        ->assertRedirect(route('admin.config.users.index'));

    $user->refresh();
    expect($user->name)->toBe('New Name')
        ->and($user->email)->toBe('new-email@example.com')
        ->and($user->email_verified_at)->not->toBeNull();
});

test('email unique rule ignores the user itself but rejects other users emails', function () {
    $admin = userWithPermission('access.users.manage');
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.config.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => [],
            'departments' => [],
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->put(route('admin.config.users.update', $user), [
            'name' => $user->name,
            'email' => $other->email,
            'roles' => [],
            'departments' => [],
        ])
        ->assertSessionHasErrors('email');
});

test('blank password keeps the current password and a filled one resets it', function () {
    $admin = userWithPermission('access.users.manage');
    $user = User::factory()->create();
    $originalHash = $user->password;

    $this->actingAs($admin)
        ->put(route('admin.config.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => '',
            'roles' => [],
            'departments' => [],
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->password)->toBe($originalHash);

    $this->actingAs($admin)
        ->put(route('admin.config.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
            'roles' => [],
            'departments' => [],
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('new-secret-123', $user->fresh()->password))->toBeTrue();
});

test('password reset requires confirmation', function () {
    $admin = userWithPermission('access.users.manage');
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.config.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'new-secret-123',
            'roles' => [],
            'departments' => [],
        ])
        ->assertSessionHasErrors('password');
});

test('user search is case-insensitive', function () {
    $admin = userWithPermission('access.users.manage');
    User::factory()->create(['name' => 'John Smith']);

    $this->actingAs($admin)
        ->get(route('admin.config.users.index', ['search' => 'john']))
        ->assertInertia(fn ($page) => $page
            ->where('users.data', fn ($users) => collect($users)->contains('name', 'John Smith')));
});

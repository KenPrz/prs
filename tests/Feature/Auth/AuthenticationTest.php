<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use Spatie\Permission\Models\Role;

// AUTH-01
test('the login screen renders', function () {
    $this->get(route('login'))->assertOk();
});

// AUTH-01 happy path
test('a user can authenticate with valid credentials', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

// AUTH-02
test('a user cannot authenticate with an invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

// AUTH-03
test('a user cannot authenticate with an unknown email', function () {
    $this->post(route('login.store'), [
        'email' => 'nobody@example.com',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

// AUTH-04 empty/boundary
test('login requires email and password', function () {
    $this->post(route('login.store'), ['email' => '', 'password' => ''])
        ->assertSessionHasErrors(['email', 'password']);

    $this->assertGuest();
});

// AUTH-05 throttling
test('repeated failed logins are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertTooManyRequests();
});

// AUTH-06 2FA challenge
test('a user with two-factor enabled is redirected to the challenge', function () {
    $this->skipUnlessFortifyFeature(Features::twoFactorAuthentication());

    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

// AUTH-08 logout
test('an authenticated user can log out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));

    $this->assertGuest();
});

// AUTH-09 / AUTH-10 unauthorized access & session expiry both surface as the guest redirect
test('guests are redirected from protected routes to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('purchase-requisitions.index'))->assertRedirect(route('login'));
});

// AUTH-11 password confirmation wall
test('the password confirmation screen renders for an authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('password.confirm'))->assertOk();
});

// AUTH-12 admin-only middleware (EnsureSuperAdmin)
test('non-admins are forbidden from admin config routes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.config.departments.index'))->assertForbidden();
});

test('super admins can reach admin config routes', function () {
    Role::query()->firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $this->actingAs($admin)->get(route('admin.config.departments.index'))->assertOk();
});

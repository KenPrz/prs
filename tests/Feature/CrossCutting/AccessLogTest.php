<?php

use App\Models\AccessLog;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A throwaway route carrying the `web` middleware group, so the appended
 * LogRouteAccess middleware runs against it exactly as it would in the app.
 */
function pingRoute(): void
{
    Route::middleware('web')->get('/_test/ping', fn () => 'ok');
    Route::middleware('web')->post('/_test/ping', fn () => response('ok', 201));
}

it('logs a mutating request from an authed user', function () {
    pingRoute();
    $user = User::factory()->create();

    $this->actingAs($user)->post('/_test/ping')->assertStatus(201);

    $log = AccessLog::query()->first();
    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($user->id)
        ->and($log->method)->toBe('POST')
        ->and($log->path)->toBe('_test/ping')
        ->and($log->status)->toBe(201)
        ->and($log->event)->toBeNull();
});

it('does not log a GET request', function () {
    pingRoute();
    $user = User::factory()->create();

    $this->actingAs($user)->get('/_test/ping')->assertOk();

    expect(AccessLog::query()->count())->toBe(0);
});

it('logs a successful login', function () {
    $user = User::factory()->create();

    event(new Login('web', $user, false));

    $log = AccessLog::query()->where('event', 'login')->first();
    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($user->id);
});

it('logs a failed login with no user and the attempted email', function () {
    event(new Failed('web', null, ['email' => 'bad@example.com']));

    $log = AccessLog::query()->where('event', 'failed_login')->first();
    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBeNull()
        ->and($log->path)->toBe('bad@example.com');
});

it('blocks the access-log page without the permission', function () {
    $user = userWithPermission('pr.view');

    $this->actingAs($user)->get('/admin/config/access-logs')->assertForbidden();
});

it('shows filtered access logs to a permitted user', function () {
    $user = userWithPermission('access.logs.view');
    AccessLog::create(['event' => 'failed_login', 'path' => 'bad@example.com']);
    AccessLog::create(['event' => 'login', 'user_id' => $user->id, 'path' => 'login']);

    $this->withoutVite()->actingAs($user)
        ->get('/admin/config/access-logs?event=failed_login')
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/config/access-logs/index')
            ->has('logs.data', 1)
            ->where('logs.data.0.event', 'failed_login'),
        );
});

it('prunes rows older than the retention window but keeps fresh ones', function () {
    GeneralSettings::fake(['access_log_retention_days' => 180]);

    $old = AccessLog::create(['path' => 'old', 'created_at' => now()->subDays(181)]);
    $fresh = AccessLog::create(['path' => 'fresh', 'created_at' => now()->subDays(179)]);

    $this->artisan('model:prune', ['--model' => [AccessLog::class]])->assertSuccessful();

    expect(AccessLog::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(AccessLog::query()->whereKey($fresh->id)->exists())->toBeTrue();
});

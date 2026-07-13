<?php

use App\Models\User;
use App\Settings\FinanceSettings;
use App\Settings\GeneralSettings;
use Inertia\Testing\AssertableInertia as Assert;

// ── Access control ─────────────────────────────────────────────────────────

it('redirects guests to login', function () {
    $this->get('/admin/config/settings')->assertRedirect(route('login'));
});

it('blocks users without the settings permission', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/config/settings')->assertForbidden();

    $this->actingAs(userWithPermission('config.suppliers.manage'))
        ->put('/admin/config/settings', [])->assertForbidden();
});

it('lands a settings-only admin on the settings page', function () {
    $this->actingAs(userWithPermission('config.settings.manage'))
        ->get('/admin/config')
        ->assertRedirect(route('admin.config.settings.edit'));
});

// ── Rendering ──────────────────────────────────────────────────────────────

it('renders the settings form with the migrated defaults', function () {
    $this->actingAs(userWithPermission('config.settings.manage'))
        ->get('/admin/config/settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/config/settings/edit')
            ->where('settings.vat_rate_percent', 12)
            ->where('settings.currency_symbol', '₱')
            ->where('settings.records_per_page', 10)
            ->where('settings.access_log_retention_days', 180));
});

// ── Persistence ────────────────────────────────────────────────────────────

it('persists updated settings', function () {
    $this->actingAs(userWithPermission('config.settings.manage'))
        ->put('/admin/config/settings', [
            'vat_rate_percent' => 10,
            'currency_symbol' => '$',
            'records_per_page' => 25,
            'access_log_retention_days' => 365,
        ])
        ->assertRedirect('/admin/config/settings')
        ->assertSessionHas('success');

    expect(app(FinanceSettings::class)->vat_rate)->toBe(0.1)
        ->and(app(FinanceSettings::class)->currency_symbol)->toBe('$')
        ->and(app(GeneralSettings::class)->records_per_page)->toBe(25)
        ->and(app(GeneralSettings::class)->access_log_retention_days)->toBe(365);
});

it('persists a fractional vat percent and echoes it back on the form', function () {
    $admin = userWithPermission('config.settings.manage');

    $this->actingAs($admin)->put('/admin/config/settings', [
        'vat_rate_percent' => 7.5,
        'currency_symbol' => '₱',
        'records_per_page' => 10,
        'access_log_retention_days' => 180,
    ])->assertRedirect();

    expect(app(FinanceSettings::class)->vat_rate)->toBe(0.075);

    // Round-trip: the form shows the stored value as a percent again.
    $this->actingAs($admin)
        ->get('/admin/config/settings')
        ->assertInertia(fn (Assert $page) => $page
            ->where('settings.vat_rate_percent', 7.5));
});

it('accepts boundary values', function () {
    $this->actingAs(userWithPermission('config.settings.manage'))
        ->put('/admin/config/settings', [
            'vat_rate_percent' => 0,
            'currency_symbol' => 'PHP', // symbols need not be a single glyph
            'records_per_page' => 100,
            'access_log_retention_days' => 3650,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(app(FinanceSettings::class)->vat_rate)->toBe(0.0)
        ->and(app(GeneralSettings::class)->records_per_page)->toBe(100)
        ->and(app(GeneralSettings::class)->access_log_retention_days)->toBe(3650);
});

// ── Validation ─────────────────────────────────────────────────────────────

it('requires every field', function () {
    $this->actingAs(userWithPermission('config.settings.manage'))
        ->put('/admin/config/settings', [])
        ->assertSessionHasErrors([
            'vat_rate_percent',
            'currency_symbol',
            'records_per_page',
            'access_log_retention_days',
        ]);
});

it('rejects invalid values', function (array $payload, string $field) {
    $valid = [
        'vat_rate_percent' => 12,
        'currency_symbol' => '₱',
        'records_per_page' => 10,
        'access_log_retention_days' => 180,
    ];

    $this->actingAs(userWithPermission('config.settings.manage'))
        ->put('/admin/config/settings', array_merge($valid, $payload))
        ->assertSessionHasErrors($field);

    // Nothing was persisted from the rejected request.
    expect(app(FinanceSettings::class)->vat_rate)->toBe(0.12)
        ->and(app(GeneralSettings::class)->records_per_page)->toBe(10);
})->with([
    'vat above 100' => [['vat_rate_percent' => 100.01], 'vat_rate_percent'],
    'negative vat' => [['vat_rate_percent' => -1], 'vat_rate_percent'],
    'non-numeric vat' => [['vat_rate_percent' => 'twelve'], 'vat_rate_percent'],
    'symbol too long' => [['currency_symbol' => str_repeat('x', 11)], 'currency_symbol'],
    'zero page size' => [['records_per_page' => 0], 'records_per_page'],
    'page size above cap' => [['records_per_page' => 101], 'records_per_page'],
    'non-integer page size' => [['records_per_page' => 10.5], 'records_per_page'],
    'zero retention' => [['access_log_retention_days' => 0], 'access_log_retention_days'],
    'retention above cap' => [['access_log_retention_days' => 3651], 'access_log_retention_days'],
]);

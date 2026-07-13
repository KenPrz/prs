<?php

use App\Enums\PriceType;
use App\Models\AccessLog;
use App\Models\Address;
use App\Models\CompanyProfile;
use App\Models\Department;
use App\Models\PurchaseRequisition;
use App\Services\CarboneClient;
use App\Settings\FinanceSettings;
use App\Settings\GeneralSettings;
use Database\Seeders\DocumentSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
|--------------------------------------------------------------------------
| Settings consumption
|--------------------------------------------------------------------------
| The settings classes are only useful if every former hardcode actually
| reads them. Each block below covers one consumer: VAT math, document
| rendering, pagination defaults, access-log pruning, and the shared
| currency symbol.
*/

// ── PriceType VAT math ─────────────────────────────────────────────────────

it('computes vat math from the configured rate', function () {
    FinanceSettings::fake(['vat_rate' => 0.10]);

    expect(PriceType::VAT_INCLUSIVE->toNet(110.0))->toEqualWithDelta(100.0, 0.0001)
        ->and(PriceType::VAT_INCLUSIVE->vatAmount(110.0))->toEqualWithDelta(10.0, 0.0001)
        ->and(PriceType::VAT_INCLUSIVE->toGross(110.0))->toBe(110.0)
        ->and(PriceType::VAT_EXCLUSIVE->toGross(100.0))->toEqualWithDelta(110.0, 0.0001)
        ->and(PriceType::VAT_EXCLUSIVE->vatAmount(100.0))->toEqualWithDelta(10.0, 0.0001);
});

it('lets an explicit rate override the configured one', function () {
    FinanceSettings::fake(['vat_rate' => 0.10]);

    expect(PriceType::VAT_EXCLUSIVE->toGross(100.0, 0.05))->toEqualWithDelta(105.0, 0.0001)
        ->and(PriceType::VAT_INCLUSIVE->toNet(105.0, 0.05))->toEqualWithDelta(100.0, 0.0001);
});

it('keeps non-vat and zero-vat free of vat regardless of the rate', function () {
    FinanceSettings::fake(['vat_rate' => 0.50]);

    foreach ([PriceType::NON_VAT, PriceType::ZERO_VAT] as $type) {
        expect($type->vatAmount(100.0))->toBe(0.0)
            ->and($type->toNet(100.0))->toBe(100.0)
            ->and($type->toGross(100.0))->toBe(100.0);
    }
});

it('drives purchase order totals with the configured rate', function () {
    ['pr' => $pr, 'a' => $a, 'b' => $b, 'unit' => $unit, 'supplier' => $supplier] = prReadyForPo();
    // VAT-inclusive order, raw total 10×100 + 5×50 = 1250.
    $po = makeOrder($pr, $supplier, [
        ['line_item_id' => $a->id, 'quantity' => 10, 'unit_id' => $unit->id, 'price' => 100],
        ['line_item_id' => $b->id, 'quantity' => 5, 'unit_id' => $unit->id, 'price' => 50],
    ]);

    FinanceSettings::fake(['vat_rate' => 0.25]);

    expect($po->gross_total)->toBe(1250.0)
        ->and($po->net_total)->toEqualWithDelta(1000.0, 0.0001)
        ->and($po->vat_total)->toEqualWithDelta(250.0, 0.0001);
});

// ── Document rendering ─────────────────────────────────────────────────────

/** Render the PO pdf with Carbone mocked; returns the data sent to Carbone. */
function renderPoCapturing($test, $po, $user): array
{
    $test->seed(DocumentSeeder::class);

    $captured = null;
    $mock = Mockery::mock(CarboneClient::class);
    $mock->shouldReceive('render')->andReturnUsing(function ($template, $data) use (&$captured) {
        $captured = $data;

        return '%PDF-1.4 fake';
    });
    app()->instance(CarboneClient::class, $mock);

    // standalone=1: render just the PO, so the captured payload is the PO's
    // (otherwise the upstream PR renders after it and wins the capture).
    $test->actingAs($user)
        ->get(route('purchase-orders.render', ['purchase_order' => $po, 'standalone' => 1]))
        ->assertOk();

    return $captured;
}

it('renders the po vat rate label from the configured rate', function () {
    ['po' => $po, 'user' => $user] = orderedOrder();
    FinanceSettings::fake(['vat_rate' => 0.10]);

    expect(renderPoCapturing($this, $po, $user)['vat_rate'])->toBe('10%');
});

it('trims trailing zeros from a fractional vat rate label', function () {
    ['po' => $po, 'user' => $user] = orderedOrder();
    FinanceSettings::fake(['vat_rate' => 0.075]);

    expect(renderPoCapturing($this, $po, $user)['vat_rate'])->toBe('7.5%');
});

it('renders zero-vat orders at 0% regardless of the configured rate', function () {
    ['po' => $po, 'user' => $user] = orderedOrder();
    $po->forceFill(['price_type' => PriceType::ZERO_VAT])->save();
    FinanceSettings::fake(['vat_rate' => 0.10]);

    expect(renderPoCapturing($this, $po, $user)['vat_rate'])->toBe('0%');
});

it('falls back to the company profile address for bill-to and ship-to', function () {
    $profile = CompanyProfile::factory()->create(['name' => 'Acme Corp']);
    Address::factory()->for($profile)->create([
        'street' => '1 Main St',
        'city' => 'Makati',
        'province' => 'Metro Manila',
    ]);

    ['po' => $po, 'user' => $user] = orderedOrder(); // no bill-to/ship-to set
    $captured = renderPoCapturing($this, $po, $user);

    $expected = "Acme Corp\n1 Main St\nMakati, Metro Manila";
    expect($captured['bill_to_address'])->toBe($expected)
        ->and($captured['ship_to_address'])->toBe($expected);
});

it('renders a dash fallback when no company profile exists', function () {
    ['po' => $po, 'user' => $user] = orderedOrder();

    expect(CompanyProfile::count())->toBe(0)
        ->and(renderPoCapturing($this, $po, $user)['bill_to_address'])->toBe('—');
});

// ── Pagination defaults ────────────────────────────────────────────────────

it('pages admin lists by the configured size', function () {
    GeneralSettings::fake(['records_per_page' => 2]);
    foreach (range(1, 3) as $i) {
        Department::create(['name' => "Dept {$i}", 'code' => "D{$i}"]);
    }

    $this->actingAs(userWithPermission('config.departments.manage'))
        ->get('/admin/config/departments')
        ->assertInertia(fn (Assert $page) => $page
            ->has('departments.data', 2)
            ->where('departments.per_page', 2));
});

it('lets a per_page query override the configured size', function () {
    GeneralSettings::fake(['records_per_page' => 2]);
    foreach (range(1, 3) as $i) {
        Department::create(['name' => "Dept {$i}", 'code' => "D{$i}"]);
    }

    $this->actingAs(userWithPermission('config.departments.manage'))
        ->get('/admin/config/departments?per_page=50')
        ->assertInertia(fn (Assert $page) => $page->has('departments.data', 3));
});

it('pages domain lists by the configured size', function () {
    GeneralSettings::fake(['records_per_page' => 2]);
    $user = userWithPermission('pr.view');
    PurchaseRequisition::factory()->count(3)->for($user, 'requestor')->create();

    $this->actingAs($user)
        ->get(route('purchase-requisitions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('purchaseRequisitions.data', 2));
});

it('still caps domain lists at 100 per page', function () {
    $user = userWithPermission('pr.view');
    PurchaseRequisition::factory()->for($user, 'requestor')->create();

    $this->actingAs($user)
        ->get(route('purchase-requisitions.index', ['per_page' => 500]))
        ->assertSessionHasErrors('per_page');
});

// ── Access-log retention ───────────────────────────────────────────────────

it('prunes access logs by the configured retention window', function () {
    GeneralSettings::fake(['access_log_retention_days' => 30]);

    $old = AccessLog::create(['path' => 'old', 'created_at' => now()->subDays(31)]);
    $fresh = AccessLog::create(['path' => 'fresh', 'created_at' => now()->subDays(29)]);

    $this->artisan('model:prune', ['--model' => [AccessLog::class]])->assertSuccessful();

    expect(AccessLog::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(AccessLog::query()->whereKey($fresh->id)->exists())->toBeTrue();
});

// ── Shared currency symbol ─────────────────────────────────────────────────

it('shares the configured currency symbol with every inertia page', function () {
    FinanceSettings::fake(['currency_symbol' => '$']);
    $user = userWithPermission('pr.view');

    $this->actingAs($user)
        ->get(route('purchase-requisitions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('finance.currency_symbol', '$'));
});

it('reflects a saved currency symbol on subsequent pages end-to-end', function () {
    $admin = userWithPermission('config.settings.manage', 'pr.view');

    $this->actingAs($admin)->put('/admin/config/settings', [
        'vat_rate_percent' => 12,
        'currency_symbol' => 'USD',
        'records_per_page' => 10,
        'access_log_retention_days' => 180,
    ])->assertRedirect();

    $this->actingAs($admin)
        ->get(route('purchase-requisitions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('finance.currency_symbol', 'USD'));
});

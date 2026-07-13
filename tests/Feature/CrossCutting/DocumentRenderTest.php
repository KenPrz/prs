<?php

use App\Services\CarboneClient;
use Database\Seeders\DocumentSeeder;

// X-04 the requisition PDF excludes omitted line items
test('the requisition pdf excludes omitted line items', function () {
    $this->seed(DocumentSeeder::class);

    $captured = null;
    $this->mock(CarboneClient::class, function ($mock) use (&$captured) {
        $mock->shouldReceive('render')->andReturnUsing(function ($template, $data) use (&$captured) {
            $captured = $data;

            return '%PDF-1.4 fake';
        });
    });

    ['pr' => $pr, 'b' => $b, 'user' => $user] = prReadyForPo();
    $b->forceFill(['omitted_at' => now()])->save();

    $this->actingAs($user)
        ->get(route('purchase-requisitions.render', $pr))
        ->assertOk();

    $descriptions = collect($captured['items'])->pluck('description');
    expect($descriptions)->toContain('ALPHA')
        ->and($descriptions)->not->toContain('BRAVO');
});

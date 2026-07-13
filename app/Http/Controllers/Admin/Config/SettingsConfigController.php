<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Settings\FinanceSettings;
use App\Settings\GeneralSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsConfigController extends Controller
{
    /**
     * Show the form for editing the system settings (singleton).
     */
    public function edit(FinanceSettings $finance, GeneralSettings $general): Response
    {
        return Inertia::render('admin/config/settings/edit', [
            'settings' => [
                'vat_rate_percent' => round($finance->vat_rate * 100, 2),
                'currency_symbol' => $finance->currency_symbol,
                'records_per_page' => $general->records_per_page,
                'access_log_retention_days' => $general->access_log_retention_days,
            ],
        ]);
    }

    /**
     * Update the system settings.
     */
    public function update(Request $request, FinanceSettings $finance, GeneralSettings $general): RedirectResponse
    {
        $validated = $request->validate([
            'vat_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'currency_symbol' => ['required', 'string', 'max:10'],
            'records_per_page' => ['required', 'integer', 'min:1', 'max:100'],
            'access_log_retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);

        $finance->vat_rate = round($validated['vat_rate_percent'] / 100, 4);
        $finance->currency_symbol = $validated['currency_symbol'];
        $finance->save();

        $general->records_per_page = $validated['records_per_page'];
        $general->access_log_retention_days = $validated['access_log_retention_days'];
        $general->save();

        return to_route('admin.config.settings.edit')
            ->with('success', 'System settings updated successfully.');
    }
}

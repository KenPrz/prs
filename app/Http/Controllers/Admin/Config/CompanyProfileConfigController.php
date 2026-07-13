<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyProfileConfigController extends Controller
{
    /**
     * Show the form for editing the company profile (singleton).
     */
    public function edit(): Response
    {
        $profile = CompanyProfile::with('address')->first();

        return Inertia::render('admin/config/company-profile/edit', [
            'companyProfile' => $profile,
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    /**
     * Update the company profile.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile_no' => ['nullable', 'string', 'max:50'],
            'tin' => ['nullable', 'string', 'max:50'],
            'currency' => ['nullable', 'string', 'max:10'],
            'default_received_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'address.recipient_name' => ['nullable', 'string', 'max:255'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.barangay' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:255'],
            'address.province' => ['nullable', 'string', 'max:255'],
            'address.zip_code' => ['nullable', 'string', 'max:20'],
            'address.mobile_no' => ['nullable', 'string', 'max:50'],
        ]);

        $profile = CompanyProfile::firstOrNew();
        $profile->fill([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'mobile_no' => $validated['mobile_no'] ?? null,
            'tin' => $validated['tin'] ?? null,
            'currency' => $validated['currency'] ?? null,
            'default_received_by_user_id' => $validated['default_received_by_user_id'] ?? null,
        ]);
        $profile->save();

        if (isset($validated['address'])) {
            $profile->address()->updateOrCreate(
                ['company_profile_id' => $profile->id],
                $validated['address'],
            );
        }

        return to_route('admin.config.company-profile.edit')
            ->with('success', 'Company profile updated successfully.');
    }
}

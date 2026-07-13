<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Settings\GeneralSettings;
use App\Support\UserDeletionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserConfigController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(Request $request): Response
    {
        $query = User::query()
            ->with(['roles', 'departments']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereLike('name', "%{$search}%")
                    ->orWhereLike('email', "%{$search}%");
            });
        }

        return Inertia::render('admin/config/users/index', [
            'users' => $query->orderBy('name')->paginate($request->input('per_page', app(GeneralSettings::class)->records_per_page))->withQueryString(),
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): Response
    {
        return Inertia::render('admin/config/users/create', [
            'availableRoles' => Role::all(),
            'availableDepartments' => Department::all(),
        ]);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'roles' => ['array'],
            'roles.*' => ['exists:roles,name'],
            'departments' => ['array'],
            'departments.*' => ['exists:departments,id'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => \Hash::make($validated['password']),
            'email_verified_at' => now(), // Auto-verify since created by admin
        ]);

        // Sync Roles
        $user->syncRoles($validated['roles'] ?? []);

        // Sync Departments
        $user->departments()->sync($validated['departments'] ?? []);

        return redirect()->route('admin.config.users.index')
            ->with('success', "User {$user->name} created successfully.");
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user): Response
    {
        return Inertia::render('admin/config/users/edit', [
            'user' => $user->load(['roles', 'departments']),
            'availableRoles' => Role::all(),
            'availableDepartments' => Department::all(),
        ]);
    }

    /**
     * Update the specified user's details, roles, and departments.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'roles' => ['array'],
            'roles.*' => ['exists:roles,name'],
            'departments' => ['array'],
            'departments.*' => ['exists:departments,id'],
        ]);

        // Removing Super Admin from the last holder would lock everyone out.
        $losesSuperAdmin = $user->hasRole('Super Admin')
            && ! \in_array('Super Admin', $validated['roles'] ?? [], true);

        if ($losesSuperAdmin && $this->isLastSuperAdmin($user)) {
            return back()->with('error', 'This is the last Super Admin — assign the role to someone else before removing it.');
        }

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        // Admin-managed emails stay verified (config routes require `verified`).
        $user->email_verified_at ??= now();

        if (! empty($validated['password'])) {
            $user->password = \Hash::make($validated['password']);
        }

        $user->save();

        // Sync Roles
        $user->syncRoles($validated['roles'] ?? []);

        // Sync Departments
        $user->departments()->sync($validated['departments'] ?? []);

        return redirect()->route('admin.config.users.index')
            ->with('success', "User {$user->name} updated successfully.");
    }

    /**
     * Delete the specified user.
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete yourself.');
        }

        if ($user->hasRole('Super Admin') && $this->isLastSuperAdmin($user)) {
            return back()->with('error', 'This is the last Super Admin — assign the role to someone else before deleting this account.');
        }

        if ($blocker = UserDeletionGuard::blocker($user)) {
            return back()->with('error', $blocker);
        }

        $user->delete();

        return redirect()->route('admin.config.users.index')
            ->with('success', 'User deleted successfully.');
    }

    /**
     * True when no other user holds the Super Admin role.
     */
    private function isLastSuperAdmin(User $user): bool
    {
        return User::role('Super Admin')->whereKeyNot($user->id)->doesntExist();
    }
}

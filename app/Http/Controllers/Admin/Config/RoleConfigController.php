<?php

namespace App\Http\Controllers\Admin\Config;

use App\Enums\WorkflowAssigneeType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleConfigController extends Controller
{
    public function index(): Response
    {
        $roles = Role::query()
            ->withCount('users')
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'users_count' => $role->users_count,
                'permissions' => $role->permissions->map(fn (Permission $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                ]),
            ]);

        return Inertia::render('admin/config/roles/index', [
            'roles' => $roles,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/config/roles/create', [
            'availablePermissions' => Permission::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
        $role->syncPermissions($validated['permissions'] ?? []);

        return to_route('admin.config.roles.index')
            ->with('success', "Role \"{$role->name}\" created successfully.");
    }

    public function edit(Role $role): Response
    {
        $role->load('permissions');

        return Inertia::render('admin/config/roles/edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ],
            'availablePermissions' => Permission::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', "unique:roles,name,{$role->id}"],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        if ($validated['name'] !== $role->name) {
            // Gate::before and the admin boundary key off this exact name.
            if ($role->name === 'Super Admin') {
                return back()->with('error', 'The Super Admin role cannot be renamed — system access control depends on it.');
            }

            // Workflow steps resolve approvers by role name; renaming would
            // silently break those steps.
            $referencedByWorkflows = DB::table('workflow_step_assignee_definitions')
                ->where('assignee_type', WorkflowAssigneeType::Role->value)
                ->where('assignee_identifier', $role->name)
                ->exists();

            if ($referencedByWorkflows) {
                return back()->with('error', "The role \"{$role->name}\" is used as an approver in workflow steps and cannot be renamed. Update the workflows first.");
            }
        }

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions'] ?? []);

        return to_route('admin.config.roles.index')
            ->with('success', "Role \"{$role->name}\" updated successfully.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->name === 'Super Admin') {
            return back()->with('error', 'The Super Admin role cannot be deleted.');
        }

        $referencedByWorkflows = DB::table('workflow_step_assignee_definitions')
            ->where('assignee_type', WorkflowAssigneeType::Role->value)
            ->where('assignee_identifier', $role->name)
            ->exists();

        if ($referencedByWorkflows) {
            return back()->with('error', "The role \"{$role->name}\" is used as an approver in workflow steps and cannot be deleted. Update the workflows first.");
        }

        $role->delete();

        return to_route('admin.config.roles.index')
            ->with('success', 'Role deleted successfully.');
    }
}

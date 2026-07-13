<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentConfigController extends Controller
{
    /**
     * Display a listing of departments.
     */
    public function index(Request $request): Response
    {
        $departments = Department::query()
            ->with('departmentHead:id,name')
            ->when($request->input('search'), function ($query, $search) {
                $query->whereLike('name', "%{$search}%")
                    ->orWhereLike('code', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', app(GeneralSettings::class)->records_per_page))
            ->withQueryString();

        return Inertia::render('admin/config/departments/index', [
            'departments' => $departments,
        ]);
    }

    /**
     * Show the form for creating a new department.
     */
    public function create(): Response
    {
        return Inertia::render('admin/config/departments/create', [
            'users' => $this->userOptions(),
        ]);
    }

    /**
     * Store a newly created department.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:departments,code'],
            'department_head_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        Department::create($validated);

        return to_route('admin.config.departments.index')
            ->with('success', 'Department created successfully.');
    }

    /**
     * Show the form for editing a department.
     */
    public function edit(Department $department): Response
    {
        return Inertia::render('admin/config/departments/edit', [
            'department' => $department,
            'users' => $this->userOptions(),
        ]);
    }

    /**
     * Update the specified department.
     */
    public function update(Request $request, Department $department): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', "unique:departments,code,{$department->id}"],
            'department_head_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $department->update($validated);

        return to_route('admin.config.departments.index')
            ->with('success', 'Department updated successfully.');
    }

    /**
     * Remove the specified department.
     */
    public function destroy(Department $department): RedirectResponse
    {
        // Pivots cascade, so a delete would silently detach members and
        // requisition associations — require emptying the department first.
        if ($department->users()->exists() || $department->purchaseRequisitions()->exists()) {
            return back()->with('error', 'This department has members or is referenced by requisitions and cannot be deleted.');
        }

        $department->delete();

        return to_route('admin.config.departments.index')
            ->with('success', 'Department deleted successfully.');
    }

    /**
     * The user options for the department-head select.
     *
     * @return Collection<int, User>
     */
    private function userOptions()
    {
        return User::query()->orderBy('name')->get(['id', 'name', 'email']);
    }
}

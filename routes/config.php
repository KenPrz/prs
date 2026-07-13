<?php

use App\Http\Controllers\Admin\Config\AccessLogConfigController;
use App\Http\Controllers\Admin\Config\CompanyProfileConfigController;
use App\Http\Controllers\Admin\Config\DepartmentConfigController;
use App\Http\Controllers\Admin\Config\DocumentConfigController;
use App\Http\Controllers\Admin\Config\ItemUnitConfigController;
use App\Http\Controllers\Admin\Config\RoleConfigController;
use App\Http\Controllers\Admin\Config\SupplierConfigController;
use App\Http\Controllers\Admin\Config\UserConfigController;
use App\Http\Controllers\Admin\Config\WorkflowConfigController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'config.access'])
    ->prefix('admin/config')
    ->name('admin.config.')
    ->group(function () {
        // Land on the first section the user is permitted to manage.
        Route::get('/', function (Request $request) {
            $sections = [
                'config.workflows.manage' => 'admin.config.workflows.index',
                'config.suppliers.manage' => 'admin.config.suppliers.index',
                'config.departments.manage' => 'admin.config.departments.index',
                'config.item_units.manage' => 'admin.config.item-units.index',
                'config.documents.manage' => 'admin.config.documents.index',
                'config.company_profile.manage' => 'admin.config.company-profile.edit',
                'access.users.manage' => 'admin.config.users.index',
                'access.roles.manage' => 'admin.config.roles.index',
                'access.logs.view' => 'admin.config.access-logs.index',
            ];

            foreach ($sections as $ability => $route) {
                if ($request->user()?->can($ability)) {
                    return redirect()->route($route);
                }
            }

            abort(403);
        });

        Route::resource('workflows', WorkflowConfigController::class)->except(['show'])->middleware('can:config.workflows.manage');
        Route::resource('departments', DepartmentConfigController::class)->except(['show'])->middleware('can:config.departments.manage');
        Route::resource('suppliers', SupplierConfigController::class)->except(['show'])->middleware('can:config.suppliers.manage');
        Route::resource('item-units', ItemUnitConfigController::class)->except(['show'])->middleware('can:config.item_units.manage');
        Route::get('documents/{document}/template', [DocumentConfigController::class, 'downloadTemplate'])
            ->middleware('can:config.documents.manage')
            ->name('documents.template');
        // Replace-only: the set of document types is code-defined; admins can
        // only swap DOCX templates, never create or delete type rows.
        Route::resource('documents', DocumentConfigController::class)->only(['index', 'edit', 'update'])->middleware('can:config.documents.manage');
        Route::resource('users', UserConfigController::class)->except(['show'])->middleware('can:access.users.manage');
        Route::resource('roles', RoleConfigController::class)->except(['show'])->middleware('can:access.roles.manage');

        Route::get('access-logs', AccessLogConfigController::class)
            ->name('access-logs.index')
            ->middleware('can:access.logs.view');

        Route::middleware('can:config.company_profile.manage')->group(function () {
            Route::get('company-profile', [CompanyProfileConfigController::class, 'edit'])->name('company-profile.edit');
            Route::put('company-profile', [CompanyProfileConfigController::class, 'update'])->name('company-profile.update');
        });
    });

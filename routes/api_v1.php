<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Lookups\DepartmentController as DepartmentLookupController;
use App\Http\Controllers\Api\V1\Lookups\ItemUnitController as ItemUnitLookupController;
use App\Http\Controllers\Api\V1\Lookups\SupplierController as SupplierLookupController;
use App\Http\Controllers\Api\V1\Lookups\WorkflowController as WorkflowLookupController;
use App\Http\Controllers\Api\V1\PaymentRequestFormController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\PurchaseRequisitionController;
use App\Http\Controllers\Api\V1\ReceivingReportController;
use Illuminate\Support\Facades\Route;

// Auth — login is unauthenticated
Route::prefix('auth')->group(function (): void {
    Route::post('login', LoginController::class)->name('api.v1.auth.login');
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', LogoutController::class)->name('api.v1.auth.logout');
        Route::get('me', MeController::class)->name('api.v1.auth.me');
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    // Procurement documents (read/CRUD only). The API workflow submit/decide
    // endpoints were retired in the workflow-engine rebuild; workflow actions are
    // now driven through the web WorkflowController.
    Route::apiResource('purchase-requisitions', PurchaseRequisitionController::class)
        ->names('api.v1.purchase-requisitions');

    Route::apiResource('purchase-orders', PurchaseOrderController::class)
        ->names('api.v1.purchase-orders');

    Route::apiResource('receiving-reports', ReceivingReportController::class)
        ->except(['update'])
        ->names('api.v1.receiving-reports');

    Route::apiResource('payment-request-forms', PaymentRequestFormController::class)
        ->names('api.v1.payment-request-forms');

    // Lookup / reference data (read-only)
    Route::get('suppliers', SupplierLookupController::class)->name('api.v1.suppliers.index');
    Route::get('departments', DepartmentLookupController::class)->name('api.v1.departments.index');
    Route::get('item-units', ItemUnitLookupController::class)->name('api.v1.item-units.index');
    Route::get('workflows', WorkflowLookupController::class)->name('api.v1.workflows.index');

    // Authenticated user profile
    Route::get('profile', ProfileController::class)->name('api.v1.profile');
});

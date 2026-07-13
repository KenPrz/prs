<?php

use App\Http\Controllers\CancelPaymentRequestFormController;
use App\Http\Controllers\CancelPurchaseOrderController;
use App\Http\Controllers\CancelPurchaseRequisitionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DownloadMediaAttachmentController;
use App\Http\Controllers\MarkPurchaseOrderAsOrderedController;
use App\Http\Controllers\MarkPurchaseRequisitionReadyForPoController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentRequestFormController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseRequisitionController;
use App\Http\Controllers\ReceivingReportController;
use App\Http\Controllers\RenderPaymentRequestFormPdfController;
use App\Http\Controllers\RenderPurchaseOrderPdfController;
use App\Http\Controllers\RenderPurchaseRequisitionPdfController;
use App\Http\Controllers\RenderReceivingReportPdfController;
use App\Http\Controllers\ToggleLineItemOmissionController;
use App\Http\Controllers\TogglePurchaseOrderItemOmissionController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('attachments/{media}', DownloadMediaAttachmentController::class)->name('attachments.download');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::get('purchase-requisitions/{purchase_requisition}/render', RenderPurchaseRequisitionPdfController::class)
        ->name('purchase-requisitions.render');
    Route::post('purchase-requisitions/{purchase_requisition}/mark-ready-for-po', MarkPurchaseRequisitionReadyForPoController::class)
        ->name('purchase-requisitions.mark-ready-for-po');
    Route::post('purchase-requisitions/{purchase_requisition}/cancel', CancelPurchaseRequisitionController::class)
        ->name('purchase-requisitions.cancel');
    Route::post('line-items/{line_item}/omission', ToggleLineItemOmissionController::class)
        ->name('line-items.omission');
    Route::resource('purchase-requisitions', PurchaseRequisitionController::class);
    Route::get('purchase-orders/{purchase_order}/render', RenderPurchaseOrderPdfController::class)
        ->name('purchase-orders.render');
    Route::post('purchase-orders/{purchase_order}/mark-as-ordered', MarkPurchaseOrderAsOrderedController::class)
        ->name('purchase-orders.mark-as-ordered');
    Route::post('purchase-orders/{purchase_order}/cancel', CancelPurchaseOrderController::class)
        ->name('purchase-orders.cancel');
    Route::post('purchase-order-items/{purchase_order_item}/omission', TogglePurchaseOrderItemOmissionController::class)
        ->name('purchase-order-items.omission');
    Route::resource('purchase-orders', PurchaseOrderController::class);
    Route::get('receiving-reports/{receiving_report}/render', RenderReceivingReportPdfController::class)
        ->name('receiving-reports.render');
    Route::resource('receiving-reports', ReceivingReportController::class);
    Route::get('payment-request-forms/{payment_request_form}/render', RenderPaymentRequestFormPdfController::class)
        ->name('payment-request-forms.render');
    Route::post('payment-request-forms/{payment_request_form}/cancel', CancelPaymentRequestFormController::class)
        ->name('payment-request-forms.cancel');
    Route::resource('payment-request-forms', PaymentRequestFormController::class);

    // Single, document-agnostic workflow entry point. {type} is one of pr|po|rr|prf.
    Route::post('workflows/{type}/{id}/submit', [WorkflowController::class, 'submit'])->name('workflows.submit');
    Route::post('workflows/{type}/{id}/act', [WorkflowController::class, 'act'])->name('workflows.act');
    Route::post('workflows/{type}/{id}/reassign', [WorkflowController::class, 'reassign'])->name('workflows.reassign');
    Route::post('workflows/{type}/{id}/cancel', [WorkflowController::class, 'cancel'])->name('workflows.cancel');
});

require __DIR__.'/settings.php';
require __DIR__.'/config.php';

<?php

use App\Livewire\PurchaseRequisitions\Form as PurchaseRequisitionForm;
use App\Livewire\PurchaseRequisitions\Index as PurchaseRequisitionIndex;
use App\Livewire\PurchaseRequisitions\Show as PurchaseRequisitionShow;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('purchase-requisitions', PurchaseRequisitionIndex::class)->name('purchase-requisitions.index');
    Route::livewire('purchase-requisitions/create', PurchaseRequisitionForm::class)->name('purchase-requisitions.create');
    Route::livewire('purchase-requisitions/{purchaseRequisition}/edit', PurchaseRequisitionForm::class)->name('purchase-requisitions.edit');
    Route::livewire('purchase-requisitions/{purchaseRequisition}', PurchaseRequisitionShow::class)->name('purchase-requisitions.show');
});

require __DIR__.'/settings.php';

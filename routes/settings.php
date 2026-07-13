<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SignatureController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    Route::get('settings/signatures', [SignatureController::class, 'index'])->name('signatures.index');
    Route::get('settings/signatures/{signature}/image', [SignatureController::class, 'image'])->name('signatures.image');
    Route::post('settings/signatures', [SignatureController::class, 'store'])->name('signatures.store');
    Route::put('settings/signatures/{signature}/active', [SignatureController::class, 'update'])->name('signatures.update');
    Route::delete('settings/signatures/{signature}', [SignatureController::class, 'destroy'])->name('signatures.destroy');
});

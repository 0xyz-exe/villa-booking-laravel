<?php

use App\Http\Controllers\VillaController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [VillaController::class, 'index'])->name('villas.index');
Route::get('/villas/{villa}', [VillaController::class, 'show'])->name('villas.show');
Route::redirect('/dashboard', '/owner')->middleware('auth')->name('dashboard');
Route::middleware('auth')->group(function () {
    Route::post('/reservations', [VillaController::class, 'store'])->name('reservations.store');
    Route::get('/reservations/{reservation}', [VillaController::class, 'confirmation'])->name('reservations.show');
    Route::get('/my-bookings', [VillaController::class, 'mine'])->name('reservations.mine');
    Route::patch('/reservations/{reservation}/cancel', [VillaController::class, 'cancel'])->name('reservations.cancel');
    Route::post('/reservations/{reservation}/review', [VillaController::class, 'review'])->name('reservations.review');
    Route::get('/owner', [VillaController::class, 'dashboard'])->name('owner.dashboard');
    Route::post('/owner/villas', [VillaController::class, 'saveVilla'])->name('owner.villas.store');
    Route::put('/owner/villas/{villa}', [VillaController::class, 'saveVilla'])->name('owner.villas.update');
    Route::post('/owner/seasonal-rates', [VillaController::class, 'seasonalRate'])->name('owner.seasonal-rates.store');
    Route::patch('/owner/reservations/{reservation}', [VillaController::class, 'updateStatus'])->name('owner.reservations.update');
    Route::get('/owner/report.csv', [VillaController::class, 'report'])->name('owner.report');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

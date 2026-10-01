<?php

use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\CheckAvailabilityController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('availability', [AvailabilityController::class, 'index'])->name('availability.index');
Route::post('availability/check', CheckAvailabilityController::class)->name('availability.check');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

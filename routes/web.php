<?php

use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BookingReviewController;
use App\Http\Controllers\CheckAvailabilityController;
use App\Http\Controllers\QuotePricingController;
use App\Http\Controllers\StoreBookingRequestController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('availability', [AvailabilityController::class, 'index'])->name('availability.index');
Route::post('availability/check', CheckAvailabilityController::class)->name('availability.check');
Route::post('pricing/quote', QuotePricingController::class)->name('pricing.quote');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::get('bookings/review', BookingReviewController::class)->name('bookings.review');
    Route::post('bookings', StoreBookingRequestController::class)->name('bookings.store');
});

require __DIR__.'/settings.php';

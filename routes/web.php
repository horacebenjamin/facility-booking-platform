<?php

use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BookingPaymentController;
use App\Http\Controllers\BookingReviewController;
use App\Http\Controllers\CheckAvailabilityController;
use App\Http\Controllers\CustomerBookingController;
use App\Http\Controllers\CustomerInvoiceController;
use App\Http\Controllers\CustomerNotificationController;
use App\Http\Controllers\PreviewRecurringBookingRequestController;
use App\Http\Controllers\QuotePricingController;
use App\Http\Controllers\StoreBookingRequestController;
use App\Http\Controllers\StoreRecurringBookingRequestController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Middleware\EnsureCustomerRole;
use App\Http\Middleware\VerifyStripePaymentWebhook;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('availability', [AvailabilityController::class, 'index'])->name('availability.index');
Route::post('availability/check', CheckAvailabilityController::class)->name('availability.check');
Route::post('pricing/quote', QuotePricingController::class)->name('pricing.quote');
Route::post('payments/stripe/webhook', StripeWebhookController::class)
    ->middleware(VerifyStripePaymentWebhook::class)->name('payments.stripe.webhook');

Route::inertia('dashboard', 'Dashboard')->middleware(['auth', 'verified', EnsureCustomerRole::class.':redirect'])->name('dashboard');

Route::middleware(['auth', 'verified', EnsureCustomerRole::class])->group(function () {
    Route::get('notifications', [CustomerNotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/{notification}/read', [CustomerNotificationController::class, 'read'])->name('notifications.read');
    Route::get('invoices', [CustomerInvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/{invoice}', [CustomerInvoiceController::class, 'show'])->name('invoices.show');
    Route::get('bookings', [CustomerBookingController::class, 'index'])->name('bookings.index');
    Route::get('bookings/{booking}/payment', [BookingPaymentController::class, 'show'])->name('bookings.payment.show');
    Route::post('bookings/{booking}/payment', [BookingPaymentController::class, 'store'])
        ->middleware('throttle:10,1')->name('bookings.payment.store');
    Route::get('bookings/review', BookingReviewController::class)->name('bookings.review');
    Route::get('bookings/{booking}', [CustomerBookingController::class, 'show'])->name('bookings.show');
    Route::post('bookings/{booking}/cancel', [CustomerBookingController::class, 'cancel'])->name('bookings.cancel');
    Route::patch('bookings/{booking}', [CustomerBookingController::class, 'amend'])->name('bookings.amend');
    Route::post('bookings', StoreBookingRequestController::class)->name('bookings.store');
    Route::post('bookings/recurring/preview', PreviewRecurringBookingRequestController::class)->name('bookings.recurring.preview');
    Route::post('bookings/recurring', StoreRecurringBookingRequestController::class)->name('bookings.recurring.store');
});

require __DIR__.'/settings.php';

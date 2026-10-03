<?php

namespace App\Actions;

use App\Enums\BookingStatus;
use App\Enums\FinancialStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentUnavailable;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\BookingPaymentEligibility;
use Carbon\CarbonInterface;

class ConfirmPaidBooking
{
    public function __construct(private BookingPaymentEligibility $eligibility) {}

    /**
     * Called only by reconciliation while holding the Booking then Payment row locks.
     */
    public function handle(Booking $booking, Payment $payment, CarbonInterface $paidAt): bool
    {
        if ($payment->status !== PaymentStatus::Succeeded || $payment->booking_id !== $booking->id
            || $payment->customer_id !== $booking->customer_id
            || $booking->payment_due_at === null || $paidAt->gt($booking->payment_due_at)
            || $paidAt->gt($payment->session_expires_at)) {
            return false;
        }

        try {
            $this->eligibility->assertLifecycle($booking, now());
            $this->eligibility->assertProtection($booking);
            $snapshot = $this->eligibility->snapshot($booking);
        } catch (PaymentUnavailable) {
            return false;
        }

        if ($snapshot->final_total_minor !== $payment->amount_minor || $snapshot->currency !== $payment->currency) {
            return false;
        }

        $booking->update(['status' => BookingStatus::Confirmed, 'financial_status' => FinancialStatus::Paid]);
        activity('booking')->performedOn($booking)->event('booking.confirmed')
            ->withProperties(['payment_reference' => $payment->reference])
            ->log('Booking confirmed after verified payment');

        return true;
    }
}

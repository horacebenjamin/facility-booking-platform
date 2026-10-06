<?php

namespace App\Services;

use App\Enums\AttendanceState;
use App\Enums\BookingStatus;
use App\Enums\FinancialStatus;
use App\Models\Booking;
use Carbon\CarbonInterface;

class CustomerBookingManagementPolicy
{
    public function isExpired(Booking $booking, CarbonInterface $evaluatedAt): bool
    {
        return $booking->status === BookingStatus::Approved
            && $booking->financial_status === FinancialStatus::AwaitingPayment
            && $booking->payment_due_at !== null
            && $booking->payment_due_at->lte($evaluatedAt);
    }

    public function cancellationUnavailableReason(Booking $booking, CarbonInterface $evaluatedAt): ?string
    {
        if (! config('booking.cancellation.enabled')) {
            return 'Customer cancellation is not currently available for this booking.';
        }

        if (! in_array($booking->status->value, config('booking.cancellation.customer_allowed_statuses', []), true)) {
            return 'This booking cannot be cancelled in its current state.';
        }

        if ($booking->attendance_state !== AttendanceState::Expected) {
            return 'A booking with recorded attendance cannot be cancelled.';
        }

        if ($this->isExpired($booking, $evaluatedAt)) {
            return 'This booking has expired because its payment deadline passed.';
        }

        if ($booking->starts_at->lte($evaluatedAt)) {
            return 'A booking cannot be cancelled after it has started.';
        }

        $minimumNoticeMinutes = max(0, (int) config('booking.cancellation.minimum_notice_minutes', 0));

        if ($booking->starts_at->lte($evaluatedAt->copy()->addMinutes($minimumNoticeMinutes))) {
            return 'This booking is inside the configured cancellation notice period.';
        }

        return null;
    }

    public function amendmentUnavailableReason(Booking $booking, CarbonInterface $evaluatedAt): ?string
    {
        if (! in_array($booking->status->value, config('booking.amendment.customer_allowed_statuses', []), true)) {
            return 'This booking cannot be amended in its current state.';
        }

        if ($booking->attendance_state !== AttendanceState::Expected) {
            return 'A booking with recorded attendance cannot be amended.';
        }

        if ($booking->starts_at->lte($evaluatedAt)) {
            return 'A booking cannot be amended after it has started.';
        }

        return null;
    }

    public function financialFollowUp(Booking $booking): string
    {
        return match ($booking->financial_status) {
            FinancialStatus::Paid,
            FinancialStatus::InvoiceOutstanding,
            FinancialStatus::Invoiced => 'manual_review_required',
            default => 'none',
        };
    }
}

<?php

namespace App\Services;

use App\Enums\AttendanceState;
use App\Enums\BillingMethod;
use App\Enums\BookingStatus;
use App\Enums\FinancialStatus;
use App\Models\Booking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Spatie\Activitylog\Models\Activity;

class CustomerBookingPresenter
{
    public function __construct(private CustomerBookingManagementPolicy $managementPolicy) {}

    /** @return array<string, mixed> */
    public function summary(Booking $booking, User $customer): array
    {
        $evaluatedAt = CarbonImmutable::now(config('app.timezone'));
        $status = $this->status($booking, $evaluatedAt);
        $invoice = $booking->invoiceLines->first()?->invoice;

        return [
            'id' => $booking->id,
            'reference' => $booking->reference,
            'status' => $status['value'],
            'status_label' => $status['label'],
            'financial_status' => $booking->financial_status->value,
            'financial_status_label' => $booking->financial_status->label(),
            'billing_method' => $booking->billing_method->value,
            'resource_name' => $booking->resource->name,
            'facility_name' => $booking->facility->name,
            'centre_name' => $booking->centre->name,
            'starts_at' => $booking->starts_at->toIso8601String(),
            'ends_at' => $booking->ends_at->toIso8601String(),
            'payment_due_at' => $booking->payment_due_at?->toIso8601String(),
            'invoice' => $invoice === null ? null : [
                'id' => $invoice->id,
                'reference' => $invoice->reference,
            ],
            'is_recurring' => $booking->booking_series_id !== null,
            'occurrence_index' => $booking->occurrence_index,
            'occurrence_count' => $booking->series?->occurrence_count,
            'can_pay' => $status['value'] === 'awaiting_payment'
                && $booking->billing_method === BillingMethod::Card
                && $booking->payment_due_at?->isFuture() === true,
            'can_cancel' => $this->managementPolicy->cancellationUnavailableReason($booking, $evaluatedAt) === null,
            'cancellation_unavailable_reason' => $this->managementPolicy->cancellationUnavailableReason($booking, $evaluatedAt),
            'can_amend' => $this->managementPolicy->amendmentUnavailableReason($booking, $evaluatedAt) === null,
            'amendment_unavailable_reason' => $this->managementPolicy->amendmentUnavailableReason($booking, $evaluatedAt),
        ];
    }

    /** @return array<string, mixed> */
    public function detail(Booking $booking, User $customer): array
    {
        $summary = $this->summary($booking, $customer);
        $summary['next_step'] = $this->nextStep($booking, $summary['status']);
        $summary['financial_message'] = $this->financialMessage($booking, $summary['status']);
        $summary['history'] = $this->history($booking);
        $summary['recurring'] = $this->recurring($booking, $customer);

        return $summary;
    }

    /** @return array{value: string, label: string} */
    private function status(Booking $booking, CarbonImmutable $evaluatedAt): array
    {
        if ($booking->attendance_state === AttendanceState::Completed) {
            return ['value' => 'completed', 'label' => 'Completed'];
        }

        if ($booking->status === BookingStatus::Cancelled) {
            return ['value' => 'cancelled', 'label' => 'Cancelled'];
        }

        if ($booking->status === BookingStatus::Rejected) {
            return ['value' => 'rejected', 'label' => 'Rejected'];
        }

        if ($this->managementPolicy->isExpired($booking, $evaluatedAt)) {
            return ['value' => 'expired', 'label' => 'Expired — payment deadline passed'];
        }

        if ($booking->status === BookingStatus::Requested) {
            return ['value' => 'requested', 'label' => 'Awaiting management approval'];
        }

        if ($booking->status === BookingStatus::Approved && $booking->financial_status === FinancialStatus::AwaitingPayment) {
            return ['value' => 'awaiting_payment', 'label' => 'Awaiting payment'];
        }

        return ['value' => 'confirmed', 'label' => 'Confirmed'];
    }

    private function nextStep(Booking $booking, string $status): string
    {
        return match ($status) {
            'requested' => 'The centre is reviewing your request. We will update this booking when a decision is made.',
            'awaiting_payment' => 'Complete the required payment before the payment deadline to confirm this booking.',
            'expired' => 'The payment deadline passed. Please contact the centre if you still need this booking.',
            'confirmed' => $booking->billing_method === BillingMethod::Invoice
                ? 'Your booking is confirmed under invoice terms.'
                : 'Your booking is confirmed. We look forward to seeing you.',
            'completed' => 'This booking has been completed.',
            'cancelled' => 'This booking has been cancelled. Contact the centre if you need help with the financial follow-up.',
            'rejected' => 'This request was not accepted. Contact the centre if you would like to discuss another option.',
            default => 'Check the booking details for the latest information.',
        };
    }

    private function financialMessage(Booking $booking, string $status): ?string
    {
        if ($status !== 'cancelled' || $this->managementPolicy->financialFollowUp($booking) === 'none') {
            return null;
        }

        return 'The financial record was not changed automatically. Contact the centre about any applicable adjustment or refund.';
    }

    /** @return list<array<string, mixed>> */
    private function history(Booking $booking): array
    {
        return array_values($booking->activities
            ->sortBy('created_at')
            ->map(function (Activity $activity): ?array {
                $entry = match ($activity->event) {
                    'booking.requested' => ['title' => 'Booking request submitted', 'description' => 'Your request was received.'],
                    'booking.approved' => [
                        'title' => $activity->getProperty('financial_status') === FinancialStatus::AwaitingPayment->value
                            ? 'Booking approved — payment required'
                            : 'Booking approved',
                        'description' => 'The centre approved your request.',
                    ],
                    'booking.confirmed', 'booking.confirmed_under_invoice_terms' => ['title' => 'Booking confirmed', 'description' => 'Your booking is confirmed.'],
                    'booking.rejected' => ['title' => 'Booking request rejected', 'description' => $this->reasonDescription($activity, 'The centre did not accept this request.')],
                    'booking.cancelled' => ['title' => 'Booking cancelled', 'description' => $this->reasonDescription($activity, 'This booking was cancelled.')],
                    'booking.amended' => ['title' => 'Booking amended', 'description' => 'The booking date, time or resource was changed.'],
                    'booking.completed' => ['title' => 'Booking completed', 'description' => 'The session was marked as completed.'],
                    default => null,
                };

                if ($entry === null) {
                    return null;
                }

                return [
                    ...$entry,
                    'event' => $activity->event,
                    'occurred_at' => $activity->created_at->toIso8601String(),
                ];
            })
            ->filter()
            ->values()
            ->all());
    }

    private function reasonDescription(Activity $activity, string $fallback): string
    {
        $reason = trim((string) $activity->getProperty('reason'));

        return $reason === '' ? $fallback : 'Reason: '.$reason;
    }

    /** @return array<string, mixed>|null */
    private function recurring(Booking $booking, User $customer): ?array
    {
        if ($booking->series === null) {
            return null;
        }

        return [
            'occurrence_index' => $booking->occurrence_index,
            'occurrence_count' => $booking->series->occurrence_count,
            'occurrences' => $booking->series->bookings
                ->filter(fn (Booking $occurrence): bool => $occurrence->customer_id === $customer->id)
                ->map(function (Booking $occurrence): array {
                    $status = $this->status($occurrence, CarbonImmutable::now(config('app.timezone')));

                    return [
                        'id' => $occurrence->id,
                        'reference' => $occurrence->reference,
                        'occurrence_index' => $occurrence->occurrence_index,
                        'starts_at' => $occurrence->starts_at->toIso8601String(),
                        'status' => $status['value'],
                        'status_label' => $status['label'],
                    ];
                })->values()->all(),
        ];
    }
}

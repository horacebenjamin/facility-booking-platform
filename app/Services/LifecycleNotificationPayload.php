<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

class LifecycleNotificationPayload
{
    public const SupportedEvents = ['booking.requested', 'booking.approved', 'booking.confirmed', 'booking.confirmed_under_invoice_terms', 'booking.rejected', 'payment.succeeded', 'payment.manual_recorded', 'invoice.issued'];

    /** @return array{customer_id: int, type: string, semantic_key: string, payload: array{type: string, title: string, body: string, action_label: string, action_url: string, occurred_at: string}}|null */
    public function fromActivity(Activity $activity): ?array
    {
        $subject = $activity->subject;
        $event = $activity->event;
        $label = 'View bookings';
        $url = route('bookings.index', absolute: false);
        if ($subject instanceof Booking) {
            $responsible = $subject;
            $context = 'Booking '.$subject->reference;
            if ($subject->occurrence_index !== null) {
                $context .= ' (recurring occurrence '.$subject->occurrence_index.')';
            }
            [$type, $title, $body] = match ($event) {
                'booking.requested' => ['booking.requested', 'Booking request received', $context.' was received for management review. This acknowledges your request; it is not a booking confirmation. Check your bookings for the current status.'],
                'booking.approved' => $activity->getProperty('financial_status') === 'awaiting_payment' ? ['booking.approved_payment_required', 'Booking approved — payment required', $context.' was approved. At approval, payment was required before confirmation. The payment deadline set at approval is '.$activity->getProperty('payment_due_at').'. Check the booking page for its current payment status.'] : [null, null, null],
                'booking.confirmed' => ['booking.confirmed', 'Booking confirmed', $context.' was confirmed following payment.'],
                'booking.confirmed_under_invoice_terms' => ['booking.confirmed', 'Booking confirmed under invoice terms', $context.' was confirmed under your authorised invoice terms. Its charge was outstanding at confirmation; confirmation does not mean it was paid.'],
                'booking.rejected' => ['booking.rejected', 'Booking request rejected', $context.' was rejected. Reason: '.(string) $activity->getProperty('reason')],
                default => [null, null, null],
            };
            if ($event === 'booking.approved') {
                $label = 'View payment status';
                $url = route('bookings.payment.show', $subject, absolute: false);
            }
        } elseif ($subject instanceof Payment && in_array($event, ['payment.succeeded', 'payment.manual_recorded'], true) && $subject->status === PaymentStatus::Succeeded) {
            $obligation = $event === 'payment.manual_recorded' ? $subject->invoice : $subject->booking;
            if ($obligation === null || $obligation->customer_id !== $subject->customer_id) {
                return null;
            }
            $responsible = $obligation;
            $type = 'payment.received';
            $title = 'Payment received';
            $body = 'Payment of '.$this->money($subject->amount_minor, $subject->currency).' was received for '.($obligation instanceof Invoice ? 'invoice ' : 'booking ').$obligation->reference.'.';
            if ($subject->reconciliation_issue !== null) {
                $body .= ' Your booking requires management review. This receipt is not a booking confirmation.';
            }
            if ($obligation instanceof Invoice) {
                $label = 'View invoice';
                $url = route('invoices.show', $obligation, absolute: false);
            }
        } elseif ($subject instanceof Invoice && $event === 'invoice.issued') {
            $responsible = $subject;
            $type = 'invoice.issued';
            $title = 'Invoice issued';
            $body = 'Invoice '.$subject->reference.' was issued on '.$subject->issue_date->toDateString().' for '.$this->money($subject->total_minor, $subject->currency).'. Its due date is '.$subject->due_date->toDateString().'. Check the invoice for its current outstanding balance.';
            $label = 'View invoice';
            $url = route('invoices.show', $subject, absolute: false);
        } else {
            return null;
        }
        if ($type === null) {
            return null;
        }
        if ($responsible->organisation_id !== null) {
            $recipient = User::query()->find($subject->customer_id);
            if ($recipient === null || ! $recipient->can('viewCustomer', $responsible)) {
                return null;
            }
            if ($responsible instanceof Booking && ($subject instanceof Payment || $event === 'booking.approved')
                && ! $recipient->can('viewPayment', $responsible)) {
                if ($subject instanceof Payment) {
                    return null;
                }
                $label = 'View booking';
                $url = route('bookings.show', $subject, absolute: false);
            }
        }

        return ['customer_id' => $subject->customer_id, 'type' => $type, 'semantic_key' => $type.':'.$subject->getMorphClass().':'.$subject->getKey(), 'payload' => [
            'type' => $type, 'title' => $title, 'body' => $body, 'action_label' => $label,
            'action_url' => $url, 'occurred_at' => $activity->created_at->toISOString(),
        ]];
    }

    private function money(int $amount, string $currency): string
    {
        return strtoupper($currency).' '.intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }
}

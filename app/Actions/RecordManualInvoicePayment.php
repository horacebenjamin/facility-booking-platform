<?php

namespace App\Actions;

use App\Enums\BillingMethod;
use App\Enums\BookingStatus;
use App\Enums\FinancialStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Events\LifecycleNotificationRequested;
use App\Exceptions\InvoiceUnavailable;
use App\Exceptions\PaymentUnavailable;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\BookingPaymentEligibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class RecordManualInvoicePayment
{
    public function __construct(private BookingPaymentEligibility $eligibility) {}

    public function handle(User $actor, Invoice $invoice, string $externalReference, string $note): Payment
    {
        Gate::forUser($actor)->authorize('recordPayment', $invoice);
        $externalReference = trim($externalReference);
        $note = trim($note);
        Validator::make(['reference' => $externalReference, 'note' => $note], [
            'reference' => ['required', 'string', 'max:255'], 'note' => ['required', 'string', 'max:1000'],
        ])->validate();
        $bookingIds = $invoice->lines()->orderBy('booking_id')->pluck('booking_id')->all();

        return DB::transaction(function () use ($actor, $invoice, $externalReference, $note, $bookingIds): Payment {
            $bookings = Booking::query()->whereKey($bookingIds)->orderBy('id')->lockForUpdate()->get();
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            Gate::forUser($actor)->authorize('recordPayment', $invoice);
            $lines = $invoice->lines()->orderBy('booking_id')->lockForUpdate()->get();
            $payments = $invoice->payments()->orderBy('id')->lockForUpdate()->get();
            if ($invoice->status !== InvoiceStatus::Issued || $invoice->paid_at !== null || $payments->isNotEmpty()
                || $bookingIds === [] || $lines->pluck('booking_id')->all() !== $bookingIds
                || $bookings->count() !== $lines->count() || $lines->sum('amount_minor') !== $invoice->total_minor) {
                throw new InvoiceUnavailable('This invoice is already settled or its charges require review.');
            }
            foreach ($bookings as $booking) {
                $line = $lines->sole('booking_id', $booking->id);
                try {
                    $snapshot = $this->eligibility->snapshot($booking);
                } catch (PaymentUnavailable $exception) {
                    throw new InvoiceUnavailable($exception->getMessage(), previous: $exception);
                }
                if ($booking->billing_method !== BillingMethod::Invoice || $booking->status !== BookingStatus::Confirmed
                    || $booking->financial_status !== FinancialStatus::Invoiced
                    || $booking->organisation_id !== $invoice->organisation_id
                    || ($invoice->organisation_id === null && $booking->customer_id !== $invoice->customer_id)
                    || $line->charge_kind !== 'booking_total' || $snapshot->final_total_minor !== $line->amount_minor
                    || $snapshot->currency !== $invoice->currency || $booking->payments()->exists()) {
                    throw new InvoiceUnavailable('An invoice charge no longer matches its financial obligation.');
                }
            }
            $payment = $invoice->payments()->create([
                'reference' => Str::uuid(), 'booking_id' => null, 'customer_id' => $invoice->customer_id,
                'provider' => 'manual', 'recorded_by' => $actor->id, 'external_reference' => $externalReference,
                'recording_note' => $note, 'amount_minor' => $invoice->total_minor, 'currency' => $invoice->currency,
                'status' => PaymentStatus::Succeeded, 'succeeded_at' => now(), 'live_mode' => false,
                'checkout_parameters' => [], 'session_expires_at' => null,
            ]);
            $invoice->update(['status' => InvoiceStatus::Paid, 'paid_at' => $payment->succeeded_at]);
            foreach ($bookings as $booking) {
                $booking->update(['financial_status' => FinancialStatus::Paid]);
                activity('booking')->performedOn($booking)->causedBy($actor)->event('booking.invoice_paid')
                    ->withProperties(['invoice_reference' => $invoice->reference, 'payment_reference' => $payment->reference, 'before' => FinancialStatus::Invoiced->value, 'after' => FinancialStatus::Paid->value])
                    ->log('Invoiced booking settled');
            }
            $activity = activity('payment')->performedOn($payment)->causedBy($actor)->event('payment.manual_recorded')
                ->withProperties(['invoice_reference' => $invoice->reference, 'amount_minor' => $payment->amount_minor, 'currency' => $payment->currency])
                ->log('Offline invoice settlement recorded');
            activity('invoice')->performedOn($invoice)->causedBy($actor)->event('invoice.paid')
                ->withProperties(['payment_reference' => $payment->reference, 'before' => InvoiceStatus::Issued->value, 'after' => InvoiceStatus::Paid->value])
                ->log('Invoice settled');

            if ($activity instanceof Activity) {
                LifecycleNotificationRequested::dispatch($activity->id);
            }

            return $payment;
        });
    }
}

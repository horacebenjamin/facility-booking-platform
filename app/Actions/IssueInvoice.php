<?php

namespace App\Actions;

use App\Enums\BillingMethod;
use App\Enums\BookingStatus;
use App\Enums\FinancialStatus;
use App\Enums\InvoiceStatus;
use App\Exceptions\InvoiceUnavailable;
use App\Exceptions\PaymentUnavailable;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\User;
use App\Services\BookingPaymentEligibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class IssueInvoice
{
    public function __construct(private BookingPaymentEligibility $eligibility) {}

    /** @param array<int, int> $bookingIds */
    public function handle(User $actor, array $bookingIds): Invoice
    {
        Gate::forUser($actor)->authorize('create', Invoice::class);
        Validator::make(['bookings' => $bookingIds], [
            'bookings' => ['required', 'array', 'min:1', 'max:50'],
            'bookings.*' => ['required', 'integer', 'min:1', 'distinct:strict'],
        ])->validate();
        sort($bookingIds, SORT_NUMERIC);

        return DB::transaction(function () use ($actor, $bookingIds): Invoice {
            $bookings = Booking::query()->whereKey($bookingIds)->orderBy('id')->lockForUpdate()->get();
            Gate::forUser($actor)->authorize('create', Invoice::class);
            if ($bookings->count() !== count($bookingIds)) {
                throw new InvoiceUnavailable('One or more booking charges are unavailable.');
            }
            $customerId = $bookings->firstOrFail()->customer_id;
            $termDays = $bookings->firstOrFail()->invoice_term_days;
            $currency = null;
            $lines = [];
            foreach ($bookings as $booking) {
                Gate::forUser($actor)->authorize('manageInvoiceTerms', $booking);
                if ($booking->billing_method !== BillingMethod::Invoice || $booking->status !== BookingStatus::Confirmed
                    || $booking->financial_status !== FinancialStatus::InvoiceOutstanding
                    || $booking->customer_id !== $customerId || $booking->invoice_term_days !== $termDays
                    || $termDays === null || $termDays < 1 || $termDays > 365
                    || $booking->invoiceLines()->where('charge_kind', 'booking_total')->exists()
                    || $booking->payments()->exists()) {
                    throw new InvoiceUnavailable('Only uninvoiced, confirmed bookings with the same customer and agreed terms can be invoiced together.');
                }
                try {
                    $snapshot = $this->eligibility->snapshot($booking);
                } catch (PaymentUnavailable $exception) {
                    throw new InvoiceUnavailable($exception->getMessage(), previous: $exception);
                }
                if ($currency !== null && $currency !== $snapshot->currency) {
                    throw new InvoiceUnavailable('Invoice charges must use the same currency.');
                }
                $currency = $snapshot->currency;
                $lines[] = ['booking_id' => $booking->id, 'charge_kind' => 'booking_total',
                    'description' => $booking->reference.' · '.$booking->resource->name.' · '.$booking->starts_at->format('j M Y H:i'),
                    'amount_minor' => $snapshot->final_total_minor];
            }
            $invoice = Invoice::query()->create([
                'reference' => 'INV-'.Str::uuid(), 'customer_id' => $customerId, 'issued_by' => $actor->id,
                'issue_date' => today(), 'due_date' => today()->addDays($termDays),
                'status' => InvoiceStatus::Issued, 'currency' => $currency, 'total_minor' => array_sum(array_column($lines, 'amount_minor')),
            ]);
            $invoice->lines()->createMany($lines);
            $invoice->update(['total_minor' => $invoice->lines()->sum('amount_minor')]);
            foreach ($bookings as $booking) {
                $booking->update(['financial_status' => FinancialStatus::Invoiced]);
                activity('booking')->performedOn($booking)->causedBy($actor)->event('booking.invoiced')
                    ->withProperties(['invoice_reference' => $invoice->reference, 'before' => FinancialStatus::InvoiceOutstanding->value, 'after' => FinancialStatus::Invoiced->value])
                    ->log('Booking charge invoiced');
            }
            activity('invoice')->performedOn($invoice)->causedBy($actor)->event('invoice.issued')
                ->withProperties(['booking_ids' => $bookingIds, 'total_minor' => $invoice->total_minor, 'currency' => $currency, 'due_date' => $invoice->due_date->toDateString()])
                ->log('Invoice issued');

            return $invoice->load('lines');
        });
    }
}

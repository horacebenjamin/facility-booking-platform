<?php

namespace App\Services\Documents;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use Illuminate\Support\Carbon;

class DocumentBuilder
{
    public function __construct(private DocumentFormatter $format) {}

    /** @return array<string, mixed> */
    public function invoice(Invoice $invoice): array
    {
        $invoice->loadMissing(['customer', 'organisation', 'lines.booking.centre', 'payments']);
        $paidMinor = (int) $invoice->payments->where('status', PaymentStatus::Succeeded)->sum('amount_minor');

        return [
            'reference' => $invoice->reference,
            'issued' => $invoice->issue_date->format('j F Y'),
            'due' => $invoice->due_date->format('j F Y'),
            'status' => $invoice->status->label(),
            'owner' => $invoice->organisation_id === null ? $invoice->customer->name : $invoice->organisation->name,
            'contact' => $invoice->organisation_id === null ? $invoice->customer->email : 'Booked by '.$invoice->customer->name.' - '.$invoice->customer->email,
            'lines' => $invoice->lines->map(fn (InvoiceLine $line): array => [
                'description' => $line->description,
                'booking' => $line->booking->reference,
                'centre' => $line->booking->centre->name,
                'amount' => $this->format->money($line->amount_minor, $invoice->currency),
            ])->all(),
            'total' => $this->format->money($invoice->total_minor, $invoice->currency),
            'paid' => $this->format->money($paidMinor, $invoice->currency),
            'outstanding' => $this->format->money(max(0, $invoice->total_minor - $paidMinor), $invoice->currency),
            'currency' => $invoice->currency,
        ];
    }

    /** @return array<string, mixed> */
    public function receipt(Payment $payment): array
    {
        abort_unless($payment->status === PaymentStatus::Succeeded && $payment->succeeded_at !== null, 404);
        $payment->loadMissing(['booking.organisation', 'booking.customer', 'invoice.organisation', 'invoice.customer']);
        $obligation = $payment->booking ?? $payment->invoice;
        abort_if($obligation === null, 404);

        return [
            'reference' => $payment->reference,
            'date' => $this->format->dateTime($payment->succeeded_at),
            'amount' => $this->format->money($payment->amount_minor, $payment->currency),
            'currency' => $payment->currency,
            'owner' => $obligation->organisation_id === null ? $obligation->customer->name : $obligation->organisation->name,
            'contact' => $obligation->customer->email,
            'booking' => $payment->booking?->reference,
            'invoice' => $payment->invoice?->reference,
            'method' => $payment->provider === 'manual' ? 'Externally received payment' : 'Card payment',
            'status' => 'Successful',
        ];
    }

    /** @return array<string, mixed> */
    public function confirmation(Booking $booking): array
    {
        abort_unless($booking->status === BookingStatus::Confirmed, 404);
        $booking->loadMissing(['customer', 'organisation', 'centre', 'facility', 'resource', 'equipmentRequests.equipment', 'priceSnapshot.lines']);
        $snapshot = $booking->priceSnapshot;

        return [
            'reference' => $booking->reference,
            'status' => $booking->status->label(),
            'owner' => $booking->organisation_id === null ? $booking->customer->name : $booking->organisation->name,
            'booked_by' => $booking->customer->name,
            'contact' => $booking->customer->email,
            'centre' => $booking->centre->name,
            'facility' => $booking->facility->name,
            'resource' => $booking->resource->name,
            'starts' => $this->format->dateTime($booking->starts_at),
            'ends' => $this->format->dateTime($booking->ends_at),
            'equipment' => $booking->equipmentRequests->map(fn ($request): string => $request->equipment->name.' × '.$request->requested_quantity)->all(),
            'price_lines' => $snapshot?->lines->map(fn ($line): array => [
                'description' => $line->description,
                'amount' => $this->format->money($line->amount_minor, $snapshot->currency),
            ])->all() ?? [],
            'discount' => $snapshot !== null && $snapshot->discount_amount_minor > 0
                ? $this->format->money($snapshot->discount_amount_minor, $snapshot->currency) : null,
            'adjustment' => $snapshot !== null && $snapshot->final_total_minor !== $snapshot->calculated_total_minor
                ? $this->format->money($snapshot->final_total_minor - $snapshot->calculated_total_minor, $snapshot->currency) : null,
            'total' => $snapshot === null ? null : $this->format->money($snapshot->final_total_minor, $snapshot->currency),
            'financial_status' => $booking->financial_status->label(),
            'generated' => $this->format->dateTime(Carbon::now()),
        ];
    }
}

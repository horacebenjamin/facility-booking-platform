<?php

namespace App\Services\Documents;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\Payment;
use App\Models\User;
use App\Services\BookingDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class StatementBuilder
{
    public function __construct(private DocumentFormatter $format) {}

    /** @return array<string, mixed> */
    public function build(User|Organisation $owner, string $from, string $to): array
    {
        $localZone = (string) config('booking.local_timezone');
        $start = CarbonImmutable::parse($from.' 00:00:00', $localZone)->utc();
        $end = CarbonImmutable::parse($to.' 00:00:00', $localZone)->addDay()->utc();
        $invoices = $this->invoices($owner);
        $settlements = $this->settlements($owner);

        $balances = [];
        foreach ((clone $invoices)->where('issue_date', '<', $from)->selectRaw('currency, SUM(total_minor) AS amount')->groupBy('currency')->pluck('amount', 'currency') as $currency => $amount) {
            $balances[$currency] = (int) $amount;
        }
        foreach ((clone $settlements)->where('succeeded_at', '<', $start)->selectRaw('currency, SUM(amount_minor) AS amount')->groupBy('currency')->pluck('amount', 'currency') as $currency => $amount) {
            $balances[$currency] = ($balances[$currency] ?? 0) - (int) $amount;
        }
        $opening = $balances;

        $entries = [];
        foreach ((clone $invoices)->whereBetween('issue_date', [$from, $to])->with('lines.booking')->orderBy('issue_date')->orderBy('id')->get() as $invoice) {
            $entries[] = [
                'sort' => $invoice->issue_date->toDateString().' 00:00:00',
                'order' => 0,
                'date' => $invoice->issue_date->format('j M Y'),
                'reference' => $invoice->reference,
                'description' => 'Invoice - '.$invoice->lines->pluck('booking.reference')->unique()->join(', '),
                'debit_minor' => $invoice->total_minor,
                'credit_minor' => 0,
                'currency' => $invoice->currency,
            ];
        }
        foreach ((clone $settlements)->where('succeeded_at', '>=', $start)->where('succeeded_at', '<', $end)->with('invoice')->orderBy('succeeded_at')->orderBy('id')->get() as $payment) {
            $entries[] = [
                'sort' => BookingDateTime::inLocalTimezone($payment->succeeded_at)->format('Y-m-d H:i:s'),
                'order' => 1,
                'date' => $this->format->dateTime($payment->succeeded_at),
                'reference' => $payment->reference,
                'description' => 'Payment for invoice '.$payment->invoice->reference,
                'debit_minor' => 0,
                'credit_minor' => $payment->amount_minor,
                'currency' => $payment->currency,
            ];
        }
        usort($entries, fn (array $left, array $right): int => [$left['sort'], $left['order'], $left['reference']] <=> [$right['sort'], $right['order'], $right['reference']]);
        $rows = [];
        foreach ($entries as $entry) {
            $currency = $entry['currency'];
            $balances[$currency] = ($balances[$currency] ?? 0) + $entry['debit_minor'] - $entry['credit_minor'];
            $rows[] = [
                'date' => $entry['date'],
                'reference' => $entry['reference'],
                'description' => $entry['description'],
                'debit' => $entry['debit_minor'] === 0 ? '-' : $this->format->money($entry['debit_minor'], $currency),
                'credit' => $entry['credit_minor'] === 0 ? '-' : $this->format->money($entry['credit_minor'], $currency),
                'balance' => $this->format->money($balances[$currency], $currency),
            ];
        }

        $cardPayments = $this->cardPayments($owner)
            ->where('succeeded_at', '>=', $start)->where('succeeded_at', '<', $end)
            ->with('booking')->orderBy('succeeded_at')->orderBy('id')->get()
            ->map(fn (Payment $payment): array => [
                'date' => $this->format->dateTime($payment->succeeded_at),
                'reference' => $payment->reference,
                'booking' => $payment->booking->reference,
                'amount' => $this->format->money($payment->amount_minor, $payment->currency),
            ])->all();

        return [
            'owner' => $owner->name,
            'owner_type' => $owner instanceof Organisation ? 'Organisation' : 'Personal',
            'contact' => $owner instanceof User ? $owner->email : null,
            'from' => $from,
            'to' => $to,
            'generated' => $this->format->dateTime(now()),
            'opening' => $this->formattedBalances($opening),
            'closing' => $this->formattedBalances($balances),
            'rows' => $rows,
            'card_payments' => $cardPayments,
        ];
    }

    /** @return Builder<Invoice> */
    private function invoices(User|Organisation $owner): Builder
    {
        return Invoice::query()->when(
            $owner instanceof Organisation,
            fn (Builder $query): Builder => $query->where('organisation_id', $owner->id),
            fn (Builder $query): Builder => $query->whereNull('organisation_id')->where('customer_id', $owner->id),
        );
    }

    /** @return Builder<Payment> */
    private function settlements(User|Organisation $owner): Builder
    {
        return Payment::query()->where('status', PaymentStatus::Succeeded)->whereNotNull('succeeded_at')
            ->whereHas('invoice', fn (Builder $query): Builder => $query->when(
                $owner instanceof Organisation,
                fn (Builder $query): Builder => $query->where('organisation_id', $owner->id),
                fn (Builder $query): Builder => $query->whereNull('organisation_id')->where('customer_id', $owner->id),
            ));
    }

    /** @return Builder<Payment> */
    private function cardPayments(User|Organisation $owner): Builder
    {
        return Payment::query()->where('status', PaymentStatus::Succeeded)->whereNotNull('succeeded_at')
            ->whereHas('booking', fn (Builder $query): Builder => $query->when(
                $owner instanceof Organisation,
                fn (Builder $query): Builder => $query->where('organisation_id', $owner->id),
                fn (Builder $query): Builder => $query->whereNull('organisation_id')->where('customer_id', $owner->id),
            ));
    }

    /** @param array<string, int> $balances
     * @return list<string>
     */
    private function formattedBalances(array $balances): array
    {
        ksort($balances);

        return array_map(fn (string $currency, int $minor): string => $this->format->money($minor, $currency), array_keys($balances), $balances);
    }
}

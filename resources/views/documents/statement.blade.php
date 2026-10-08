@extends('documents.layout')

@section('title', 'Account statement')

@section('body')
    <h1>Account statement</h1><div class="document-reference">{{ $document['owner_type'] }} account - {{ $document['from'] }} to {{ $document['to'] }}</div>
    <table class="intro"><tr><td><h2>Account holder</h2><div class="value">{{ $document['owner'] }}</div>@if ($document['contact'])<div>{{ $document['contact'] }}</div>@endif</td><td class="right"><h2>Generated</h2><div>{{ $document['generated'] }}</div></td></tr></table>
    <h2>Invoice account</h2>
    <table class="balance-summary"><tr><td><div class="label">Opening invoice balance</div><div class="balance">{{ count($document['opening']) ? implode(' / ', $document['opening']) : '£0.00' }}</div></td><td><div class="label">Closing invoice balance</div><div class="balance">{{ count($document['closing']) ? implode(' / ', $document['closing']) : '£0.00' }}</div></td></tr></table>
    <table class="items"><thead><tr><th>Date / reference</th><th>Description</th><th class="right">Charge</th><th class="right">Payment</th><th class="right">Balance</th></tr></thead><tbody>
    @forelse ($document['rows'] as $row)
        <tr><td>{{ $row['date'] }}<br><span class="document-reference">{{ $row['reference'] }}</span></td><td>{{ $row['description'] }}</td><td class="right">{{ $row['debit'] }}</td><td class="right">{{ $row['credit'] }}</td><td class="right">{{ $row['balance'] }}</td></tr>
    @empty
        <tr><td colspan="5">No invoice account activity in this period.</td></tr>
    @endforelse
    </tbody></table>
    <h2>Card payments for bookings</h2>
    <p class="muted">These payments settle bookings directly and are separate from the invoice balance above.</p>
    <table class="items"><thead><tr><th>Received / payment</th><th>Booking</th><th class="right">Amount</th></tr></thead><tbody>
    @forelse ($document['card_payments'] as $payment)<tr><td>{{ $payment['date'] }}<br><span class="document-reference">{{ $payment['reference'] }}</span></td><td>{{ $payment['booking'] }}</td><td class="right">{{ $payment['amount'] }}</td></tr>
    @empty<tr><td colspan="3">No direct booking payments in this period.</td></tr>@endforelse
    </tbody></table>
@endsection

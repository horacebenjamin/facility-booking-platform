@extends('documents.layout')

@section('title', 'Receipt')

@section('body')
    <h1>Receipt</h1>
    <div class="document-reference">Payment reference <strong>{{ $document['reference'] }}</strong></div>
    <table class="intro"><tr>
        <td><h2>Received from</h2><div class="value">{{ $document['owner'] }}</div><div>{{ $document['contact'] }}</div></td>
        <td class="right"><h2>Payment details</h2><div class="label">Received</div><div>{{ $document['date'] }}</div><div class="space"></div><div class="label">Status</div><div>{{ $document['status'] }}</div></td>
    </tr></table>
    <table class="items"><thead><tr><th>For</th><th>Method</th><th class="right">Amount received</th></tr></thead><tbody><tr>
        <td>@if ($document['invoice']) Invoice {{ $document['invoice'] }} @else Booking {{ $document['booking'] }} @endif</td>
        <td>{{ $document['method'] }}</td><td class="right"><div class="amount-highlight">{{ $document['amount'] }}</div></td>
    </tr></tbody></table>
    <div class="note">This receipt records a successful {{ $document['currency'] }} payment. It is not an invoice.</div>
@endsection

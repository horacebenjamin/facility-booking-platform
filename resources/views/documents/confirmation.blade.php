@extends('documents.layout')

@section('title', 'Booking confirmation')

@section('body')
    <h1>Booking confirmation</h1>
    <div class="document-reference">Booking reference <strong>{{ $document['reference'] }}</strong> - {{ $document['status'] }}</div>
    <table class="intro"><tr>
        <td><h2>Booked for</h2><div class="value">{{ $document['owner'] }}</div><div>Booked by {{ $document['booked_by'] }}</div><div>{{ $document['contact'] }}</div></td>
        <td class="right"><h2>Venue</h2><div class="value">{{ $document['centre'] }}</div><div>{{ $document['facility'] }} - {{ $document['resource'] }}</div></td>
    </tr></table>
    <table class="items"><thead><tr><th>Starts</th><th>Ends</th></tr></thead><tbody><tr><td>{{ $document['starts'] }}</td><td>{{ $document['ends'] }}</td></tr></tbody></table>
    @if (count($document['equipment']) > 0)
        <h2>Equipment</h2><p>{{ implode(', ', $document['equipment']) }}</p>
    @endif
    @if ($document['total'] !== null)
        <h2>Confirmed price</h2>
        <table class="items"><thead><tr><th>Charge</th><th class="right">Amount</th></tr></thead><tbody>
        @foreach ($document['price_lines'] as $line)<tr><td>{{ $line['description'] }}</td><td class="right">{{ $line['amount'] }}</td></tr>@endforeach
        @if ($document['discount'])<tr><td>Discount</td><td class="right">-{{ $document['discount'] }}</td></tr>@endif
        @if ($document['adjustment'])<tr><td>Agreed price adjustment</td><td class="right">{{ $document['adjustment'] }}</td></tr>@endif
        </tbody></table>
        <table class="totals"><tr class="final"><td>Total</td><td class="right">{{ $document['total'] }}</td></tr></table>
    @endif
    <div class="note">Financial status: {{ $document['financial_status'] }}. Generated {{ $document['generated'] }}.</div>
@endsection

@extends('documents.layout')

@section('title', 'Invoice')

@section('body')
    <h1>Invoice</h1>
    <div class="document-reference">Invoice reference <strong>{{ $document['reference'] }}</strong></div>
    <table class="intro"><tr>
        <td><h2>Bill to</h2><div class="value">{{ $document['owner'] }}</div><div>{{ $document['contact'] }}</div></td>
        <td class="right"><div class="label">Issued</div><div>{{ $document['issued'] }}</div><div class="space"></div><div class="label">Due</div><div>{{ $document['due'] }}</div><div class="space"></div><div class="label">Status</div><div>{{ $document['status'] }}</div></td>
    </tr></table>
    <h2>Booking charges</h2>
    <table class="items"><thead><tr><th>Description</th><th>Booking / centre</th><th class="right">Amount</th></tr></thead><tbody>
    @foreach ($document['lines'] as $line)
        <tr><td>{{ $line['description'] }}</td><td><span class="document-reference">{{ $line['booking'] }}</span><br><span class="muted">{{ $line['centre'] }}</span></td><td class="right">{{ $line['amount'] }}</td></tr>
    @endforeach
    </tbody></table>
    <table class="totals"><tr><td>Invoice total</td><td class="right">{{ $document['total'] }}</td></tr><tr><td>Payments received</td><td class="right">{{ $document['paid'] }}</td></tr><tr class="final"><td>Outstanding</td><td class="right">{{ $document['outstanding'] }}</td></tr></table>
    <div class="note">Use the invoice reference above when contacting Facility4Hire. Amounts are shown in {{ $document['currency'] }}.</div>
@endsection

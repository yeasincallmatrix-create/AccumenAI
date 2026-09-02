@extends('layouts.institute')
@section('title','Credit Note '.$ret->credit_note_number.' — AccumenAI')
@section('content')
<div class="d-flex justify-content-between mb-4">
    <h4>Credit Note: {{ $ret->credit_note_number }} <small class="text-muted">for Return {{ $ret->return_number }}</small></h4>
    <a class="btn btn-sm btn-dark rounded-pill" href="{{ route('sales.returns.credit-note',$ret) }}?print=1" target="_blank"><i class="bi bi-printer"></i> Print</a>
</div>
<div class="card"><div class="card-body">
<p><strong>Customer:</strong> {{ $ret->customer?->name }} | <strong>Invoice:</strong> {{ $ret->invoice?->invoice_number }} | <strong>Date:</strong> {{ $ret->return_date->format('Y-m-d') }} | <strong>Status:</strong> {{ ucfirst($ret->status) }}</p>
<table class="table"><thead><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Total</th></tr></thead><tbody>@foreach($ret->items as $it)<tr><td>{{ $it->description }}</td><td class="text-end">{{ $it->quantity }}</td><td class="text-end">{{ number_format($it->line_total,2) }}</td></tr>@endforeach</tbody><tfoot><tr><th colspan="2" class="text-end">Total Credit</th><th class="text-end">{{ number_format($ret->grand_total,2) }}</th></tr></tfoot></table>
<p class="text-muted">Immutable after posting — reversals create a new document. Linked invoice remains unchanged.</p>
</div></div>
@endsection

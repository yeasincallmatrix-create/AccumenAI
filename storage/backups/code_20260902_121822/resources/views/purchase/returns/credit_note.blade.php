@extends('layouts.institute')
@section('title', 'Credit Note ' . $return->credit_note_number)
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Credit Note <small class="text-muted">{{ $return->credit_note_number }}</small></h4>
    <a href="{{ route('purchase.returns.show',$return) }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
</div>
<div class="card"><div class="card-body">
<p><strong>Supplier:</strong> {{ $return->supplier?->name }}<br><strong>Return:</strong> {{ $return->return_number }}<br><strong>Date:</strong> {{ $return->return_date->format('Y-m-d') }}<br><strong>Status:</strong> {{ ucfirst($return->status) }}</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Total</th></tr></thead><tbody>@foreach($return->items as $line)<tr><td>{{ $line->description }}</td><td class="text-end">{{ number_format($line->quantity,2) }}</td><td class="text-end">{{ number_format($line->unit_price,2) }}</td><td class="text-end">{{ number_format($line->line_total,2) }}</td></tr>@endforeach</tbody><tfoot><tr><th colspan="3" class="text-end">Grand Total</th><th class="text-end">{{ number_format($return->grand_total,2) }}</th></tr></tfoot></table></div>
<p class="text-muted small">Supplier → PO {{ $return->purchaseOrder?->order_number ?? '—' }} → GRN {{ $return->goodsReceipt?->receipt_number ?? '—' }} → Return {{ $return->return_number }} → Credit Note {{ $return->credit_note_number }}<br>Journal: {{ $return->journal?->journal_no ?? '—' }}</p>
</div></div>
@endsection

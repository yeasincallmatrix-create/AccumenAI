@extends('layouts.institute')
@section('title','Supplier Payment Report')
@section('content')
<h4>Supplier Payment Report</h4>
@include('purchase.reports._filters')
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Date</th><th>Supplier</th><th>Invoice</th><th class="text-end">Amount</th></tr></thead><tbody>
@forelse($rows as $r)<tr><td>{{ $r->paid_at?->format('Y-m-d') }}</td><td>{{ $r->supplier?->name }}</td><td>{{ $r->purchaseInvoice?->invoice_number ?? '-' }}</td><td class="text-end">{{ number_format($r->amount,2) }}</td></tr>
@empty<tr><td colspan="4" class="text-center text-muted">No payments</td></tr>@endforelse
</tbody></table></div>@if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif</div>
@endsection

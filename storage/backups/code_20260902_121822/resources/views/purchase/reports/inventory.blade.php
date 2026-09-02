@extends('layouts.institute')
@section('title','Inventory Reconciliation')
@section('content')
<h4>Inventory Reconciliation — Ordered / Received / Returned / Net</h4>
@include('purchase.reports._filters')
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Order #</th><th>Supplier</th><th class="text-end">Ordered</th><th class="text-end">Received</th><th class="text-end">Returned</th><th class="text-end">Net</th></tr></thead><tbody>
@forelse($rows as $r)<tr><td>{{ $r->order_number }}</td><td>{{ $r->supplier?->name }}</td><td class="text-end">{{ number_format($r->reconciliation['ordered_qty'],2) }}</td><td class="text-end">{{ number_format($r->reconciliation['received_qty'],2) }}</td><td class="text-end">{{ number_format($r->reconciliation['returned_qty'],2) }}</td><td class="text-end fw-bold">{{ number_format($r->reconciliation['net_received_qty'],2) }}</td></tr>
@empty<tr><td colspan="6" class="text-center text-muted">No orders</td></tr>@endforelse
</tbody></table></div>@if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif</div>
@endsection

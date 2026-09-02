@extends('layouts.institute')
@section('title','Warehouse Receiving')
@section('content')
<h4>Warehouse-wise Receiving</h4>
@include('purchase.reports._filters')
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Warehouse</th><th class="text-end">Receipts</th><th class="text-end">Net Qty</th></tr></thead><tbody>
@forelse($rows as $r)<tr><td>{{ $r->warehouse_name }}</td><td class="text-end">{{ $r->cnt }}</td><td class="text-end">{{ number_format($r->net_qty,2) }}</td></tr>
@empty<tr><td colspan="3" class="text-center text-muted">No data</td></tr>@endforelse
</tbody></table></div></div>
@endsection

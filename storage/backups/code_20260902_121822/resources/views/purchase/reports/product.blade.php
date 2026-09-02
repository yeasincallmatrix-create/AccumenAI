@extends('layouts.institute')
@section('title','Product-wise Purchases')
@section('content')
<h4>Product-wise Purchases</h4>
@include('purchase.reports._filters')
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Product</th><th>SKU</th><th class="text-end">Qty</th><th class="text-end">Total</th></tr></thead><tbody>
@forelse($rows as $r)<tr><td>{{ $r->item_name }}</td><td>{{ $r->sku }}</td><td class="text-end">{{ number_format($r->qty,2) }}</td><td class="text-end">{{ number_format($r->total,2) }}</td></tr>
@empty<tr><td colspan="4" class="text-center text-muted">No data</td></tr>@endforelse
</tbody></table></div>@if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif</div>
@endsection

@extends('layouts.institute')
@section('title','Category-wise Purchases')
@section('content')
<h4>Category-wise Purchases</h4>
@include('purchase.reports._filters')
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Category</th><th class="text-end">Qty</th><th class="text-end">Total</th></tr></thead><tbody>
@forelse($rows as $r)<tr><td>{{ $r->category_name }}</td><td class="text-end">{{ number_format($r->qty,2) }}</td><td class="text-end">{{ number_format($r->total,2) }}</td></tr>
@empty<tr><td colspan="3" class="text-center text-muted">No data</td></tr>@endforelse
</tbody></table></div></div>
@endsection

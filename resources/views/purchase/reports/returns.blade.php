@extends('layouts.institute')
@section('title','Purchase Return Report')
@section('content')
<h4>Purchase Return Report</h4>
@include('purchase.reports._filters')
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Return #</th><th>Supplier</th><th>Date</th><th class="text-end">Total</th></tr></thead><tbody>
@forelse($rows as $r)<tr><td>{{ $r->return_number }}</td><td>{{ $r->supplier?->name }}</td><td>{{ $r->return_date?->format('Y-m-d') }}</td><td class="text-end">{{ number_format($r->grand_total,2) }}</td></tr>
@empty<tr><td colspan="4" class="text-center text-muted">No returns</td></tr>@endforelse
</tbody></table></div>@if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif</div>
@endsection

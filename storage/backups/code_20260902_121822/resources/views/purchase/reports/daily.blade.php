@extends('layouts.institute')
@section('title','Daily Purchase Report')
@section('content')
<h4>Daily Purchase Report</h4>
@include('purchase.reports._filters')
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Period</th><th class="text-end">Invoices</th><th class="text-end">Total</th><th class="text-end">Tax</th></tr></thead><tbody>
@forelse($rows as $r)<tr><td>{{ $r->period }}</td><td class="text-end">{{ $r->cnt }}</td><td class="text-end">{{ number_format($r->total,2) }}</td><td class="text-end">{{ number_format($r->tax,2) }}</td></tr>
@empty<tr><td colspan="4" class="text-center text-muted">No data</td></tr>@endforelse
</tbody></table></div></div>
@endsection

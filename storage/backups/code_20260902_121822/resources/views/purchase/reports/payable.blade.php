@extends('layouts.institute')
@section('title','Outstanding Payable Report')
@section('content')
<h4>Outstanding Payable (Finance AP — source of truth)</h4>
@include('purchase.reports._filters')
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Supplier</th><th class="text-end">Payable</th><th class="text-end">Current</th><th class="text-end">31-60</th></tr></thead><tbody>
@forelse($rows as $r)<tr><td>{{ $r->name }}</td><td class="text-end">{{ number_format($r->payable,2) }}</td><td class="text-end">{{ number_format($r->aging['current']??0,2) }}</td><td class="text-end">{{ number_format($r->aging['31_60']??0,2) }}</td></tr>
@empty<tr><td colspan="4" class="text-center text-muted">No outstanding payable</td></tr>@endforelse
</tbody></table></div></div>
@endsection

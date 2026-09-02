@extends('layouts.institute')
@section('title','Product-wise Sales — AccumenAI')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Product-wise Sales</h4>
    <div class="d-flex gap-2"><a href="{{ request()->fullUrlWithQuery(['export'=>'csv']) }}" class="btn btn-sm btn-outline-success rounded-pill"><i class="bi bi-download"></i> CSV</a><button class="btn btn-sm btn-outline-primary rounded-pill" onclick="window.print()"><i class="bi bi-printer"></i> Print</button><a href="{{ route('sales.reports.dashboard') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Dashboard</a></div>
</div>
<form method="GET" class="card mb-3"><div class="card-body d-flex flex-wrap gap-2 align-items-end">
    <div><label class="form-label small mb-1">From</label><input type="date" name="from" value="{{ $filters['from'] }}" class="form-control form-control-sm"></div>
    <div><label class="form-label small mb-1">To</label><input type="date" name="to" value="{{ $filters['to'] }}" class="form-control form-control-sm"></div>
    <div><label class="form-label small mb-1">Customer</label><input type="number" name="customer_id" value="{{ $filters['customer_id'] }}" class="form-control form-control-sm" style="width:120px"></div>
    <button class="btn btn-sm btn-primary rounded-pill">Filter</button><a href="{{ route('sales.reports.product') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Reset</a>
</div></form>
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Product</th><th>SKU</th><th class="text-end">Qty</th><th class="text-end">Total</th><th class="text-end">Orders</th></tr></thead><tbody>
@forelse($rows as $r)<tr><td>{{ $r->product_name }}</td><td class="text-muted small">{{ $r->sku }}</td><td class="text-end">{{ number_format($r->qty,2) }}</td><td class="text-end">{{ number_format($r->total,2) }}</td><td class="text-end">{{ $r->orders }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-3">No data.</td></tr>@endforelse
</tbody>@if($rows->isNotEmpty())<tfoot><tr class="table-light"><th colspan="2">Total</th><th class="text-end">{{ number_format($rows->sum('qty'),2) }}</th><th class="text-end">{{ number_format($rows->sum('total'),2) }}</th><th class="text-end">{{ $rows->sum('orders') }}</th></tr></tfoot>@endif</table></div></div>
@endsection

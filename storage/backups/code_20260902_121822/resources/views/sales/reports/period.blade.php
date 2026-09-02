@extends('layouts.institute')
@section('title', ucfirst($group).' Sales — AccumenAI')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">{{ ucfirst($group) }} Sales</h4>
    <div class="d-flex gap-2">
        <a href="{{ request()->fullUrlWithQuery(['export'=>'csv']) }}" class="btn btn-sm btn-outline-success rounded-pill"><i class="bi bi-download"></i> CSV</a>
        <button class="btn btn-sm btn-outline-primary rounded-pill" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        <a href="{{ route('sales.reports.dashboard') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Dashboard</a>
    </div>
</div>
<form method="GET" class="card mb-3"><div class="card-body d-flex flex-wrap gap-2 align-items-end">
    <div><label class="form-label small mb-1">From</label><input type="date" name="from" value="{{ $filters['from'] }}" class="form-control form-control-sm"></div>
    <div><label class="form-label small mb-1">To</label><input type="date" name="to" value="{{ $filters['to'] }}" class="form-control form-control-sm"></div>
    <div><label class="form-label small mb-1">Customer</label><input type="number" name="customer_id" value="{{ $filters['customer_id'] }}" placeholder="ID" class="form-control form-control-sm" style="width:120px"></div>
    <div><label class="form-label small mb-1">Status</label><select name="status" class="form-select form-select-sm"><option value="">All</option><option value="approved" {{ $filters['status']=='approved'?'selected':'' }}>Approved</option><option value="completed" {{ $filters['status']=='completed'?'selected':'' }}>Completed</option></select></div>
    <button class="btn btn-sm btn-primary rounded-pill">Filter</button>
    <a href="{{ route('sales.reports.'.$group) }}" class="btn btn-sm btn-outline-secondary rounded-pill">Reset</a>
    <div class="ms-auto d-flex gap-1">
        <a href="{{ route('sales.reports.daily') }}" class="btn btn-sm {{ $group=='daily'?'btn-primary':'btn-outline-secondary' }}">Daily</a>
        <a href="{{ route('sales.reports.weekly') }}" class="btn btn-sm {{ $group=='weekly'?'btn-primary':'btn-outline-secondary' }}">Weekly</a>
        <a href="{{ route('sales.reports.monthly') }}" class="btn btn-sm {{ $group=='monthly'?'btn-primary':'btn-outline-secondary' }}">Monthly</a>
        <a href="{{ route('sales.reports.yearly') }}" class="btn btn-sm {{ $group=='yearly'?'btn-primary':'btn-outline-secondary' }}">Yearly</a>
    </div>
</div></form>
<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Period</th><th class="text-end">Orders</th><th class="text-end">Total Sales</th><th class="text-end">Discount</th><th class="text-end">Tax</th></tr></thead>
            <tbody>
            @forelse($rows as $r)
                <tr><td>{{ $r->period }}</td><td class="text-end">{{ $r->orders }}</td><td class="text-end">{{ number_format($r->total,2) }}</td><td class="text-end text-danger">{{ number_format($r->discount,2) }}</td><td class="text-end">{{ number_format($r->tax,2) }}</td></tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-3">No data for selected period.</td></tr>
            @endforelse
            </tbody>
            @if($rows->isNotEmpty())
                <tfoot><tr class="table-light"><th>Total</th><th class="text-end">{{ $rows->sum('orders') }}</th><th class="text-end">{{ number_format($rows->sum('total'),2) }}</th><th class="text-end">{{ number_format($rows->sum('discount'),2) }}</th><th class="text-end">{{ number_format($rows->sum('tax'),2) }}</th></tr></tfoot>
            @endif
        </table>
    </div>
</div>
@endsection

@extends('layouts.standalone')

@section('title', 'Sales Report — AccumenAI')
@section('page_title', 'Reports')

@section('content')
<div class="standalone-heading">
    <h4>Sales Report (বিক্রয় প্রতিবেদন)</h4>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('dealership.reports.sales.export', request()->query()) }}"><i class="bi bi-download"></i> CSV</a>
        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    </div>
</div>

@include('dealership.reports._nav')

<div class="filter-card mb-3">
    <form method="GET" action="{{ route('dealership.reports.sales.index') }}" class="row g-2">
        <div class="col-md-2"><label class="form-label">Group By</label>
            <select name="group_by" class="form-select form-select-sm"><option value="channel">channel</option><option value="brand" @selected(($filters['group_by'] ?? '') === 'brand')>brand</option><option value="product" @selected(($filters['group_by'] ?? '') === 'product')>product</option></select>
        </div>
        <div class="col-md-2"><label class="form-label">Channel</label><input type="text" name="channel" class="form-control form-control-sm" value="{{ $filters['channel'] ?? '' }}"></div>
        <div class="col-md-2"><label class="form-label">From</label><x-tdate-input class="form-control form-control-sm" name="from" :value="$filters['from'] ?? null" /></div>
        <div class="col-md-2"><label class="form-label">To</label><x-tdate-input class="form-control form-control-sm" name="to" :value="$filters['to'] ?? null" /></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-search"></i> Filter</button></div>
    </form>
</div>

<div class="admin-card">
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead><tr><th>{{ ucfirst($report['group_by']) }}</th><th>Qty</th><th>Amount</th></tr></thead>
            <tbody>
                @forelse($report['rows'] as $row)
                    <tr><td>{{ $row[$report['group_by']] }}</td><td>{{ $row['qty'] }}</td><td>{{ number_format($row['amount'], 2) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted">কোনো তথ্য নেই (No data)</td></tr>
                @endforelse
            </tbody>
            <tfoot><tr><th>Total</th><th>{{ $report['totals']['qty'] }}</th><th>{{ number_format($report['totals']['amount'], 2) }}</th></tr></tfoot>
        </table>
    </div>
</div>
@endsection

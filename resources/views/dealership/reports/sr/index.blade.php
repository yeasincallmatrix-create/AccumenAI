@extends('layouts.standalone')

@section('title', 'SR Sales Report — AccumenAI')
@section('page_title', 'Reports')

@section('content')
<div class="standalone-heading">
    <h4>SR Sales Report (এসআর বিক্রয়)</h4>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('dealership.reports.sr.export', request()->query()) }}"><i class="bi bi-download"></i> CSV</a>
        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    </div>
</div>

@include('dealership.reports._nav')

<div class="filter-card mb-3">
    <form method="GET" action="{{ route('dealership.reports.sr.index') }}" class="row g-2">
        <div class="col-md-2"><label class="form-label">SR</label><input type="number" name="sr" class="form-control form-control-sm" value="{{ $filters['sr'] ?? '' }}"></div>
        <div class="col-md-2"><label class="form-label">Status</label>
            <select name="status" class="form-select form-select-sm"><option value="">— All —</option>@foreach($statuses as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $s }}</option>@endforeach</select>
        </div>
        <div class="col-md-2"><label class="form-label">From</label><x-tdate-input class="form-control form-control-sm" name="from" :value="$filters['from'] ?? null" /></div>
        <div class="col-md-2"><label class="form-label">To</label><x-tdate-input class="form-control form-control-sm" name="to" :value="$filters['to'] ?? null" /></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-search"></i> Filter</button></div>
    </form>
</div>

<div class="admin-card">
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead><tr><th>SR</th><th>Orders</th><th>Qty</th><th>Amount</th></tr></thead>
            <tbody>
                @forelse($report['rows'] as $row)
                    <tr><td>{{ $row['sales_force_id'] }}</td><td>{{ $row['orders'] }}</td><td>{{ $row['qty'] }}</td><td>{{ number_format($row['amount'], 2) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted">কোনো তথ্য নেই (No data)</td></tr>
                @endforelse
            </tbody>
            <tfoot><tr><th>Total</th><th>{{ $report['totals']['orders'] }}</th><th></th><th>{{ number_format($report['totals']['amount'], 2) }}</th></tr></tfoot>
        </table>
    </div>
</div>
@endsection

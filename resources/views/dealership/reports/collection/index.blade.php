@extends('layouts.standalone')

@section('title', 'Collection Report — AccumenAI')
@section('page_title', 'Reports')

@section('content')
<div class="standalone-heading">
    <h4>Collection Report (কালেকশন প্রতিবেদন)</h4>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('dealership.reports.collection.export', request()->query()) }}"><i class="bi bi-download"></i> CSV</a>
        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    </div>
</div>

@include('dealership.reports._nav')

<div class="filter-card mb-3">
    <form method="GET" action="{{ route('dealership.reports.collection.index') }}" class="row g-2">
        <div class="col-md-2"><label class="form-label">SR</label><input type="number" name="sr" class="form-control form-control-sm" value="{{ $filters['sr'] ?? '' }}"></div>
        <div class="col-md-2"><label class="form-label">Status</label><input type="text" name="status" class="form-control form-control-sm" value="{{ $filters['status'] ?? '' }}"></div>
        <div class="col-md-2"><label class="form-label">From</label><x-tdate-input class="form-control form-control-sm" name="from" :value="$filters['from'] ?? null" /></div>
        <div class="col-md-2"><label class="form-label">To</label><x-tdate-input class="form-control form-control-sm" name="to" :value="$filters['to'] ?? null" /></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-search"></i> Filter</button></div>
    </form>
</div>

<div class="admin-card mb-3">
    <div class="card-header">By SR / এসআর অনুযায়ী</div>
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead><tr><th>SR</th><th>Receipts</th><th>Amount</th></tr></thead>
            <tbody>
                @forelse($report['by_sr'] as $row)
                    <tr><td>{{ $row['sales_force_id'] }}</td><td>{{ $row['receipts'] }}</td><td>{{ number_format($row['amount'], 2) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted">কোনো তথ্য নেই (No data)</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card mb-3">
    <div class="card-header">Aging Buckets / বকেয়া বিশ্লেষণ</div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead><tr><th>Bucket</th><th>Count</th><th>Amount</th></tr></thead>
            <tbody>
                @foreach($report['aging'] as $bucket)
                    <tr><td>{{ $bucket['label'] }}</td><td>{{ $bucket['count'] }}</td><td>{{ number_format($bucket['amount'], 2) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card">
    <div class="card-header">Customer Outstanding / কাস্টমার বকেয়া</div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead><tr><th>Customer</th><th>Ordered</th><th>Collected</th><th>Outstanding</th></tr></thead>
            <tbody>
                @forelse($report['customer_outstanding'] as $row)
                    <tr><td>{{ $row['customer_id'] }}</td><td>{{ number_format($row['ordered'], 2) }}</td><td>{{ number_format($row['collected'], 2) }}</td><td>{{ number_format($row['outstanding'], 2) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted">কোনো তথ্য নেই (No data)</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

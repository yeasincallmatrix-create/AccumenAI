@extends('layouts.standalone')
@section('title', 'Comparative Balance Sheet — AccumenAI')
@section('page_title', 'Reports')

@section('content')
<div class="standalone-heading">
    <h4>Comparative Balance Sheet</h4>
    <p>Balance sheet comparison between current and prior date.</p>
    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
</div>

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Current Date</label>
                <input type="date" class="form-control form-control-sm" name="current_date" value="{{ $current_date }}">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Prior Date</label>
                <input type="date" class="form-control form-control-sm" name="prior_date" value="{{ $prior_date }}">
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-primary btn-sm mt-1" type="submit"><i class="bi bi-search"></i> Filter</button>
            </div>
        </div>
    </form>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="admin-card">
            <h6 class="card-title">Current ({{ $current_date }})</h6>
            <table class="table table-sm mb-0">
                <thead><tr><th>Category</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                    <tr><td>Total Assets</td><td class="text-end fw-semibold">{{ number_format($current['total_assets'], 2) }}</td></tr>
                    <tr><td>Total Liabilities</td><td class="text-end fw-semibold">{{ number_format($current['total_liabilities'], 2) }}</td></tr>
                    <tr><td>Total Equity</td><td class="text-end fw-semibold">{{ number_format($current['total_equity'], 2) }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card">
            <h6 class="card-title">Prior ({{ $prior_date }})</h6>
            <table class="table table-sm mb-0">
                <thead><tr><th>Category</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                    <tr><td>Total Assets</td><td class="text-end fw-semibold">{{ number_format($prior['total_assets'], 2) }}</td></tr>
                    <tr><td>Total Liabilities</td><td class="text-end fw-semibold">{{ number_format($prior['total_liabilities'], 2) }}</td></tr>
                    <tr><td>Total Equity</td><td class="text-end fw-semibold">{{ number_format($prior['total_equity'], 2) }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="admin-card mt-4">
    <h6 class="card-title">Variance Summary</h6>
    <table class="table table-sm mb-0">
        <thead><tr><th>Metric</th><th class="text-end">Current</th><th class="text-end">Prior</th><th class="text-end">Variance</th><th class="text-end">%</th></tr></thead>
        <tbody>
            @foreach (['total_assets' => 'Total Assets', 'total_liabilities' => 'Total Liabilities', 'total_equity' => 'Total Equity'] as $key => $label)
            <tr>
                <td>{{ $label }}</td>
                <td class="text-end">{{ number_format($current[$key], 2) }}</td>
                <td class="text-end">{{ number_format($prior[$key], 2) }}</td>
                <td class="text-end">{{ number_format($variance[$key], 2) }}</td>
                <td class="text-end">{{ $variance[str_replace('total_', '', $key) . '_pct'] }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection

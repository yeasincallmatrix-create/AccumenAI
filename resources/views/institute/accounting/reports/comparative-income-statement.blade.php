@extends('layouts.standalone')
@section('title', 'Comparative Income Statement — AccumenAI')
@section('page_title', 'Reports')

@section('content')
<div class="standalone-heading">
    <h4>Comparative Income Statement</h4>
    <p>Income statement comparison between current and prior period.</p>
    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
</div>

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Current From</label>
                <input type="date" class="form-control form-control-sm" name="current_from" value="{{ $current_from }}">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Current To</label>
                <input type="date" class="form-control form-control-sm" name="current_to" value="{{ $current_to }}">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Prior From</label>
                <input type="date" class="form-control form-control-sm" name="prior_from" value="{{ $prior_from }}">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Prior To</label>
                <input type="date" class="form-control form-control-sm" name="prior_to" value="{{ $prior_to }}">
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
            <h6 class="card-title">Current Period ({{ $current_from }} to {{ $current_to }})</h6>
            <table class="table table-sm mb-0">
                <thead><tr><th>Account</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                    @forelse ($current['income'] as $row)
                        <tr><td>{{ $row->name }}</td><td class="text-end">{{ number_format($row->balance, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="text-muted text-center">No income</td></tr>
                    @endforelse
                    <tr class="fw-bold"><td>Total Income</td><td class="text-end">{{ number_format($current['total_income'], 2) }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card">
            <h6 class="card-title">Prior Period ({{ $prior_from }} to {{ $prior_to }})</h6>
            <table class="table table-sm mb-0">
                <thead><tr><th>Account</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                    @forelse ($prior['income'] as $row)
                        <tr><td>{{ $row->name }}</td><td class="text-end">{{ number_format($row->balance, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="text-muted text-center">No income</td></tr>
                    @endforelse
                    <tr class="fw-bold"><td>Total Income</td><td class="text-end">{{ number_format($prior['total_income'], 2) }}</td></tr>
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
            <tr>
                <td>Total Income</td>
                <td class="text-end">{{ number_format($current['total_income'], 2) }}</td>
                <td class="text-end">{{ number_format($prior['total_income'], 2) }}</td>
                <td class="text-end">{{ number_format($variance['total_income'], 2) }}</td>
                <td class="text-end">{{ $variance['income_pct'] }}%</td>
            </tr>
            <tr>
                <td>Total Expense</td>
                <td class="text-end">{{ number_format($current['total_expense'], 2) }}</td>
                <td class="text-end">{{ number_format($prior['total_expense'], 2) }}</td>
                <td class="text-end">{{ number_format($variance['total_expense'], 2) }}</td>
                <td class="text-end">{{ $variance['expense_pct'] }}%</td>
            </tr>
            <tr class="fw-bold">
                <td>Net Income</td>
                <td class="text-end">{{ number_format($current['net'], 2) }}</td>
                <td class="text-end">{{ number_format($prior['net'], 2) }}</td>
                <td class="text-end">{{ number_format($variance['net'], 2) }}</td>
                <td class="text-end">{{ $variance['net_pct'] }}%</td>
            </tr>
        </tbody>
    </table>
</div>
@endsection

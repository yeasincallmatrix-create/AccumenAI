@extends('layouts.standalone')
@section('title', 'VAT Summary — AccumenAI')
@section('page_title', 'Reports')

@section('content')
<div class="standalone-heading">
    <h4>VAT Summary</h4>
    <p>Output VAT, input VAT, and net liability for the selected period.</p>
    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
</div>

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">From</label>
                <input type="date" class="form-control form-control-sm" name="from" value="{{ $from }}">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">To</label>
                <input type="date" class="form-control form-control-sm" name="to" value="{{ $to }}">
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-primary btn-sm mt-1" type="submit"><i class="bi bi-search"></i> Filter</button>
            </div>
        </div>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="admin-card text-center">
            <small class="text-muted">Output VAT (Collected)</small>
            <h4 class="mb-0 mt-1">{{ number_format($output_vat, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card text-center">
            <small class="text-muted">Input VAT (Paid)</small>
            <h4 class="mb-0 mt-1">{{ number_format($input_vat, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card text-center">
            <small class="text-muted">Net Liability</small>
            <h4 class="mb-0 mt-1 {{ $net_liability > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($net_liability, 2) }}</h4>
        </div>
    </div>
</div>

<div class="admin-card">
    <h6 class="card-title">Summary ({{ $from }} to {{ $to }})</h6>
    <table class="table table-sm mb-0">
        <thead><tr><th>Metric</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
            <tr><td>Output VAT (Collected)</td><td class="text-end">{{ number_format($output_vat, 2) }}</td></tr>
            <tr><td>Input VAT (Paid)</td><td class="text-end">{{ number_format($input_vat, 2) }}</td></tr>
            <tr class="fw-bold"><td>Net Liability</td><td class="text-end {{ $net_liability > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($net_liability, 2) }}</td></tr>
            <tr><td>Total Tax Transactions</td><td class="text-end">{{ $transactions }}</td></tr>
        </tbody>
    </table>
</div>
@endsection

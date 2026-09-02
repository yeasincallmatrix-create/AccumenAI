@extends('layouts.standalone')
@section('title', 'Tax Liability — AccumenAI')
@section('page_title', 'Reports')

@section('content')
<div class="standalone-heading">
    <h4>Tax Liability Breakdown</h4>
    <p>Outstanding tax balances as of the selected date.</p>
    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
</div>

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">As Of Date</label>
                <input type="date" class="form-control form-control-sm" name="as_of_date" value="{{ $as_of_date }}">
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-primary btn-sm mt-1" type="submit"><i class="bi bi-search"></i> Filter</button>
            </div>
        </div>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">VAT Payable</small>
            <h4 class="mb-0 mt-1">{{ number_format($vat_payable, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">WHT Payable</small>
            <h4 class="mb-0 mt-1">{{ number_format($wht_payable, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Tax Clearing</small>
            <h4 class="mb-0 mt-1">{{ number_format($tax_clearing, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Total Liability</small>
            <h4 class="mb-0 mt-1 text-danger">{{ number_format($total_liability, 2) }}</h4>
        </div>
    </div>
</div>

<div class="admin-card">
    <h6 class="card-title">Breakdown as of {{ $as_of_date }}</h6>
    <table class="table table-sm mb-0">
        <thead><tr><th>Account</th><th class="text-end">Balance</th></tr></thead>
        <tbody>
            <tr><td>VAT Payable (2100)</td><td class="text-end">{{ number_format($vat_payable, 2) }}</td></tr>
            <tr><td>WHT Payable (2101)</td><td class="text-end">{{ number_format($wht_payable, 2) }}</td></tr>
            <tr><td>Tax Clearing (2102)</td><td class="text-end">{{ number_format($tax_clearing, 2) }}</td></tr>
            <tr class="fw-bold"><td>Total Tax Liability</td><td class="text-end text-danger">{{ number_format($total_liability, 2) }}</td></tr>
        </tbody>
    </table>
</div>
@endsection

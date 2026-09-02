@extends('layouts.standalone')
@section('title', 'Executive Dashboard — AccumenAI')
@section('page_title', 'Executive')

@section('content')
<div class="standalone-heading">
    <h4>Executive Dashboard</h4>
    <p>Key performance indicators for {{ $institute->name }}.
        @if ($branch && $branch->id)
            <span class="badge text-bg-primary ms-1">Branch: {{ $branch->name }}</span>
        @else
            <span class="badge text-bg-light border ms-1">All branches</span>
        @endif
    </p>

    <form class="filter-layout d-flex align-items-end gap-2 flex-wrap" method="GET" action="{{ route('accounting.executive.index') }}">
        <div>
            <label class="form-label mb-1">From</label>
            <input type="date" class="form-control form-control-sm" name="from" value="{{ $from }}">
        </div>
        <div>
            <label class="form-label mb-1">To</label>
            <input type="date" class="form-control form-control-sm" name="to" value="{{ $to }}">
        </div>
        <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-funnel"></i> Apply</button>
    </form>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-xl-3">
        <a class="admin-card h-100 d-block text-decoration-none" href="{{ route('accounting.executive.revenue', ['from' => $from, 'to' => $to]) }}">
            <div class="small text-muted">Total Revenue</div>
            <div class="fs-5 fw-semibold text-success">{{ number_format($kpis['total_revenue'], 2) }}</div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <a class="admin-card h-100 d-block text-decoration-none" href="{{ route('accounting.executive.profit', ['from' => $from, 'to' => $to]) }}">
            <div class="small text-muted">Total Expenses</div>
            <div class="fs-5 fw-semibold text-danger">{{ number_format($kpis['total_expenses'], 2) }}</div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <a class="admin-card h-100 d-block text-decoration-none" href="{{ route('accounting.executive.profit', ['from' => $from, 'to' => $to]) }}">
            <div class="small text-muted">Net Income</div>
            <div class="fs-5 fw-semibold {{ $kpis['net_income'] < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($kpis['net_income'], 2) }}</div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <a class="admin-card h-100 d-block text-decoration-none" href="{{ route('accounting.executive.cash') }}">
            <div class="small text-muted">Cash Balance</div>
            <div class="fs-5 fw-semibold">{{ number_format($kpis['cash_balance'], 2) }}</div>
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-xl-3">
        <a class="admin-card h-100 d-block text-decoration-none" href="{{ route('accounting.reports.receivables') }}">
            <div class="small text-muted">Accounts Receivable</div>
            <div class="fs-5 fw-semibold">{{ number_format($kpis['accounts_receivable'], 2) }}</div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <a class="admin-card h-100 d-block text-decoration-none" href="{{ route('accounting.reports.payables') }}">
            <div class="small text-muted">Accounts Payable</div>
            <div class="fs-5 fw-semibold text-danger">{{ number_format($kpis['accounts_payable'], 2) }}</div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <div class="admin-card h-100">
            <div class="small text-muted">Active Customers</div>
            <div class="fs-5 fw-semibold">{{ number_format($kpis['active_customers']) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <div class="admin-card h-100">
            <div class="small text-muted">Active Suppliers</div>
            <div class="fs-5 fw-semibold">{{ number_format($kpis['active_suppliers']) }}</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="admin-card h-100">
            <h6>Quick Links</h6>
            <ul class="list-unstyled mb-0">
                <li class="mb-2"><a href="{{ route('accounting.executive.revenue', ['from' => $from, 'to' => $to]) }}" class="text-decoration-none">Revenue Analytics <i class="bi bi-arrow-right"></i></a></li>
                <li class="mb-2"><a href="{{ route('accounting.executive.profit', ['from' => $from, 'to' => $to]) }}" class="text-decoration-none">Profit Analysis <i class="bi bi-arrow-right"></i></a></li>
                <li class="mb-2"><a href="{{ route('accounting.executive.cash') }}" class="text-decoration-none">Cash Forecast <i class="bi bi-arrow-right"></i></a></li>
                <li class="mb-2"><a href="{{ route('accounting.executive.insights', ['from' => $from, 'to' => $to]) }}" class="text-decoration-none">Business Insights <i class="bi bi-arrow-right"></i></a></li>
            </ul>
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card h-100">
            <h6>Financial Snapshot</h6>
            <table class="table table-sm mb-0">
                <tbody>
                    <tr><td>Revenue</td><td class="text-end">{{ number_format($kpis['total_revenue'], 2) }}</td></tr>
                    <tr><td>Expenses</td><td class="text-end text-danger">{{ number_format($kpis['total_expenses'], 2) }}</td></tr>
                    <tr class="table-light"><td><strong>Net Income</strong></td><td class="text-end fw-bold {{ $kpis['net_income'] < 0 ? 'text-danger' : 'text-success' }}"><strong>{{ number_format($kpis['net_income'], 2) }}</strong></td></tr>
                    <tr><td>Cash Balance</td><td class="text-end">{{ number_format($kpis['cash_balance'], 2) }}</td></tr>
                    <tr><td>AR Total</td><td class="text-end">{{ number_format($kpis['accounts_receivable'], 2) }}</td></tr>
                    <tr><td>AP Total</td><td class="text-end">{{ number_format($kpis['accounts_payable'], 2) }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

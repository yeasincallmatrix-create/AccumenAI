@extends('layouts.institute')

@section('title', 'Budget Forecast — AccumenAI')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-graph-up me-2"></i>Budget Forecast</h4>
    <a class="btn btn-outline-primary btn-sm rounded-pill" href="{{ route('finance.budgets.dashboard') }}"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
</div>

@if (!$fiscalYear)
    <div class="alert alert-info">No current fiscal year found.</div>
@endif

@if ($forecast)
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-muted small">Budget Total</div>
                    <div class="fs-5 fw-bold">{{ number_format($forecast['budget_total'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-muted small">Forecast Income</div>
                    <div class="fs-5 fw-bold text-success">{{ number_format($forecast['forecast_income'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-muted small">Forecast Expense</div>
                    <div class="fs-5 fw-bold text-danger">{{ number_format($forecast['forecast_expense'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="text-muted small">Year-End Variance</div>
                    <div class="fs-5 fw-bold {{ $forecast['year_end_variance'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($forecast['year_end_variance'], 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="text-muted small mb-2">Year Progress</div>
            <div class="progress" style="height: 30px;">
                <div class="progress-bar bg-primary" style="width: {{ $forecast['progress_pct'] }}%">{{ $forecast['progress_pct'] }}% elapsed</div>
            </div>
            <div class="d-flex justify-content-between small text-muted mt-1">
                <span>{{ $fiscalYear->start_date }}</span>
                <span>{{ $forecast['remaining_days'] }} days remaining</span>
                <span>{{ $fiscalYear->end_date }}</span>
            </div>
        </div>
    </div>
@endif

@if ($cashFlow && $cashFlow['monthly']->count())
    <div class="card">
        <div class="card-header fw-semibold"><i class="bi bi-cash-stack me-1"></i>Cash Flow Plan</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Period</th><th class="text-end">Income</th><th class="text-end">Expense</th><th class="text-end">Net Flow</th><th class="text-end">Cumulative Net</th><th>Type</th></tr>
                </thead>
                <tbody>
                    @foreach ($cashFlow['monthly'] as $m)
                        <tr class="{{ $m['is_actual'] ? '' : 'text-muted' }}">
                            <td>{{ $m['period'] }}</td>
                            <td class="text-end">{{ number_format($m['income'], 2) }}</td>
                            <td class="text-end">{{ number_format($m['expense'], 2) }}</td>
                            <td class="text-end {{ $m['net_cash_flow'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($m['net_cash_flow'], 2) }}</td>
                            <td class="text-end {{ $m['cumulative_net'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($m['cumulative_net'], 2) }}</td>
                            <td><span class="badge {{ $m['is_actual'] ? 'bg-success' : 'bg-secondary' }}">{{ $m['is_actual'] ? 'Actual' : 'Planned' }}</span></td>
                        </tr>
                    @endforeach
                    <tr class="table-active fw-bold">
                        <td>Total</td>
                        <td class="text-end">{{ number_format($cashFlow['total_income'], 2) }}</td>
                        <td class="text-end">{{ number_format($cashFlow['total_expense'], 2) }}</td>
                        <td class="text-end {{ $cashFlow['net_cash_flow'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($cashFlow['net_cash_flow'], 2) }}</td>
                        <td></td><td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection

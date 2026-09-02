@extends('layouts.institute')

@section('title', 'Budget Dashboard — AccumenAI')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-wallet2 me-2"></i>Budget Dashboard</h4>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-primary btn-sm rounded-pill" href="{{ route('finance.budgets.index') }}"><i class="bi bi-list me-1"></i>All Budgets</a>
        <a class="btn btn-outline-primary btn-sm rounded-pill" href="{{ route('finance.budgets.reports') }}"><i class="bi bi-bar-chart me-1"></i>Reports</a>
    </div>
</div>

@if (!$fiscalYear)
    <div class="alert alert-info">No current fiscal year found. Create a fiscal year under Accounting > Fiscal Years to use budgeting.</div>
@endif

@if ($forecast)
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <div class="text-muted small">Budget Total</div>
                    <div class="fs-5 fw-bold text-primary">{{ number_format($forecast['budget_total'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <div class="text-muted small">Actual YTD</div>
                    <div class="fs-5 fw-bold text-success">{{ number_format($forecast['actual_expense'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <div class="text-muted small">Forecast (Year-End)</div>
                    <div class="fs-5 fw-bold text-warning">{{ number_format($forecast['forecast_expense'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <div class="text-muted small">Remaining Budget</div>
                    <div class="fs-5 fw-bold text-info">{{ number_format($forecast['remaining_expense'], 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small mb-2">Fiscal Year Progress</div>
                    <div class="progress" style="height: 24px;">
                        <div class="progress-bar bg-primary" style="width: {{ $forecast['progress_pct'] }}%">{{ $forecast['progress_pct'] }}%</div>
                    </div>
                    <div class="small text-muted mt-1">{{ $forecast['elapsed_days'] }} / {{ $forecast['total_days'] }} days elapsed</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small mb-2">Revenue</div>
                    <div>Budget: <strong>{{ number_format($forecast['budget_total'], 2) }}</strong></div>
                    <div>Actual YTD: <strong class="text-success">{{ number_format($forecast['actual_income'], 2) }}</strong></div>
                    <div>Forecast: <strong>{{ number_format($forecast['forecast_income'], 2) }}</strong></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small mb-2">Year-End Variance</div>
                    <div class="fs-4 fw-bold {{ $forecast['year_end_variance'] >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ number_format($forecast['year_end_variance'], 2) }}
                    </div>
                    <div class="small text-muted">Expected {{ $forecast['year_end_variance'] >= 0 ? 'under' : 'over' }} budget</div>
                </div>
            </div>
        </div>
    </div>
@endif

@if ($comparison && $comparison['lines']->count())
    <div class="card mb-4">
        <div class="card-header fw-semibold"><i class="bi bi-bar-chart-line me-1"></i>Budget vs Actual</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Account</th><th class="text-end">Budget</th><th class="text-end">Actual</th><th class="text-end">Variance</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @foreach ($comparison['lines']->take(15) as $line)
                        <tr>
                            <td>{{ $line['code'] }} — {{ $line['name'] }}</td>
                            <td class="text-end">{{ number_format($line['budget_amount'], 2) }}</td>
                            <td class="text-end">{{ number_format($line['actual_amount'], 2) }}</td>
                            <td class="text-end {{ $line['variance'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($line['variance'], 2) }}</td>
                            <td>
                                @if ($line['is_favorable'])
                                    <span class="badge bg-success">Favorable</span>
                                @else
                                    <span class="badge bg-danger">Unfavorable</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@if ($alerts && count($alerts) > 0)
    <div class="card border-warning">
        <div class="card-header bg-warning text-dark fw-semibold"><i class="bi bi-exclamation-triangle me-1"></i>Alerts ({{ count($alerts) }})</div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush">
                @foreach (array_slice($alerts, 0, 10) as $alert)
                    <div class="list-group-item d-flex justify-content-between">
                        <span><span class="badge bg-{{ $alert['level'] === 'severe' ? 'danger' : ($alert['level'] === 'critical' ? 'warning' : 'info') }} me-2">{{ ucfirst($alert['level']) }}</span>{{ $alert['message'] }}</span>
                        <span class="fw-semibold">{{ $alert['consumed_pct'] }}%</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
@endsection

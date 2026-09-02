@extends('layouts.standalone')
@section('title', 'Revenue Analytics — AccumenAI')
@section('page_title', 'Executive')

@section('content')
<div class="standalone-heading">
    <h4>Revenue Analytics</h4>
    <p>Monthly revenue trends and top revenue accounts.
        @if ($branch && $branch->id)
            <span class="badge text-bg-primary ms-1">Branch: {{ $branch->name }}</span>
        @else
            <span class="badge text-bg-light border ms-1">All branches</span>
        @endif
    </p>

    <form class="filter-layout d-flex align-items-end gap-2 flex-wrap" method="GET" action="{{ route('accounting.executive.revenue') }}">
        <div>
            <label class="form-label mb-1">From</label>
            <input type="date" class="form-control form-control-sm" name="from" value="{{ $from }}">
        </div>
        <div>
            <label class="form-label mb-1">To</label>
            <input type="date" class="form-control form-control-sm" name="to" value="{{ $to }}">
        </div>
        <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-funnel"></i> Apply</button>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('accounting.executive.index') }}"><i class="bi bi-arrow-left"></i> Back</a>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="admin-card text-center">
            <small class="text-muted">Current Period Revenue</small>
            <h4 class="mb-0 mt-1 text-success">{{ number_format($period_comparison['current_revenue'], 2) }}</h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card text-center">
            <small class="text-muted">Previous Period Revenue</small>
            <h4 class="mb-0 mt-1">{{ number_format($period_comparison['previous_revenue'], 2) }}</h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card text-center">
            <small class="text-muted">Period Change</small>
            <h4 class="mb-0 mt-1 {{ $period_comparison['change_percentage'] >= 0 ? 'text-success' : 'text-danger' }}">
                {{ $period_comparison['change_percentage'] >= 0 ? '+' : '' }}{{ $period_comparison['change_percentage'] }}%
            </h4>
        </div>
    </div>
</div>

<div class="admin-card mb-4">
    <h6>Monthly Revenue Trend</h6>
    @if ($monthly->isEmpty())
        <p class="text-muted mb-0">No data for the selected period.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th class="text-end">Revenue</th>
                        <th class="text-end">Expenses</th>
                        <th class="text-end">Profit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($monthly as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td class="text-end text-success">{{ number_format($row['revenue'], 2) }}</td>
                            <td class="text-end text-danger">{{ number_format($row['expense'], 2) }}</td>
                            <td class="text-end {{ $row['profit'] < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($row['profit'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-light fw-bold">
                        <td>Total</td>
                        <td class="text-end text-success">{{ number_format($monthly->sum('revenue'), 2) }}</td>
                        <td class="text-end text-danger">{{ number_format($monthly->sum('expense'), 2) }}</td>
                        <td class="text-end {{ $monthly->sum('profit') < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($monthly->sum('profit'), 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>

<div class="admin-card">
    <h6>Top Revenue Accounts</h6>
    @if ($top_accounts->isEmpty())
        <p class="text-muted mb-0">No revenue accounts with activity.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Account</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($top_accounts as $account)
                        <tr>
                            <td>{{ $account['code'] }}</td>
                            <td>{{ $account['name'] }}</td>
                            <td class="text-end text-success">{{ number_format($account['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection

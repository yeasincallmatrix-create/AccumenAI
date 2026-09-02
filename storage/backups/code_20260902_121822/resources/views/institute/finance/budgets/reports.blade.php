@extends('layouts.institute')

@section('title', 'Budget Reports — AccumenAI')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Budget Reports</h4>
    <div class="d-flex gap-2">
        @if ($fiscalYear)
            <a class="btn btn-outline-success btn-sm rounded-pill" href="{{ route('finance.budgets.export.comparison') }}"><i class="bi bi-download me-1"></i>Export CSV</a>
        @endif
        <a class="btn btn-outline-primary btn-sm rounded-pill" href="{{ route('finance.budgets.dashboard') }}"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
    </div>
</div>

@if (!$fiscalYear)
    <div class="alert alert-info">No current fiscal year found.</div>
@endif

@if ($comparison && $comparison['lines']->count())
    <div class="card mb-4">
        <div class="card-header fw-semibold">Budget vs Actual — {{ $fiscalYear->name }}</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Code</th><th>Account</th><th>Type</th>
                        <th class="text-end">Budget</th><th class="text-end">Actual</th>
                        <th class="text-end">Variance</th><th class="text-end">Variance %</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($comparison['lines'] as $line)
                        <tr>
                            <td class="text-muted">{{ $line['code'] }}</td>
                            <td class="fw-semibold">{{ $line['name'] }}</td>
                            <td><span class="badge bg-secondary">{{ ucfirst($line['type']) }}</span></td>
                            <td class="text-end">{{ number_format($line['budget_amount'], 2) }}</td>
                            <td class="text-end">{{ number_format($line['actual_amount'], 2) }}</td>
                            <td class="text-end {{ $line['variance'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($line['variance'], 2) }}</td>
                            <td class="text-end {{ $line['variance_pct'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $line['variance_pct'] }}%</td>
                            <td>@if ($line['is_favorable'])<span class="badge bg-success">Favorable</span>@else<span class="badge bg-danger">Unfavorable</span>@endif</td>
                        </tr>
                    @endforeach
                    <tr class="table-active fw-bold">
                        <td colspan="3">Totals</td>
                        <td class="text-end">{{ number_format($comparison['totals']['budget'], 2) }}</td>
                        <td class="text-end">{{ number_format($comparison['totals']['actual'], 2) }}</td>
                        <td class="text-end {{ $comparison['totals']['variance'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($comparison['totals']['variance'], 2) }}</td>
                        <td class="text-end">{{ $comparison['totals']['variance_pct'] }}%</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endif

@if ($monthly->count())
    <div class="card">
        <div class="card-header fw-semibold">Monthly Performance</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Period</th><th class="text-end">Income</th><th class="text-end">Expense</th><th class="text-end">Net</th></tr>
                </thead>
                <tbody>
                    @foreach ($monthly as $m)
                        <tr>
                            <td>{{ $m['period'] }}</td>
                            <td class="text-end">{{ number_format($m['income'], 2) }}</td>
                            <td class="text-end">{{ number_format($m['expense'], 2) }}</td>
                            <td class="text-end {{ $m['net'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($m['net'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection

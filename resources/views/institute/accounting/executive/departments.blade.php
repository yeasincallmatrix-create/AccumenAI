@extends('layouts.standalone')
@section('title', 'Department Reports — AccumenAI')
@section('page_title', 'Executive')

@section('content')
<div class="standalone-heading">
    <h4>Department Reports</h4>
    <p>Revenue and expense breakdown by department for {{ $institute->name }}.
        @if ($branch && $branch->id)
            <span class="badge text-bg-primary ms-1">Branch: {{ $branch->name }}</span>
        @else
            <span class="badge text-bg-light border ms-1">All branches</span>
        @endif
    </p>

    <form class="filter-layout d-flex align-items-end gap-2 flex-wrap" method="GET" action="{{ route('accounting.executive.departments') }}">
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
            <small class="text-muted">Total Income</small>
            <h4 class="mb-0 mt-1 text-success">{{ number_format($total_income, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card text-center">
            <small class="text-muted">Total Expenses</small>
            <h4 class="mb-0 mt-1 text-danger">{{ number_format($total_expense, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card text-center">
            <small class="text-muted">Active Departments</small>
            <h4 class="mb-0 mt-1">{{ number_format($departments->count()) }}</h4>
        </div>
    </div>
</div>

<div class="admin-card mb-4">
    <h6>Department Headcount & Payroll</h6>
    @if ($departments->isEmpty())
        <p class="text-muted mb-0">No active departments.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th class="text-end">Active Staff</th>
                        <th class="text-end">Payroll Cost</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($departments as $dept)
                        <tr>
                            <td>{{ $dept['name'] }}</td>
                            <td class="text-end">{{ number_format($dept['active_employees']) }}</td>
                            <td class="text-end text-danger">{{ number_format($dept['payroll_cost'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-light fw-bold">
                        <td>Total</td>
                        <td class="text-end">{{ number_format($departments->sum('active_employees')) }}</td>
                        <td class="text-end text-danger">{{ number_format($departments->sum('payroll_cost'), 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="admin-card mb-4">
            <h6>Income by Account</h6>
            @if ($income_breakdown->isEmpty())
                <p class="text-muted mb-0">No income data.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($income_breakdown as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td class="text-end text-success">{{ number_format($row['amount'], 2) }}</td>
                                    <td class="text-end">{{ $total_income > 0 ? round(($row['amount'] / $total_income) * 100, 1) : 0 }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card mb-4">
            <h6>Expense by Account</h6>
            @if ($expense_breakdown->isEmpty())
                <p class="text-muted mb-0">No expense data.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($expense_breakdown as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td class="text-end text-danger">{{ number_format($row['amount'], 2) }}</td>
                                    <td class="text-end">{{ $total_expense > 0 ? round(($row['amount'] / $total_expense) * 100, 1) : 0 }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@extends('layouts.standalone')
@section('title', 'HR Analytics — AccumenAI')
@section('page_title', 'Executive')

@section('content')
<div class="standalone-heading">
    <h4>HR Analytics</h4>
    <p>Workforce KPIs for {{ $institute->name }}.
        @if ($branch && $branch->id)
            <span class="badge text-bg-primary ms-1">Branch: {{ $branch->name }}</span>
        @else
            <span class="badge text-bg-light border ms-1">All branches</span>
        @endif
    </p>

    <form class="filter-layout d-flex align-items-end gap-2 flex-wrap" method="GET" action="{{ route('accounting.executive.hr') }}">
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
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Total Employees</small>
            <h4 class="mb-0 mt-1">{{ number_format($total_employees) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Active Employees</small>
            <h4 class="mb-0 mt-1 text-success">{{ number_format($active_employees) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Payroll Cost</small>
            <h4 class="mb-0 mt-1 text-danger">{{ number_format($payroll_cost, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Attendance Rate</small>
            <h4 class="mb-0 mt-1 {{ $attendance_rate >= 90 ? 'text-success' : 'text-warning' }}">{{ $attendance_rate }}%</h4>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Attendance Records</small>
            <h4 class="mb-0 mt-1">{{ number_format($total_attendance_records) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Present Days</small>
            <h4 class="mb-0 mt-1 text-success">{{ number_format($present_records) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Leave Requests</small>
            <h4 class="mb-0 mt-1">{{ number_format($total_leave_requests) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Leave Utilization</small>
            <h4 class="mb-0 mt-1">{{ $leave_utilization }}%</h4>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="admin-card mb-4">
            <h6>Headcount by Employment Status</h6>
            @if ($headcount_by_status->isEmpty())
                <p class="text-muted mb-0">No employee data.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th class="text-end">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($headcount_by_status as $status => $count)
                                <tr>
                                    <td>{{ ucfirst(str_replace('_', ' ', $status)) }}</td>
                                    <td class="text-end">{{ number_format($count) }}</td>
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
            <h6>Active Headcount by Department</h6>
            @if ($headcount_by_department->isEmpty())
                <p class="text-muted mb-0">No department data.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th class="text-end">Active Staff</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($headcount_by_department as $dept => $count)
                                <tr>
                                    <td>{{ $dept ?? 'Unassigned' }}</td>
                                    <td class="text-end">{{ number_format($count) }}</td>
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

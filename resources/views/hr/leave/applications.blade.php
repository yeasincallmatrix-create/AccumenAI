@extends('layouts.institute')

@section('title', 'Leave Applications — HR')

@section('content')
<div class="standalone-heading">
    <h4>Leave Applications</h4>
    <p>Pending/approved/rejected/cancelled. Approved leave reflected in attendance.</p>
    <a href="{{ route('hr.leave.applications.create') }}" class="btn btn-sm btn-primary">New Application</a>
</div>

<div class="filter-card mb-3">
    <form method="GET" action="{{ route('hr.leave.applications') }}" class="d-flex flex-wrap gap-2">
        <select name="status" class="form-select form-select-sm"><option value="">All status</option>@foreach($statuses as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>@endforeach</select>
        <select name="employee_id" class="form-select form-select-sm"><option value="">All employees</option>@foreach($employees as $e)<option value="{{ $e->id }}" @selected((string)request('employee_id')===(string)$e->id)>{{ $e->display_name }}</option>@endforeach</select>
        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
    </form>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table small mb-0">
            <thead><tr><th>Employee</th><th>Type</th><th>Dates</th><th>Days</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                @forelse($applications as $a)
                    <tr>
                        <td>{{ $a->employee->display_name }}<div class="text-muted small">{{ $a->employee->employee_code }}</div></td>
                        <td>{{ $a->leaveType->name }}</td>
                        <td>{{ $a->start_date->format('Y-m-d') }} → {{ $a->end_date->format('Y-m-d') }}<div class="text-muted small">{{ $a->reason ?? '' }}</div></td>
                        <td>{{ $a->days_count }}</td>
                        <td><span class="badge {{ $a->status==='approved'?'text-bg-success':($a->status==='pending'?'text-bg-warning':'text-bg-secondary') }}">{{ $a->status }}</span></td>
                        <td>
                            @if($a->status==='pending')
                                <form method="POST" action="{{ route('hr.leave.applications.decide', $a) }}" class="d-inline">@csrf<button name="decision" value="approved" class="btn btn-sm btn-success">Approve</button></form>
                                <form method="POST" action="{{ route('hr.leave.applications.decide', $a) }}" class="d-inline">@csrf<button name="decision" value="rejected" class="btn btn-sm btn-outline-danger">Reject</button></form>
                            @endif
                            @if(in_array($a->status,['pending','approved']))
                                <form method="POST" action="{{ route('hr.leave.applications.decide', $a) }}" class="d-inline">@csrf<button name="decision" value="cancelled" class="btn btn-sm btn-outline-secondary">Cancel</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">No applications.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($applications->hasPages())<div class="p-2 border-top">{{ $applications->links() }}</div>@endif
</div>
@endsection

@extends('layouts.institute')
@section('title','My Dashboard — HR Self-Service')
@section('content')
<div class="standalone-heading">
    <h4>My Self-Service Dashboard</h4>
    <p class="text-muted small">{{ $employee->display_name }} — {{ $employee->employee_code }}</p>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h5 mb-0">{{ $attendance['present'] }}</div><div class="text-muted small">Present (month)</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h5 mb-0">{{ $balances->sum(fn($b)=>$b->remaining()) }}</div><div class="text-muted small">Leave Balance Remaining</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h5 mb-0">{{ $payslips->count() }}</div><div class="text-muted small">Payslips</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h5 mb-0">{{ $documents->count() }}</div><div class="text-muted small">Documents</div></div></div>
</div>
<div class="row g-3">
    <div class="col-md-6">
        <div class="admin-card p-3">
            <h6>Employment</h6>
            <div class="small"><div>Department: <strong>{{ $employee->department?->name ?? '—' }}</strong></div><div>Designation: <strong>{{ $employee->designation?->name ?? '—' }}</strong></div><div>Branch: <strong>{{ $employee->branch?->name ?? '—' }}</strong></div><div>Status: <span class="badge text-bg-success">{{ $employee->employment_status }}</span></div></div>
            <a href="{{ route('hr.self.profile') }}" class="btn btn-sm btn-outline-primary mt-2">View Profile</a>
        </div>
        <div class="admin-card p-3 mt-3">
            <h6>Recent Leave (5)</h6>
            @foreach($leaveHistory as $l)<div class="small border-bottom py-1">{{ $l->leaveType?->name ?? 'Leave' }} {{ $l->start_date->format('Y-m-d') }} → {{ $l->end_date->format('Y-m-d') }} <span class="badge text-bg-secondary">{{ $l->status }}</span></div>@endforeach
            <a href="{{ route('hr.self.leave') }}" class="btn btn-sm btn-outline-primary mt-2">Manage Leave</a>
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card p-3">
            <h6>Attendance ({{ now()->format('Y-m') }})</h6>
            <div class="small">Present: {{ $attendance['present'] }}, Absent: {{ $attendance['absent'] }}, Late: {{ $attendance['late'] }}, Overtime: {{ $attendance['overtime_minutes'] }} mins</div>
            <a href="{{ route('hr.self.attendance') }}" class="btn btn-sm btn-outline-primary mt-2">View Attendance</a>
        </div>
        <div class="admin-card p-3 mt-3">
            <h6>Payslips</h6>
            @foreach($payslips as $p)<div class="small">{{ $p->period?->name ?? 'Period' }} — {{ number_format($p->net_salary,2) }} <span class="badge text-bg-secondary">{{ $p->status }}</span></div>@endforeach
            <a href="{{ route('hr.self.payslips') }}" class="btn btn-sm btn-outline-primary mt-2">View Payslips</a>
        </div>
    </div>
</div>
@endsection

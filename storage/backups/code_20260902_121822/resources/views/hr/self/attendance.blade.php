@extends('layouts.institute')
@section('title','My Attendance — HR')
@section('content')
<div class="standalone-heading">
    <h4>My Attendance — {{ $month }}</h4>
    <a href="{{ route('hr.self.dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="row g-3">
    <div class="col-md-8">
        <div class="admin-card p-3">
            <h6>Month Summary</h6>
            <div class="small">Present: {{ $summary['present'] }}, Absent: {{ $summary['absent'] }}, Late: {{ $summary['late'] }}, Leave: {{ $summary['leave'] }}, Half: {{ $summary['half_day'] }}, Overtime: {{ $summary['overtime_minutes'] }} mins</div>
            <div class="table-responsive mt-2">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Date</th><th>Status</th><th>In</th><th>Out</th></tr></thead>
                    <tbody>
                        @foreach($summary['records'] as $r)<tr><td>{{ $r->attendance_date->format('Y-m-d') }}</td><td><span class="badge text-bg-secondary">{{ $r->status }}</span></td><td>{{ $r->check_in ?? '—' }}</td><td>{{ $r->check_out ?? '—' }}</td></tr>@endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card p-3">
            <h6>Request Correction</h6>
            <form method="POST" action="{{ route('hr.self.attendance.correction') }}">
                @csrf
                <div class="mb-2"><label class="form-label small">Date *</label><input type="date" name="correction_date" class="form-control form-control-sm" required></div>
                <div class="mb-2"><label class="form-label small">Status *</label><select name="requested_status" class="form-select form-select-sm" required><option value="present">Present</option><option value="absent">Absent</option><option value="late">Late</option><option value="leave">Leave</option></select></div>
                <div class="mb-2"><label class="form-label small">Reason *</label><textarea name="reason" class="form-control form-control-sm" rows="2" required></textarea></div>
                <button type="submit" class="btn btn-primary btn-sm w-100">Submit</button>
            </form>
        </div>
        <div class="admin-card p-3 mt-3">
            <h6>Correction Status</h6>
            @foreach($corrections as $c)<div class="small border-bottom py-1">{{ $c->correction_date->format('Y-m-d') }} — {{ $c->requested_status }} <span class="badge text-bg-secondary">{{ $c->status }}</span></div>@endforeach
        </div>
    </div>
</div>
@endsection

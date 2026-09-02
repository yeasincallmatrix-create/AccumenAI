@extends('layouts.institute')

@section('title', 'Daily Attendance — HR')

@section('content')
<div class="standalone-heading">
    <h4>Daily Attendance — {{ $date }}</h4>
    <p>Employee workforce attendance (present/absent/late/early/holiday/weekend/half-day). Check-in/out with working duration, late/overtime.</p>
</div>

<div class="filter-card mb-3">
    <form method="GET" action="{{ route('hr.attendance.daily') }}" class="d-flex flex-wrap gap-2 align-items-end">
        <div><label class="form-label small">Date</label><input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm"></div>
        <div><label class="form-label small">Branch</label><select name="branch_id" class="form-select form-select-sm"><option value="">All</option>@foreach($branches as $b)<option value="{{ $b->id }}" @selected((string)($filters['branch_id']??'') === (string)$b->id)>{{ $b->name }}</option>@endforeach</select></div>
        <div><label class="form-label small">Department</label><select name="department_id" class="form-select form-select-sm"><option value="">All</option>@foreach($departments as $d)<option value="{{ $d->id }}" @selected((string)($filters['department_id']??'') === (string)$d->id)>{{ $d->name }}</option>@endforeach</select></div>
        <div><label class="form-label small">Status</label><select name="status" class="form-select form-select-sm"><option value="">All</option>@foreach($statuses as $s)<option value="{{ $s }}" @selected(($filters['status']??'') === $s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>@endforeach</select></div>
        <div><label class="form-label small">Search</label><input type="search" name="q" value="{{ $filters['q']??'' }}" class="form-control form-control-sm" placeholder="Name or code"></div>
        <div><button type="submit" class="btn btn-sm btn-primary">Filter</button> <a href="{{ route('hr.attendance.daily') }}" class="btn btn-sm btn-outline-secondary">Reset</a></div>
    </form>
</div>

<div class="admin-card p-3 mb-3">
    <h6>Mark Attendance (Manual)</h6>
    <form method="POST" action="{{ route('hr.attendance.mark') }}" class="row g-2">
        @csrf
        <div class="col-md-3"><label class="form-label small">Employee *</label><select name="employee_id" class="form-select form-select-sm" required><option value="">— Select —</option>@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->display_name }} ({{ $e->employee_code }})</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label small">Date *</label><input type="date" name="attendance_date" value="{{ $date }}" class="form-control form-control-sm" required></div>
        <div class="col-md-2"><label class="form-label small">Status *</label><select name="status" class="form-select form-select-sm" required><option value="present">Present</option><option value="absent">Absent</option><option value="late">Late</option><option value="early_departure">Early Departure</option><option value="half_day">Half Day</option><option value="leave">Leave</option><option value="holiday">Holiday</option><option value="weekend">Weekend</option></select></div>
        <div class="col-md-1"><label class="form-label small">In</label><input type="time" name="check_in" class="form-control form-control-sm"></div>
        <div class="col-md-1"><label class="form-label small">Out</label><input type="time" name="check_out" class="form-control form-control-sm"></div>
        <div class="col-md-3 d-flex align-items-end"><button type="submit" class="btn btn-sm btn-primary">Save</button></div>
    </form>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0 small">
            <thead><tr><th>Employee</th><th>Status</th><th>In</th><th>Out</th><th>Working</th><th>Late</th><th>OT</th><th>Source</th></tr></thead>
            <tbody>
                @forelse($attendances as $a)
                    <tr>
                        <td><a href="{{ route('hr.employees.show', $a->employee) }}" class="text-decoration-none">{{ $a->employee->display_name }}</a><div class="text-muted">{{ $a->employee->employee_code }} · {{ $a->employee->department?->name ?? '—' }}</div></td>
                        <td><span class="badge {{ $a->status === 'present' ? 'text-bg-success' : ($a->status === 'absent' ? 'text-bg-danger' : 'text-bg-secondary') }}">{{ $a->status }}</span></td>
                        <td>{{ $a->check_in ?? '—' }}</td>
                        <td>{{ $a->check_out ?? '—' }}</td>
                        <td>{{ $a->working_minutes !== null ? floor($a->working_minutes/60).'h '.($a->working_minutes%60).'m' : '—' }}</td>
                        <td>{{ $a->late_minutes ?? '—' }}</td>
                        <td>{{ $a->overtime_minutes ?? '—' }}</td>
                        <td>{{ $a->source }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-3">No attendance recorded for this date. Unrecorded ≠ absent.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($attendances->hasPages())<div class="p-2 border-top">{{ $attendances->links() }}</div>@endif
</div>

<div class="admin-card p-3 mt-3">
    <h6>Request Correction</h6>
    <form method="POST" action="{{ route('hr.attendance.corrections.request') }}" class="row g-2">
        @csrf
        <div class="col-md-3"><select name="employee_id" class="form-select form-select-sm" required><option value="">Employee</option>@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->display_name }}</option>@endforeach</select></div>
        <div class="col-md-2"><input type="date" name="correction_date" class="form-control form-control-sm" required></div>
        <div class="col-md-2"><select name="requested_status" class="form-select form-select-sm" required>@foreach($statuses as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach</select></div>
        <div class="col-md-2"><input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason *" required></div>
        <div class="col-md-3"><button type="submit" class="btn btn-sm btn-outline-primary">Request (preserves original)</button></div>
    </form>
</div>
@endsection

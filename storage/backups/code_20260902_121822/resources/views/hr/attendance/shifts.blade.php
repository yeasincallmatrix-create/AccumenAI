@extends('layouts.institute')

@section('title', 'Shifts — HR')

@section('content')
<div class="standalone-heading"><h4>Work Shifts</h4><p>Working days, shift start/end, grace period, branch/employee applicability.</p></div>

<div class="admin-card p-3 mb-3">
    <h6>Create Shift</h6>
    <form method="POST" action="{{ route('hr.attendance.shifts.store') }}" class="row g-2">
        @csrf
        <div class="col-md-2"><input type="text" name="name" class="form-control form-control-sm" placeholder="Name *" required></div>
        <div class="col-md-2"><select name="branch_id" class="form-select form-select-sm"><option value="">All branches</option>@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><select name="employee_id" class="form-select form-select-sm"><option value="">All employees</option>@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->display_name }}</option>@endforeach</select></div>
        <div class="col-md-1"><input type="time" name="start_time" class="form-control form-control-sm" required></div>
        <div class="col-md-1"><input type="time" name="end_time" class="form-control form-control-sm" required></div>
        <div class="col-md-1"><input type="number" name="grace_minutes" class="form-control form-control-sm" placeholder="Grace"></div>
        <div class="col-md-3"><button type="submit" class="btn btn-sm btn-primary">Create</button></div>
    </form>
    <div class="small text-muted mt-1">Working days default Mon-Fri (1-5). Store as JSON array.</div>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table small mb-0">
            <thead><tr><th>Name</th><th>Branch</th><th>Employee</th><th>Start</th><th>End</th><th>Grace</th><th>Days</th><th></th></tr></thead>
            <tbody>
                @forelse($shifts as $s)
                    <tr>
                        <td>{{ $s->name }}</td>
                        <td>{{ $s->branch?->name ?? 'All' }}</td>
                        <td>{{ $s->employee?->display_name ?? 'All' }}</td>
                        <td>{{ $s->start_time }}</td>
                        <td>{{ $s->end_time }}</td>
                        <td>{{ $s->grace_minutes }}m</td>
                        <td>{{ is_array($s->working_days) ? implode(',',$s->working_days) : $s->working_days }}</td>
                        <td><form method="POST" action="{{ route('hr.attendance.shifts.destroy', $s) }}" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-3">No shifts.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($shifts->hasPages())<div class="p-2 border-top">{{ $shifts->links() }}</div>@endif
</div>
@endsection

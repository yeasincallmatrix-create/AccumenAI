@extends('layouts.institute')
@section('title','Attendance Report')
@section('content')
<div class="standalone-heading"><h4>Attendance Report</h4><a href="{{ route('hr.reports.attendance.export', request()->query()) }}" class="btn btn-primary btn-sm">Export CSV</a></div>
<form method="GET" class="admin-card p-3 mb-3 row g-2"><div class="col-md-3"><input type="date" name="from" class="form-control form-control-sm" value="{{ request('from', $data['from'] ?? '') }}"></div><div class="col-md-3"><input type="date" name="to" class="form-control form-control-sm" value="{{ request('to', $data['to'] ?? '') }}"></div><div class="col-md-3"><select name="branch_id" class="form-select form-select-sm"><option value="">All Branches</option></select></div><div class="col-md-3"><button type="submit" class="btn btn-primary btn-sm w-100">Filter</button></div></form>
<div class="admin-card p-3 mb-3"><div>Total: {{ $data['total'] }} — Late: {{ $data['late'] }} Absent: {{ $data['absent'] }} Overtime: {{ $data['overtime_minutes'] }} mins</div><div>By Status: @foreach($data['by_status'] as $s=>$c)<span class="badge bg-light text-dark border me-1">{{ $s }}: {{ $c }}</span> @endforeach</div></div>
<div class="admin-card"><table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Employee</th><th>Status</th></tr></thead><tbody>@foreach($data['rows'] as $r)<tr><td>{{ $r->attendance_date->format('Y-m-d') }}</td><td>{{ $r->employee->display_name ?? $r->employee_id }}</td><td>{{ $r->status }}</td></tr>@endforeach</tbody></table></div>
@endsection

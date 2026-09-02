@extends('layouts.institute')
@section('title','Employee Report')
@section('content')
<div class="standalone-heading"><h4>Employee Report</h4><a href="{{ route('hr.reports.index') }}" class="btn btn-outline-secondary btn-sm">All Reports</a> <a href="{{ route('hr.reports.employee.export', request()->query()) }}" class="btn btn-primary btn-sm">Export CSV</a></div>
<form method="GET" class="admin-card p-3 mb-3 row g-2">
    <div class="col-md-3"><select name="employment_status" class="form-select form-select-sm"><option value="">All Status</option><option value="active" @selected(request('employment_status')=='active')>Active</option><option value="inactive" @selected(request('employment_status')=='inactive')>Inactive</option></select></div>
    <div class="col-md-3"><input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}" placeholder="From"></div>
    <div class="col-md-3"><input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}" placeholder="To"></div>
    <div class="col-md-3"><button type="submit" class="btn btn-primary btn-sm w-100">Filter</button></div>
</form>
<div class="admin-card p-3 mb-3"><div>Total: <strong>{{ $data['total'] }}</strong> — By Status: @foreach($data['by_status'] as $s=>$c)<span class="badge bg-light text-dark border me-1">{{ $s }}: {{ $c }}</span> @endforeach</div></div>
<div class="admin-card"><table class="table table-sm mb-0"><thead><tr><th>Code</th><th>Name</th><th>Dept</th><th>Branch</th><th>Status</th></tr></thead><tbody>@foreach($data['rows'] as $e)<tr><td><code>{{ $e->employee_code }}</code></td><td>{{ $e->display_name }}</td><td>{{ $e->department?->name ?? '—' }}</td><td>{{ $e->branch?->name ?? '—' }}</td><td>{{ $e->employment_status }}</td></tr>@endforeach</tbody></table></div>
@endsection

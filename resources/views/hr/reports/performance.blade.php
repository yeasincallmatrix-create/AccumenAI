@extends('layouts.institute')
@section('title','Performance Report')
@section('content')
<div class="standalone-heading"><h4>Performance Report</h4><a href="{{ route('hr.reports.performance.export', request()->query()) }}" class="btn btn-primary btn-sm">Export CSV</a></div>
<form method="GET" class="admin-card p-3 mb-3 row g-2"><div class="col-md-4"><input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}"></div><div class="col-md-4"><input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}"></div><div class="col-md-4"><button type="submit" class="btn btn-primary btn-sm w-100">Filter</button></div></form>
<div class="admin-card p-3 mb-3"><div>Total Reviews: {{ $data['total'] }} Avg Score: {{ $data['avg_score'] }}</div><div>By Status: @foreach($data['by_status'] as $s=>$c)<span class="badge bg-light text-dark border me-1">{{ $s }}: {{ $c }}</span> @endforeach</div></div>
<div class="admin-card"><table class="table table-sm mb-0"><thead><tr><th>Employee</th><th>Score</th><th>Status</th></tr></thead><tbody>@foreach($data['rows'] as $r)<tr><td>{{ $r->employee->display_name ?? $r->employee_id }}</td><td>{{ $r->overall_score }}</td><td>{{ $r->status }}</td></tr>@endforeach</tbody></table></div>
@endsection

@extends('layouts.institute')
@section('title','Training Report')
@section('content')
<div class="standalone-heading"><h4>Training Report</h4><a href="{{ route('hr.reports.training.export', request()->query()) }}" class="btn btn-primary btn-sm">Export CSV</a></div>
<form method="GET" class="admin-card p-3 mb-3 row g-2"><div class="col-md-4"><input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}"></div><div class="col-md-4"><input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}"></div><div class="col-md-4"><button type="submit" class="btn btn-primary btn-sm w-100">Filter</button></div></form>
<div class="admin-card p-3 mb-3"><div>Total Trainings: {{ $data['total_trainings'] }} Enrollments: {{ $data['total_enrollments'] }} Completed: {{ $data['completed'] }} Rate: {{ $data['completion_rate'] }}% Cost: {{ number_format($data['total_cost'],2) }}</div><div>Skill Gaps: @foreach($data['skill_gaps'] as $level=>$c)<span class="badge bg-light text-dark border me-1">{{ $level }}: {{ $c }}</span> @endforeach</div></div>
@endsection

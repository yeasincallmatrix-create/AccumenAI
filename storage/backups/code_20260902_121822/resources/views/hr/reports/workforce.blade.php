@extends('layouts.institute')
@section('title','Workforce Report')
@section('content')
<div class="standalone-heading"><h4>Workforce Report</h4><a href="{{ route('hr.reports.workforce.export', request()->query()) }}" class="btn btn-primary btn-sm">Export CSV</a></div>
<form method="GET" class="admin-card p-3 mb-3 row g-2"><div class="col-md-4"><input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}"></div><div class="col-md-4"><input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}"></div><div class="col-md-4"><button type="submit" class="btn btn-primary btn-sm w-100">Filter</button></div></form>
<div class="admin-card p-3 mb-3"><div>Headcount: <strong>{{ $data['headcount'] }}</strong> Active: {{ $data['active'] }} New Hires: {{ $data['new_hires'] }} Resignations: {{ $data['resignations'] }} Terminations: {{ $data['terminations'] }} Turnover: {{ $data['turnover_rate'] }}%</div><div class="mt-2">Trend: @foreach($data['trend'] as $m=>$c)<span class="badge bg-light text-dark border me-1">{{ $m }}: {{ $c }}</span> @endforeach</div></div>
@endsection

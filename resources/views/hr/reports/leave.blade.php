@extends('layouts.institute')
@section('title','Leave Report')
@section('content')
<div class="standalone-heading"><h4>Leave Report</h4><a href="{{ route('hr.reports.leave.export', request()->query()) }}" class="btn btn-primary btn-sm">Export CSV</a></div>
<form method="GET" class="admin-card p-3 mb-3 row g-2"><div class="col-md-4"><input type="date" name="from" class="form-control form-control-sm" value="{{ request('from', $data['from'] ?? '') }}"></div><div class="col-md-4"><input type="date" name="to" class="form-control form-control-sm" value="{{ request('to', $data['to'] ?? '') }}"></div><div class="col-md-4"><button type="submit" class="btn btn-primary btn-sm w-100">Filter</button></div></form>
<div class="admin-card p-3 mb-3"><div>Total Applications: {{ $data['total'] }} — Pending: {{ $data['pending'] }}</div><div>By Status: @foreach($data['by_status'] as $s=>$c)<span class="badge bg-light text-dark border me-1">{{ $s }}: {{ $c }}</span> @endforeach</div><div>Utilization: {{ $data['utilization'] }}</div></div>
@endsection

@extends('layouts.institute')
@section('title','Recruitment Report')
@section('content')
<div class="standalone-heading"><h4>Recruitment Report</h4><a href="{{ route('hr.reports.recruitment.export', request()->query()) }}" class="btn btn-primary btn-sm">Export CSV</a></div>
<form method="GET" class="admin-card p-3 mb-3 row g-2"><div class="col-md-4"><x-tdate-input name="from" :value="request('from', $data['from'] ?? '')" class="form-control form-control-sm" /></div><div class="col-md-4"><x-tdate-input name="to" :value="request('to', $data['to'] ?? '')" class="form-control form-control-sm" /></div><div class="col-md-4"><button type="submit" class="btn btn-primary btn-sm w-100">Filter</button></div></form>
<div class="admin-card p-3 mb-3"><div>Vacancies: {{ $data['vacancies'] }} Applicants: {{ $data['applicants'] }} Hiring Rate: {{ $data['hiring_rate'] }}%</div><div>By Stage: @foreach($data['by_stage'] as $s=>$c)<span class="badge bg-light text-dark border me-1">{{ $s }}: {{ $c }}</span> @endforeach</div><div>By Source: @foreach($data['by_source'] as $s=>$c)<span class="badge bg-light text-dark border me-1">Source #{{ $s }}: {{ $c }}</span> @endforeach</div></div>
@endsection

@extends('layouts.institute')
@section('title','HR Reports — AccumenAI')
@section('content')
<div class="standalone-heading"><h4>HR Reports</h4><p class="text-muted small">Industry-neutral reports: employee, workforce, attendance, leave, payroll, recruitment, performance, training.</p></div>
<div class="row g-3">
    @foreach (['employee'=>'Employee','workforce'=>'Workforce','attendance'=>'Attendance','leave'=>'Leave','payroll'=>'Payroll','recruitment'=>'Recruitment','performance'=>'Performance','training'=>'Training'] as $k=>$l)
        <div class="col-md-3"><a href="{{ route('hr.reports.'.$k) }}" class="admin-card p-3 d-block text-decoration-none"><strong>{{ $l }}</strong><div class="text-muted small">View {{ $l }} report</div></a></div>
    @endforeach
</div>
@endsection

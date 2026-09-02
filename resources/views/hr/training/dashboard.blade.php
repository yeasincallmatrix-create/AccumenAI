@extends('layouts.institute')
@section('title','Training Dashboard — HR')
@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Training & Development <span class="badge bg-success ms-2" style="font-size:.7rem"><i class="bi bi-circle-fill me-1" style="font-size:.45rem"></i>Active</span></h4>
        <p class="page-header-desc mb-0">Programs, enrollments, skills, certificates.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('hr.training.programs') }}" class="btn btn-primary btn-sm">Programs</a>
        <a href="{{ route('hr.training.skills') }}" class="btn btn-outline-secondary btn-sm">Skills</a>
    </div>
</div>

@include('hr._payroll_tabs')

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h4 mb-0">{{ $stats['totalTrainings'] }}</div><div class="text-muted small">Programs</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h4 mb-0">{{ $stats['enrollments'] }}</div><div class="text-muted small">Enrollments</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h4 mb-0 text-success">{{ $stats['completed'] }}</div><div class="text-muted small">Completed</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h4 mb-0">{{ number_format($stats['cost'],0) }}</div><div class="text-muted small">Total Cost</div></div></div>
</div>
<div class="admin-card p-3"><h6>Reports</h6><ul class="small text-muted mb-0"><li>Training participation by department/branch</li><li>Training cost summary</li><li>Skill gaps & development history</li></ul></div>
@endsection

@extends('layouts.institute')
@section('title','Performance Dashboard — HR')
@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Performance Management <span class="badge bg-success ms-2" style="font-size:.7rem"><i class="bi bi-circle-fill me-1" style="font-size:.45rem"></i>Active</span></h4>
        <p class="page-header-desc mb-0">Review periods, KPIs, evaluations, history.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('hr.performance.reviews') }}" class="btn btn-primary btn-sm">Reviews</a>
        <a href="{{ route('hr.performance.periods') }}" class="btn btn-outline-secondary btn-sm">Periods</a>
        <a href="{{ route('hr.performance.kpis') }}" class="btn btn-outline-secondary btn-sm">KPIs</a>
    </div>
</div>

@include('hr._payroll_tabs')

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h4 mb-0">{{ $stats['totalReviews'] }}</div><div class="text-muted small">Total Reviews</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h4 mb-0 text-warning">{{ $stats['pending'] }}</div><div class="text-muted small">Pending</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h4 mb-0 text-success">{{ $stats['approved'] }}</div><div class="text-muted small">Approved</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h4 mb-0">{{ $stats['avgScore'] ? number_format($stats['avgScore'],1) : '—' }}</div><div class="text-muted small">Avg Score</div></div></div>
</div>
<div class="admin-card p-3"><h6>Reports</h6><ul class="small text-muted mb-0"><li>Performance summary by period</li><li>KPI report (weights & scores)</li><li>Review completion rate</li></ul></div>
@endsection

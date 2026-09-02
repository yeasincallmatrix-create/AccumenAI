@extends('layouts.institute')
@section('title','Recruitment Dashboard — HR')
@section('content')
<div class="standalone-heading">
    <h4>Recruitment Dashboard</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('hr.recruitment.requisitions') }}" class="btn btn-primary btn-sm">Requisitions</a>
        <a href="{{ route('hr.recruitment.vacancies') }}" class="btn btn-outline-primary btn-sm">Vacancies</a>
        <a href="{{ route('hr.recruitment.applications') }}" class="btn btn-outline-primary btn-sm">Applications</a>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h5 mb-0">{{ $stats['open_requisitions'] }}</div><div class="text-muted small">Open Requisitions</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h5 mb-0">{{ $stats['open_vacancies'] }}</div><div class="text-muted small">Open Vacancies</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h5 mb-0">{{ $stats['total_applications'] }}</div><div class="text-muted small">Applications</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3 text-center"><div class="h5 mb-0">{{ $stats['hired'] }}</div><div class="text-muted small">Hired</div></div></div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-6"><div class="admin-card p-3"><h6>Pipeline</h6>@foreach($stats['by_stage'] as $stage=>$count)<span class="badge bg-light text-dark border me-1">{{ $stage }}: {{ $count }}</span> @endforeach</div></div>
    <div class="col-md-6"><div class="admin-card p-3"><h6>Time to Hire</h6><div class="h6">{{ $stats['avg_time_to_hire'] }} days avg</div></div></div>
</div>
@if($openVacancies->isNotEmpty())
<div class="admin-card mb-4">
    <div class="p-3 border-bottom"><h6 class="mb-0">Open Vacancies</h6></div>
    <table class="table table-sm mb-0">
        <thead><tr><th>Title</th><th>Openings</th><th>Status</th></tr></thead>
        <tbody>
            @foreach($openVacancies as $vac)<tr><td>{{ $vac->title }}</td><td>{{ $vac->openings }}</td><td><span class="badge text-bg-success">{{ $vac->status }}</span></td></tr>@endforeach
        </tbody>
    </table>
</div>
@endif
<h6>Recent Applications</h6>
<div class="admin-card">
    <table class="table table-sm mb-0">
        <thead><tr><th>Candidate</th><th>Vacancy</th><th>Stage</th></tr></thead>
        <tbody>
            @foreach($recentApps as $app)<tr><td>{{ $app->candidateLead->first_name }} {{ $app->candidateLead->last_name }}</td><td>{{ $app->vacancy?->title ?? '—' }}</td><td><span class="badge text-bg-secondary">{{ $app->current_stage }}</span></td></tr>@endforeach
        </tbody>
    </table>
</div>
@endsection

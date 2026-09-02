@extends('layouts.institute')
@section('title','Applications — HR')
@section('content')
<div class="standalone-heading">
    <h4>Applications</h4>
    <a href="{{ route('hr.recruitment.dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="admin-card p-3 mb-3">
    <h6>Create Application</h6>
    <form method="POST" action="{{ route('hr.recruitment.applications.store') }}" class="row g-2">
        @csrf
        <div class="col-md-3">
            <select name="vacancy_id" class="form-select form-select-sm"><option value="">Select Vacancy</option>@foreach($vacancies as $vac)<option value="{{ $vac->id }}">{{ $vac->title }}</option>@endforeach</select>
        </div>
        <div class="col-md-3">
            <select name="candidate_lead_id" class="form-select form-select-sm" required><option value="">Select Candidate (Lead) *</option>@foreach($leads as $lead)<option value="{{ $lead->id }}">{{ $lead->first_name }} {{ $lead->last_name }}</option>@endforeach</select>
        </div>
        <div class="col-md-3">
            <select name="source_id" class="form-select form-select-sm"><option value="">Source</option>@foreach($sources as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select>
        </div>
        <div class="col-md-3"><button type="submit" class="btn btn-primary btn-sm w-100">Apply</button></div>
    </form>
</div>
<div class="admin-card">
    <table class="table table-sm mb-0">
        <thead><tr><th>Candidate</th><th>Vacancy</th><th>Stage</th><th>Recruiter</th><th></th></tr></thead>
        <tbody>
            @foreach($apps as $app)
                <tr>
                    <td>{{ $app->candidateLead->first_name }} {{ $app->candidateLead->last_name }}</td>
                    <td>{{ $app->vacancy?->title ?? '—' }}</td>
                    <td><span class="badge text-bg-secondary">{{ $app->current_stage }}</span></td>
                    <td>{{ $app->recruiter?->email ?? '—' }}</td>
                    <td><a href="{{ route('hr.recruitment.applications.show',$app) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="p-2">{{ $apps->links() }}</div>
</div>
@endsection

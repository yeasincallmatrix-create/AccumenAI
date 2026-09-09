@extends('layouts.institute')

@section('title', 'Patients (React) — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Patients (React)</h4>
        <p class="page-header-subtitle text-muted mb-0">
            Searchable live list
            <a href="{{ route('medical.patients.index') }}" class="ms-2">Back to Patients</a>
        </p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div id="react-patients-container" data-props='@json($props)'></div>
        <noscript>
            <div class="alert alert-warning mb-0">The patient list needs JavaScript enabled.</div>
        </noscript>
    </div>
</div>
@endsection

@push('scripts')
    @viteReactRefresh
    @vite('resources/js/medical/patients.jsx')
@endpush

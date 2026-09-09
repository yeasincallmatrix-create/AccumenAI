@extends('layouts.institute')

@section('title', 'Prescriptions (React) — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Prescriptions (React)</h4>
        <p class="page-header-subtitle text-muted mb-0">
            Filterable live list
            <a href="{{ route('medical.prescriptions.index') }}" class="ms-2">Back to Prescriptions</a>
        </p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div id="react-prescriptions-container" data-props='@json($props)'></div>
        <noscript>
            <div class="alert alert-warning mb-0">The prescription list needs JavaScript enabled.</div>
        </noscript>
    </div>
</div>
@endsection

@push('scripts')
    @viteReactRefresh
    @vite('resources/js/medical/prescriptions.jsx')
@endpush

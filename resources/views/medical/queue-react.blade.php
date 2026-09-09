@extends('layouts.institute')

@section('title', 'Live Queue (React) — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Live Queue (React)</h4>
        <p class="page-header-subtitle text-muted mb-0">
            Dr. {{ $doctorName ?? '—' }} · {{ $date ?? '' }}
            <a href="{{ route('medical.appointments.index', ['tab' => 'queue']) }}" class="ms-2">Back to Appointments</a>
        </p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div id="react-queue-container" data-props='@json($props)'></div>
        <noscript>
            <div class="alert alert-warning mb-0">The live queue needs JavaScript enabled.</div>
        </noscript>
    </div>
</div>
@endsection

@push('scripts')
    @viteReactRefresh
    @vite('resources/js/medical/queue.jsx')
@endpush

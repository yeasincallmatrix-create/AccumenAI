@extends('layouts.institute')

@section('title', 'Patient Timeline — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h4 class="mb-0">{{ $patient->full_name ?? ($patient->first_name . ' ' . $patient->last_name) }}</h4>
                    <small class="text-muted">
                        MRN: {{ $patient->mr_number ?? $patient->id }} |
                        Age: {{ $patient->age ?? 'N/A' }} |
                        Gender: {{ ucfirst($patient->gender ?? 'N/A') }} |
                        Phone: {{ $patient->phone ?? 'N/A' }}
                    </small>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('medical.records.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> EMR
                    </a>
                    <form method="POST" action="{{ route('medical.records.patients.timeline.backfill', $patient) }}">
                        @csrf
                        <button type="submit" class="btn btn-warning btn-sm" title="Aggregate history from all modules">
                            <i class="bi bi-arrow-repeat"></i> Backfill
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small">Event types</label>
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($eventTypes as $key => $label)
                            <label class="badge border {{ in_array($key, $filters['event_types'] ?? []) ? 'bg-primary text-white' : 'bg-light text-dark' }}" style="cursor: pointer;">
                                <input type="checkbox" name="event_types[]" value="{{ $key }}" class="d-none"
                                    {{ in_array($key, $filters['event_types'] ?? []) ? 'checked' : '' }}
                                    onchange="this.form.submit()">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">From</label>
                    <input type="date" name="from" class="form-control form-control-sm" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">To</label>
                    <input type="date" name="to" class="form-control form-control-sm" value="{{ $filters['to'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> Filter</button>
                    <a href="{{ route('medical.records.patients.timeline', $patient) }}" class="btn btn-link btn-sm w-100">Clear</a>
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Unified Timeline ({{ $events->count() }} events)</h6>
        </div>
        <div class="card-body">
            @forelse($events as $event)
                @include('medical.records.timeline._event', ['event' => $event])
            @empty
                <p class="text-muted text-center mb-0">
                    No events found. Click <strong>Backfill</strong> to aggregate this patient's history from all modules.
                </p>
            @endforelse
        </div>
    </div>
</div>
@endsection

@extends('layouts.institute')

@section('title', 'Patient History — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Clinical Timeline — {{ $patient->full_name }} ({{ clinical_no($patient->mr_number) }})</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.patients.show', $patient) }}">
            <i class="bi bi-arrow-left me-1"></i>Back to Profile
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('medical.patients.history', $patient) }}" method="GET" class="row g-2">
            <div class="col-md-3">
                <label class="form-label" for="from">From</label>
                <input type="date" id="from" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="to">To</label>
                <input type="date" id="to" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="type">Event type</label>
                <select id="type" name="type" class="form-select">
                    <option value="">All visible types</option>
                    @foreach($eventTypes as $eventType)
                        <option value="{{ $eventType }}" {{ ($filters['type'] ?? '') === $eventType ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $eventType)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <a href="{{ route('medical.patients.history', $patient) }}" class="btn btn-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

@php
$typeBadges = [
    'admission' => 'primary', 'discharge' => 'success', 'transfer' => 'info',
    'encounter' => 'primary', 'diagnosis' => 'info', 'lab_order' => 'warning',
    'lab_result' => 'success', 'prescription' => 'dark', 'vital' => 'secondary',
    'nursing_note' => 'secondary',
];
@endphp

<div class="card mt-3">
    <div class="card-header"><h6 class="mb-0">Timeline ({{ $events->total() }} events)</h6></div>
    <div class="card-body">
        @if($events->count() > 0)
            @foreach($events as $event)
                <div class="card mb-2">
                    <div class="card-body py-2">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <span class="badge bg-{{ $typeBadges[$event['type']] ?? 'secondary' }}">
                                    {{ ucfirst(str_replace('_', ' ', $event['type'])) }}
                                </span>
                                <strong class="ms-2">{{ $event['title'] }}</strong>
                                @if($event['summary'] !== '')
                                    <div class="text-muted small mt-1">{{ $event['summary'] }}</div>
                                @endif
                            </div>
                            <div class="text-end">
                                <div class="small"><x-tdate :value="$event['occurred_at']" fallback="d M Y, h:i A" /></div>
                                <a href="{{ route($event['route_name'], $event['route_param']) }}" class="btn btn-sm btn-link p-0">Open record</a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
            <div class="mt-3">{{ $events->links() }}</div>
        @else
            <p class="text-muted mb-0">No clinical history found for the selected period.</p>
        @endif
    </div>
</div>
<p class="text-muted small mt-2 mb-0">The timeline shows existing records only. Absence of an event is not a clinical conclusion.</p>
@endsection

@extends('layouts.institute')

@section('title', 'Emergency — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-clipboard2-pulse"></i> Emergency Triage Board</h4>
        <div class="d-flex gap-2">
            @if($user && $user->hasPermission('medical_emergency.create'))
                <a href="{{ route('medical.emergency.create') }}" class="btn btn-danger btn-sm">
                    <i class="bi bi-person-plus"></i> New Walk-in
                </a>
            @endif
            <a href="{{ route('medical.emergency.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-list-ul"></i> All Visits
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-2 mb-3">
        <div class="col-md-2">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold">{{ $todayStats['total'] }}</div>
                    <small class="text-muted">Today</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-primary">{{ $todayStats['active'] }}</div>
                    <small class="text-muted">Active</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-danger">{{ $todayStats['red'] }}</div>
                    <small class="text-muted">Red</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-warning">{{ $todayStats['orange'] }}</div>
                    <small class="text-muted">Orange</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-info">{{ $todayStats['yellow'] }}</div>
                    <small class="text-muted">Yellow</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-success">{{ $todayStats['discharged'] }}</div>
                    <small class="text-muted">Discharged</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Triage Columns -->
    @php
        $triageColors = [
            'red' => 'danger',
            'orange' => 'warning',
            'yellow' => 'info',
            'green' => 'success',
            'white' => 'secondary',
        ];
    @endphp

    <div class="row g-2">
        @foreach($triageColors as $level => $color)
            <div class="col-lg col-md-4 col-6">
                <div class="card border-{{ $color }} shadow-sm">
                    <div class="card-header bg-{{ $color }} text-white py-1">
                        <strong>{{ ucfirst($level) }} ({{ $byTriage[$level]->count() }})</strong>
                    </div>
                    <div class="card-body p-1" style="max-height: 400px; overflow-y: auto;">
                        @forelse($byTriage[$level] as $visit)
                            <div class="card mb-1 border-{{ $color }}">
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <small class="fw-bold">{{ $visit->visit_number }}</small><br>
                                            <small>{{ $visit->patientDisplayName() }}</small>
                                        </div>
                                        <a href="{{ route('medical.emergency.show', $visit) }}" class="btn btn-sm btn-outline-{{ $color }}">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </div>
                                    <small class="text-muted d-block mt-1" style="font-size:11px;">
                                        {{ $visit->chief_complaint ? Str::limit($visit->chief_complaint, 30) : 'No complaint' }}
                                    </small>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-3">
                                <small>No patients</small>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection

@extends('layouts.institute')

@section('title', 'Physiotherapy Dashboard — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-activity"></i> Physiotherapy</h4>
        <div class="d-flex gap-2">
            @if($user && $user->hasPermission('medical_physiotherapy.create'))
                <a href="{{ route('medical.physiotherapy.plans.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> New Plan
                </a>
            @endif
            <a href="{{ route('medical.physiotherapy.plans.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-list-ul"></i> Plans
            </a>
            <a href="{{ route('medical.physiotherapy.sessions.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-list-ul"></i> Sessions
            </a>
            <a href="{{ route('medical.physiotherapy.exercises.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-list-ul"></i> Exercises
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-2 mb-3">
        <div class="col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-primary">{{ $activePlansCount ?? 0 }}</div>
                    <small class="text-muted">Active Plans</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-info">{{ $todaySessions->count() ?? 0 }}</div>
                    <small class="text-muted">Today's Sessions</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-success">{{ $attendedToday ?? 0 }}</div>
                    <small class="text-muted">Sessions Attended Today</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-warning">{{ $averagePainReduction ?? '-' }}</div>
                    <small class="text-muted">Avg Pain Reduction</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-calendar-check"></i> Today's Schedule</div>
                <div class="card-body">
                    @if($todaySessions && $todaySessions->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Therapist</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($todaySessions as $session)
                                        <tr>
                                            <td>
                                                <a href="{{ route('medical.physiotherapy.sessions.show', $session) }}">
                                                    <strong>{{ $session->plan->patient->fullName() ?? '-' }}</strong>
                                                </a>
                                            </td>
                                            <td>{{ $session->therapist->name ?? '-' }}</td>
                                            <td>{{ $session->session_date ? $session->session_date->format('h:i A') : '-' }}</td>
                                            <td>
                                                <span class="badge bg-{{ $session->statusColor() }}">{{ $session->statusLabel() }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">No sessions scheduled for today.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-graph-up"></i> Quick Stats</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>Total Active Plans</span>
                        <strong>{{ $plans->count() ?? 0 }}</strong>
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>Completed Plans</span>
                        <strong class="text-success">{{ $completedPlans ?? 0 }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2">
                        <span>Pending Sessions</span>
                        <strong class="text-warning">{{ $pendingSessions ?? 0 }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-clipboard-check"></i> Active Treatment Plans</div>
                <div class="card-body">
                    @if($plans && $plans->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Plan #</th>
                                        <th>Patient</th>
                                        <th>Therapist</th>
                                        <th>Modality</th>
                                        <th>Progress</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($plans as $plan)
                                        @php
                                            $progress = $plan->sessions_planned > 0 ? round(($plan->sessions_completed / $plan->sessions_planned) * 100) : 0;
                                        @endphp
                                        <tr>
                                            <td><strong>{{ $plan->plan_number }}</strong></td>
                                            <td>{{ $plan->patient->fullName() ?? '-' }}</td>
                                            <td>{{ $plan->therapist->name ?? '-' }}</td>
                                            <td>{{ $plan->modality }}</td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress flex-grow-1" style="height: 8px;">
                                                        <div class="progress-bar bg-success" style="width: {{ $progress }}%"></div>
                                                    </div>
                                                    <small class="text-muted">{{ $plan->sessions_completed }}/{{ $plan->sessions_planned }}</small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $plan->statusColor() }}">{{ $plan->statusLabel() }}</span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="{{ route('medical.physiotherapy.plans.show', $plan) }}" class="btn btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                                    @if($user && $user->hasPermission('medical_physiotherapy.edit'))
                                                        <a href="{{ route('medical.physiotherapy.plans.edit', $plan) }}" class="btn btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">No active treatment plans.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
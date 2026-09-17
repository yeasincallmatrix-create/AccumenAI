@extends('layouts.institute')

@section('title', 'Session ' . $session->session_number . ' — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    @php
        $painDiff = ($session->pain_score_before ?? 0) - ($session->pain_score_after ?? 0);
    @endphp

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0">
            <i class="bi bi-calendar-check"></i> Session {{ $session->session_number }}
            <span class="badge bg-{{ $session->statusColor() }} ms-1">{{ $session->statusLabel() }}</span>
        </h4>
        <div class="d-flex gap-2 flex-wrap">
            @if($session->status === 'scheduled' && $user && $user->hasPermission('medical_physiotherapy.edit'))
                <a href="{{ route('medical.physiotherapy.sessions.edit', $session) }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-pencil"></i> Edit
                </a>
                <form method="POST" action="{{ route('medical.physiotherapy.sessions.attend', $session) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Mark this session as attended?')">
                        <i class="bi bi-check-circle"></i> Mark Attended
                    </button>
                </form>
                <form method="POST" action="{{ route('medical.physiotherapy.sessions.no-show', $session) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Mark this session as no-show?')">
                        <i class="bi bi-x-circle"></i> No-Show
                    </button>
                </form>
            @endif
            <a href="{{ route('medical.physiotherapy.plans.show', $session->plan) }}" class="btn btn-outline-secondary btn-sm">Back to Plan</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <small class="text-muted d-block">Pain Before</small>
                    <div class="fs-3 fw-bold text-danger">{{ $session->pain_score_before ?? '-' }}<small class="fs-6">/10</small></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <small class="text-muted d-block">Pain After</small>
                    <div class="fs-3 fw-bold text-success">{{ $session->pain_score_after ?? '-' }}<small class="fs-6">/10</small></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <small class="text-muted d-block">Pain Change</small>
                    @if($session->pain_score_after !== null)
                        @if($painDiff > 0)
                            <div class="fs-3 fw-bold text-success">-{{ $painDiff }}</div>
                        @elseif($painDiff < 0)
                            <div class="fs-3 fw-bold text-danger">+{{ abs($painDiff) }}</div>
                        @else
                            <div class="fs-3 fw-bold text-muted">0</div>
                        @endif
                    @else
                        <div class="fs-3 fw-bold text-muted">-</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-info-circle"></i> Session Information</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th width="140">Session #</th><td>{{ $session->session_number }}</td></tr>
                        <tr><th>Plan #</th><td><a href="{{ route('medical.physiotherapy.plans.show', $session->plan) }}">{{ $session->plan->plan_number }}</a></td></tr>
                        <tr><th>Patient</th><td>{{ $session->plan->patient->fullName() ?? '-' }}</td></tr>
                        <tr><th>Therapist</th><td>{{ $session->therapist->name ?? '-' }}</td></tr>
                        <tr><th>Date</th><td>{{ $session->session_date?->format('d M Y') ?? '-' }}</td></tr>
                        <tr><th>Duration</th><td>{{ $session->duration_minutes }} minutes</td></tr>
                        <tr><th>Fee</th><td>{{ $session->fee ? number_format($session->fee, 2) : '-' }}</td></tr>
                        <tr><th>Status</th><td><span class="badge bg-{{ $session->statusColor() }}">{{ $session->statusLabel() }}</span></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-journal-text"></i> Clinical Notes</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Assessment Notes</label>
                        <p class="mb-0">{{ $session->assessment_notes ?? 'No assessment notes recorded.' }}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Treatment Given</label>
                        <p class="mb-0">{{ $session->treatment_given ?? 'No treatment notes recorded.' }}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Exercises Done</label>
                        <p class="mb-0">{{ $session->exercises_done ?? 'No exercises recorded.' }}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Progress Notes</label>
                        <p class="mb-0">{{ $session->progress_notes ?? 'No progress notes recorded.' }}</p>
                    </div>
                    <div>
                        <label class="form-label fw-bold">Next Session Focus</label>
                        <p class="mb-0">{{ $session->next_session_focus ?? 'Not specified.' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
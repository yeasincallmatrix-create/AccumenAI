@extends('layouts.institute')

@section('title', 'Plan ' . $plan->plan_number . ' — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    @php
        $progress = $plan->sessions_planned > 0 ? round(($plan->sessions_completed / $plan->sessions_planned) * 100) : 0;
    @endphp

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0">
            <i class="bi bi-clipboard-check"></i> Plan {{ $plan->plan_number }}
            <span class="badge bg-light text-dark ms-2">{{ $plan->modality }}</span>
            <span class="badge bg-{{ $plan->statusColor() }} ms-1">{{ $plan->statusLabel() }}</span>
        </h4>
        <div class="d-flex gap-2 flex-wrap">
            @if(in_array($plan->status, ['active', 'on_hold']) && $user && $user->hasPermission('medical_physiotherapy.edit'))
                <a href="{{ route('medical.physiotherapy.plans.edit', $plan) }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-pencil"></i> Edit
                </a>
            @endif
            @if($plan->status === 'active' && $user && $user->hasPermission('medical_physiotherapy.create'))
                <a href="{{ route('medical.physiotherapy.sessions.create', $plan) }}" class="btn btn-success btn-sm">
                    <i class="bi bi-plus-circle"></i> Add Session
                </a>
                <form method="POST" action="{{ route('medical.physiotherapy.plans.complete', $plan) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-success btn-sm" onclick="return confirm('Mark this plan as completed?')">
                        <i class="bi bi-check-circle"></i> Complete
                    </button>
                </form>
                <form method="POST" action="{{ route('medical.physiotherapy.plans.discontinue', $plan) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Discontinue this plan?')">
                        <i class="bi bi-x-circle"></i> Discontinue
                    </button>
                </form>
            @endif
            <a href="{{ route('medical.physiotherapy.plans.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="flex-grow-1">
                            <strong>Progress: {{ $plan->sessions_completed }}/{{ $plan->sessions_planned }} sessions</strong>
                        </div>
                        <div style="width: 300px;">
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-success" style="width: {{ $progress }}%">{{ $progress }}%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-person"></i> Patient Information</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th width="120">Name</th><td>{{ $plan->patient->fullName() ?? '-' }}</td></tr>
                        <tr><th>Phone</th><td>{{ $plan->patient->phone ?? '-' }}</td></tr>
                        <tr><th>Email</th><td>{{ $plan->patient->email ?? '-' }}</td></tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-person-badge"></i> Treatment Team</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th width="120">Therapist</th><td>{{ $plan->therapist->name ?? '-' }}</td></tr>
                        <tr><th>Referring Dr.</th><td>{{ $plan->referringDoctor->name ?? '-' }}</td></tr>
                        <tr><th>Modality</th><td>{{ $plan->modality }}</td></tr>
                        <tr><th>Frequency</th><td>{{ $plan->frequency }}</td></tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-journal-text"></i> Diagnosis & Goals</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th width="120">Diagnosis</th><td>{{ $plan->diagnosis ?? '-' }}</td></tr>
                        <tr><th>Pain Score</th><td>{{ $plan->pain_score_initial }}/10</td></tr>
                        <tr><th>Start Date</th><td>{{ $plan->start_date?->format('d M Y') ?? '-' }}</td></tr>
                        <tr><th>End Date</th><td>{{ $plan->expected_end_date?->format('d M Y') ?? '-' }}</td></tr>
                        <tr><th>Fee/Session</th><td>{{ $plan->fee_per_session ? number_format($plan->fee_per_session, 2) : '-' }}</td></tr>
                        <tr><th>Total Fee</th><td>{{ $plan->total_fee ? number_format($plan->total_fee, 2) : '-' }}</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if($plan->chief_complaint)
        <div class="row g-3 mt-1">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-chat-left-text"></i> Chief Complaint</div>
                    <div class="card-body">
                        <p class="mb-0">{{ $plan->chief_complaint }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-3 mt-1">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-list-check"></i> Sessions Timeline</div>
                <div class="card-body">
                    @if($plan->sessions && $plan->sessions->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Session #</th>
                                        <th>Date</th>
                                        <th>Therapist</th>
                                        <th>Pain Before</th>
                                        <th>Pain After</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($plan->sessions->sortBy('session_order') as $session)
                                        <tr>
                                            <td>{{ $session->session_order }}</td>
                                            <td><strong>{{ $session->session_number }}</strong></td>
                                            <td>{{ $session->session_date?->format('d M Y') ?? '-' }}</td>
                                            <td>{{ $session->therapist->name ?? '-' }}</td>
                                            <td>{{ $session->pain_score_before ?? '-' }}</td>
                                            <td>
                                                @if($session->pain_score_after !== null)
                                                    @php
                                                        $diff = $session->pain_score_before - $session->pain_score_after;
                                                        $color = $diff > 0 ? 'text-success' : ($diff < 0 ? 'text-danger' : 'text-muted');
                                                    @endphp
                                                    <span class="{{ $color }}">{{ $session->pain_score_after }}</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $session->statusColor() }}">{{ $session->statusLabel() }}</span>
                                            </td>
                                            <td>
                                                <a href="{{ route('medical.physiotherapy.sessions.show', $session) }}" class="btn btn-outline-primary btn-sm" title="View"><i class="bi bi-eye"></i></a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">No sessions recorded yet.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
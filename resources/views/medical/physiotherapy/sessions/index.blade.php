@extends('layouts.institute')

@section('title', 'Physiotherapy Sessions — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-calendar-check"></i> Sessions</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('medical.physiotherapy.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-grid"></i> Dashboard
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        @foreach(\App\Models\Medical\PhysiotherapySession::STATUSES as $key => $label)
                            <option value="{{ $key }}" @selected(($statusFilter ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Therapist</label>
                    <select name="therapist_id" class="form-select">
                        <option value="">All Therapists</option>
                        @foreach($therapists as $therapist)
                            <option value="{{ $therapist->id }}" @selected(($therapistFilter ?? '') == $therapist->id)>{{ $therapist->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="{{ $fromDate ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="{{ $toDate ?? '' }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Filter</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Session #</th>
                            <th>Plan #</th>
                            <th>Patient</th>
                            <th>Therapist</th>
                            <th>Date</th>
                            <th>Pain Before</th>
                            <th>Pain After</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sessions as $session)
                            <tr>
                                <td>
                                    <a href="{{ route('medical.physiotherapy.sessions.show', $session) }}">
                                        <strong>{{ $session->session_number }}</strong>
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route('medical.physiotherapy.plans.show', $session->plan) }}">{{ $session->plan->plan_number }}</a>
                                </td>
                                <td>{{ $session->plan->patient->fullName() ?? '-' }}</td>
                                <td>{{ $session->therapist->name ?? '-' }}</td>
                                <td>{{ $session->session_date?->format('d M Y') ?? '-' }}</td>
                                <td>{{ $session->pain_score_before ?? '-' }}</td>
                                <td>
                                    @if($session->pain_score_after !== null)
                                        {{ $session->pain_score_after }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $session->statusColor() }}">{{ $session->statusLabel() }}</span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('medical.physiotherapy.sessions.show', $session) }}" class="btn btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                        @if($user && $user->hasPermission('medical_physiotherapy.edit') && $session->status === 'scheduled')
                                            <a href="{{ route('medical.physiotherapy.sessions.edit', $session) }}" class="btn btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="bi bi-calendar-check fs-2 d-block mb-2"></i>
                                    No sessions found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $sessions->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
@extends('layouts.institute')

@section('title', 'Dental Dashboard — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-emoji-smile"></i> Dental Dashboard</h4>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 opacity-75">Today's Procedures</h6>
                            <h2 class="mb-0 mt-1">{{ $todayProcedures->count() }}</h2>
                        </div>
                        <i class="bi bi-tools fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 opacity-75">Active Treatment Plans</h6>
                            <h2 class="mb-0 mt-1">{{ $activePlansCount }}</h2>
                        </div>
                        <i class="bi bi-list-check fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 opacity-75">Upcoming Follow-ups (7 days)</h6>
                            <h2 class="mb-0 mt-1">{{ $upcomingFollowUps->count() }}</h2>
                        </div>
                        <i class="bi bi-calendar-check fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Today's Procedures</h6></div>
                <div class="card-body">
                    @forelse($todayProcedures as $proc)
                        <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                            <div>
                                <strong>{{ $proc->procedure_number }}</strong> — {{ $proc->procedure_name }}
                                <br><small class="text-muted">{{ $proc->patient->full_name ?? 'N/A' }} | {{ $proc->dentist->name ?? 'N/A' }}</small>
                            </div>
                            <span class="badge bg-{{ $proc->statusColor() }}">{{ $proc->statusLabel() }}</span>
                        </div>
                    @empty
                        <p class="text-muted text-center mb-0">No procedures today.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Upcoming Follow-ups</h6></div>
                <div class="card-body">
                    @forelse($upcomingFollowUps as $proc)
                        <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                            <div>
                                <strong>{{ $proc->procedure_number }}</strong> — {{ $proc->procedure_name }}
                                <br><small class="text-muted">{{ $proc->patient->full_name ?? 'N/A' }} | Due: {{ $proc->follow_up_date->format('d M Y') }}</small>
                            </div>
                            <a href="{{ route('medical.dental.procedures.show', $proc) }}" class="btn btn-outline-primary btn-sm">View</a>
                        </div>
                    @empty
                        <p class="text-muted text-center mb-0">No upcoming follow-ups.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

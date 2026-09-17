@extends('layouts.institute')

@section('title', 'Physiotherapy Plans — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-clipboard-check"></i> Treatment Plans</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('medical.physiotherapy.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-grid"></i> Dashboard
            </a>
            @if($user && $user->hasPermission('medical_physiotherapy.create'))
                <a href="{{ route('medical.physiotherapy.plans.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> New Plan
                </a>
            @endif
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
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Plan #, patient name" value="{{ $search ?? '' }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        @foreach(\App\Models\Medical\PhysiotherapyPlan::STATUSES as $key => $label)
                            <option value="{{ $key }}" @selected(($status ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
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
                            <th>Plan #</th>
                            <th>Patient</th>
                            <th>Therapist</th>
                            <th>Modality</th>
                            <th>Sessions</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($plans as $plan)
                            @php
                                $progress = $plan->sessions_planned > 0 ? round(($plan->sessions_completed / $plan->sessions_planned) * 100) : 0;
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('medical.physiotherapy.plans.show', $plan) }}">
                                        <strong>{{ $plan->plan_number }}</strong>
                                    </a>
                                </td>
                                <td>{{ $plan->patient->fullName() ?? '-' }}</td>
                                <td>{{ $plan->therapist->name ?? '-' }}</td>
                                <td>{{ $plan->modality }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px; width: 80px;">
                                            <div class="progress-bar bg-success" style="width: {{ $progress }}%"></div>
                                        </div>
                                        <small>{{ $plan->sessions_completed }}/{{ $plan->sessions_planned }}</small>
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
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="bi bi-clipboard-check fs-2 d-block mb-2"></i>
                                    No treatment plans found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $plans->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
@extends('layouts.institute')

@section('title', 'Emergency Visits — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-clipboard2-pulse"></i> Emergency Visits</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('medical.emergency.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-grid"></i> Triage Board
            </a>
            @if($user && $user->hasPermission('medical_emergency.create'))
                <a href="{{ route('medical.emergency.create') }}" class="btn btn-danger btn-sm">
                    <i class="bi bi-person-plus"></i> New Walk-in
                </a>
            @endif
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Visit # or complaint" value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Triage Level</label>
                    <select name="triage_level" class="form-select">
                        <option value="">All Levels</option>
                        @foreach(\App\Models\Medical\EmergencyVisit::TRIAGE_LEVELS as $key => $label)
                            <option value="{{ $key }}" @selected(request('triage_level') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        @foreach(\App\Models\Medical\EmergencyVisit::STATUSES as $key => $label)
                            <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From</label>
                    <x-tdate-input name="from_date" :value="request('from_date')" />
                </div>
                <div class="col-md-2">
                    <label class="form-label">To</label>
                    <x-tdate-input name="to_date" :value="request('to_date')" />
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Filter</button>
                </div>
            </div>
        </div>
    </form>

    <!-- Table -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Visit #</th>
                            <th>Patient</th>
                            <th>Triage</th>
                            <th>Complaint</th>
                            <th>Arrived</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($visits as $visit)
                            <tr>
                                <td><strong>{{ $visit->visit_number }}</strong></td>
                                <td>
                                    {{ $visit->patientDisplayName() }}
                                    @if($visit->patient_age)
                                        <br><small class="text-muted">{{ $visit->patient_age }}y, {{ ucfirst($visit->patient_gender ?? '') }}</small>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $triageColor = match($visit->triage_level) {
                                            'red' => 'danger',
                                            'orange' => 'warning',
                                            'yellow' => 'info',
                                            'green' => 'success',
                                            'white' => 'secondary',
                                            default => 'secondary',
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $triageColor }}">{{ strtoupper($visit->triage_level ?? '?') }}</span>
                                </td>
                                <td>{{ Str::limit($visit->chief_complaint ?? '-', 40) }}</td>
                                <td>
                                    <x-tdate :value="$visit->arrived_at" />
                                    <br><small class="text-muted">{{ $visit->arrived_at ? $visit->arrived_at->diffForHumans() : '' }}</small>
                                </td>
                                <td>{{ $visit->attendingDoctor?->name ?? '-' }}</td>
                                <td>
                                    @php
                                        $statusColor = match($visit->status) {
                                            'waiting' => 'secondary',
                                            'registered' => 'primary',
                                            'triaged' => 'info',
                                            'attended' => 'success',
                                            'discharged' => 'dark',
                                            default => 'secondary',
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $statusColor }}">{{ $visit->statusLabel() }}</span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('medical.emergency.show', $visit) }}" class="btn btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                        @if($visit->isActive() && $user && $user->hasPermission('medical_emergency.edit'))
                                            <a href="{{ route('medical.emergency.triage.form', $visit) }}" class="btn btn-outline-warning" title="Triage"><i class="bi bi-clipboard2-pulse"></i></a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="bi bi-clipboard2-pulse fs-2 d-block mb-2"></i>
                                    No emergency visits found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $visits->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection

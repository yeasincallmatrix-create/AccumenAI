@extends('layouts.institute')

@section('title', 'Emergency Visit ' . $visit->visit_number . ' — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0">
            <i class="bi bi-clipboard2-pulse"></i> {{ $visit->visit_number }}
            @php
                $triageColor = match($visit->triage_level) {
                    'red' => 'danger', 'orange' => 'warning', 'yellow' => 'info',
                    'green' => 'success', 'white' => 'secondary', default => 'secondary',
                };
            @endphp
            <span class="badge bg-{{ $triageColor }} ms-2">{{ strtoupper($visit->triage_level ?? '?') }}</span>
            @php
                $statusColor = match($visit->status) {
                    'waiting' => 'secondary', 'registered' => 'primary', 'triaged' => 'info',
                    'attended' => 'success', 'discharged' => 'dark', default => 'secondary',
                };
            @endphp
            <span class="badge bg-{{ $statusColor }} ms-1">{{ $visit->statusLabel() }}</span>
        </h4>
        <div class="d-flex gap-2">
            @if($visit->isActive() && $user && $user->hasPermission('medical_emergency.triage'))
                <a href="{{ route('medical.emergency.triage.form', $visit) }}" class="btn btn-warning btn-sm">
                    <i class="bi bi-clipboard2-pulse"></i> Triage
                </a>
            @endif
            @if($visit->isActive() && $user && $user->hasPermission('medical_emergency.edit'))
                <a href="{{ route('medical.emergency.edit', $visit) }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-pencil"></i> Edit
                </a>
            @endif
            @if($visit->isActive() && $user && $user->hasPermission('medical_emergency.discharge'))
                <a href="{{ route('medical.emergency.discharge.form', $visit) }}" class="btn btn-success btn-sm">
                    <i class="bi bi-check-circle"></i> Discharge
                </a>
            @endif
            <a href="{{ route('medical.emergency.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
    </div>

    <div class="row g-3">
        <!-- Patient & Arrival -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-person"></i> Patient & Arrival</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th width="140">Patient</th><td>{{ $visit->patientDisplayName() }}</td></tr>
                        <tr><th>Age / Gender</th><td>{{ $visit->patient_age ?? '-' }} / {{ ucfirst($visit->patient_gender ?? '-') }}</td></tr>
                        <tr><th>Phone</th><td>{{ $visit->patient_phone ?? '-' }}</td></tr>
                        <tr><th>Arrival Mode</th><td>{{ \App\Models\Medical\EmergencyVisit::ARRIVAL_MODES[$visit->arrival_mode] ?? '-' }}</td></tr>
                        <tr><th>Arrived</th><td><x-tdate :value="$visit->arrived_at" /> {{ $visit->arrived_at?->format('H:i') }}</td></tr>
                        <tr><th>Triage Level</th><td><span class="badge bg-{{ $triageColor }}">{{ strtoupper($visit->triage_level ?? 'Not Triaged') }}</span></td></tr>
                        <tr><th>Triaged By</th><td>{{ $visit->triagedBy?->name ?? '-' }}</td></tr>
                        <tr><th>Triaged At</th><td>{{ $visit->triaged_at?->format('d M Y H:i') ?? '-' }}</td></tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Clinical -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-journal-medical"></i> Clinical</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th width="140">Chief Complaint</th><td>{{ $visit->chief_complaint ?? '-' }}</td></tr>
                        <tr><th>History</th><td>{{ $visit->history_notes ?? '-' }}</td></tr>
                        <tr><th>Vitals</th>
                            <td>
                                @if($visit->vitals_snapshot)
                                    @foreach($visit->vitals_snapshot as $key => $val)
                                        <span class="badge bg-light text-dark me-1">{{ ucfirst(str_replace('_', ' ', $key)) }}: {{ $val }}</span>
                                    @endforeach
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                        <tr><th>Examination</th><td>{{ $visit->examination_findings ?? '-' }}</td></tr>
                        <tr><th>Provisional Dx</th><td>{{ $visit->provisional_diagnosis ?? '-' }}</td></tr>
                        <tr><th>Treatment Given</th><td>{{ $visit->treatment_given ?? '-' }}</td></tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Disposition -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-arrow-left-right"></i> Disposition</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th width="140">Status</th><td><span class="badge bg-{{ $statusColor }}">{{ $visit->statusLabel() }}</span></td></tr>
                        <tr><th>Disposition</th><td>{{ ucfirst($visit->disposition ?? '-') }}</td></tr>
                        <tr><th>Discharged By</th><td>{{ $visit->dispositionBy?->name ?? '-' }}</td></tr>
                        <tr><th>Discharged At</th><td>{{ $visit->disposition_at?->format('d M Y H:i') ?? '-' }}</td></tr>
                        <tr><th>Notes</th><td>{{ $visit->disposition_notes ?? '-' }}</td></tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Attending & Fees -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-person-badge"></i> Attending & Fees</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th width="140">Doctor</th><td>{{ $visit->attendingDoctor?->name ?? 'Not Assigned' }}</td></tr>
                        <tr><th>Attended At</th><td>{{ $visit->attended_at?->format('d M Y H:i') ?? '-' }}</td></tr>
                        <tr><th>Triage Fee</th><td>৳{{ number_format($visit->triage_fee, 2) }}</td></tr>
                        <tr><th>Total Fee</th><td>৳{{ number_format($visit->total_fee, 2) }}</td></tr>
                    </table>
                </div>
            </div>
        </div>

        @if($visit->admission)
            <div class="col-md-12">
                <div class="alert alert-success">
                    <i class="bi bi-hospital"></i> Admitted to IPD: Admission #{{ $visit->admission->id }}
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

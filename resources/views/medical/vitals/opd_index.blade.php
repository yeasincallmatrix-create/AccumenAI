@extends('layouts.institute')

@section('title', 'OPD Vitals — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">OPD Vitals</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.appointments.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Appointments
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row g-2">
                <div class="col-md-3">
                    <input type="date" name="date" class="form-control" value="{{ request('date') }}"
                           onchange="this.form.submit()" title="Filter by recorded date">
                </div>
                <div class="col-md-3">
                    <select name="patient_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Patients</option>
                        @foreach($patients as $patient)
                            <option value="{{ $patient->id }}" @selected((string) request('patient_id') === (string) $patient->id)>
                                {{ $patient->full_name }} ({{ clinical_no($patient->mr_number) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="doctor_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Doctors</option>
                        @foreach($doctors as $doctor)
                            <option value="{{ $doctor->id }}" @selected((string) request('doctor_id') === (string) $doctor->id)>
                                {{ $doctor->name ?? ('Doctor #'.$doctor->id) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 text-end">
                    <a href="{{ route('medical.vitals.opd') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Recorded</th>
                        <th>Patient</th>
                        <th>Visit</th>
                        <th>Temp</th>
                        <th>BP</th>
                        <th>Pulse / HR</th>
                        <th>SpO2</th>
                        <th>Pain</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vitals as $vital)
                    <tr>
                        <td class="text-nowrap">{{ $vital->recorded_at ? $vital->recorded_at->format('d M Y, h:i A') : '—' }}</td>
                        <td>{{ $vital->patient->full_name ?? $vital->appointment->patient->full_name ?? 'N/A' }}</td>
                        <td>
                            @if($vital->appointment)
                                <a href="{{ route('medical.appointments.show', $vital->appointment) }}">
                                    #{{ $vital->appointment->serial_number }}
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ $vital->temperature !== null ? $vital->temperature.'°C' : '—' }}</td>
                        <td>{{ $vital->blood_pressure ?? '—' }}</td>
                        <td>{{ $vital->pulse ?? '—' }}{{ $vital->heart_rate !== null ? ' / '.$vital->heart_rate : '' }}</td>
                        <td>{{ $vital->spo2 !== null ? $vital->spo2.'%' : '—' }}</td>
                        <td>{{ $vital->pain_score !== null ? $vital->pain_score.'/10' : '—' }}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('medical.vitals.show', $vital) }}" class="btn btn-info" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('medical.vitals.edit', $vital) }}" class="btn btn-warning" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            <i class="bi bi-heart-pulse fs-2 d-block mb-2"></i>
                            No OPD vitals found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $vitals->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection

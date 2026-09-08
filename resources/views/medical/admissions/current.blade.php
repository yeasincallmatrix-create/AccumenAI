@extends('layouts.institute')

@section('title', 'Current IPD — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Current IPD ({{ $admissions->count() }} admitted)</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-primary" href="{{ route('medical.admissions.create') }}">
            <i class="bi bi-plus-lg me-1"></i>New Admission
        </a>
        <a class="btn btn-secondary" href="{{ route('medical.admissions.index') }}">
            <i class="bi bi-list me-1"></i>All Admissions
        </a>
    </div>
</div>

<div class="row mb-3">
    @forelse($summary as $row)
    <div class="col-md-4 mb-3">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title">{{ $row['name'] }} <span class="badge bg-secondary">{{ strtoupper($row['type']) }}</span></h6>
                <p class="mb-1">Occupied: <strong>{{ $row['occupied_beds'] }}</strong> / {{ $row['total_beds'] }}</p>
                <div class="progress" style="height: 16px;">
                    <div class="progress-bar {{ $row['occupancy_rate'] >= 90 ? 'bg-danger' : ($row['occupancy_rate'] >= 70 ? 'bg-warning' : 'bg-success') }}"
                         role="progressbar" style="width: {{ $row['occupancy_rate'] }}%">
                        {{ $row['occupancy_rate'] }}%
                    </div>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12"><p class="text-muted">No active wards.</p></div>
    @endforelse
</div>

<div class="card">
    <div class="card-header"><h6 class="mb-0">Admitted Patients</h6></div>
    <div class="card-body">
        @if($admissions->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Bed</th>
                            <th>Admitted</th>
                            <th>Stay</th>
                            <th>Doctor</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($admissions as $admission)
                        <tr>
                            <td>
                                <a href="{{ route('medical.patients.show', $admission->patient) }}">
                                    {{ $admission->patient->full_name ?? 'N/A' }}
                                </a>
                            </td>
                            <td>
                                @if($admission->bed)
                                    {{ $admission->bed->bed_number }}
                                    <span class="text-muted small">({{ $admission->bed->ward->name ?? '' }})</span>
                                @else
                                    <span class="text-muted">No bed</span>
                                @endif
                            </td>
                            <td><x-tdate :value="$admission->admission_date" fallback="d M Y" /></td>
                            <td>{{ $admission->length_of_stay }} day(s)</td>
                            <td>{{ $admission->admittingDoctor->name ?? 'N/A' }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('medical.admissions.show', $admission) }}" class="btn btn-info" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('medical.vitals.create', ['admission_id' => $admission->id]) }}" class="btn btn-primary" title="Record vitals">
                                        <i class="bi bi-heart-pulse"></i>
                                    </a>
                                    <a href="{{ route('medical.admissions.discharge.form', $admission) }}" class="btn btn-success" title="Discharge">
                                        <i class="bi bi-box-arrow-right"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted mb-0">No active admissions.</p>
        @endif
    </div>
</div>
@endsection

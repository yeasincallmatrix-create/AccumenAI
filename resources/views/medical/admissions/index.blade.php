@extends('layouts.institute')

@section('title', 'Admissions — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Admissions (IPD)</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-success me-1" href="{{ route('medical.admissions.current') }}">
            <i class="bi bi-activity me-1"></i>Current IPD
        </a>
        <a class="btn btn-primary" href="{{ route('medical.admissions.create') }}">
            <i class="bi bi-plus-lg me-1"></i>New Admission
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row g-2">
                <div class="col-md-3">
                    <select name="status" class="form-select" onchange="guardTdateSubmit(this)">
                        <option value="active" @selected(request('status', 'active') === 'active')>Active</option>
                        <option value="discharged" @selected(request('status') === 'discharged')>Discharged</option>
                        <option value="transferred" @selected(request('status') === 'transferred')>Transferred</option>
                        <option value="expired" @selected(request('status') === 'expired')>Expired</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="patient_id" class="form-select" onchange="guardTdateSubmit(this)">
                        <option value="">All Patients</option>
                        @foreach($patients as $patient)
                            <option value="{{ $patient->id }}" @selected((string) request('patient_id') === (string) $patient->id)>
                                {{ $patient->full_name }} ({{ $patient->mr_number }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <x-tdate-input name="from_date" :value="request('from_date')" class="form-control" onchange="guardTdateSubmit(this)" />
                </div>
                <div class="col-md-2">
                    <x-tdate-input name="to_date" :value="request('to_date')" class="form-control" onchange="guardTdateSubmit(this)" />
                </div>
                <div class="col-md-2 text-end">
                    <a href="{{ route('medical.admissions.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Bed</th>
                        <th>Admitted</th>
                        <th>Doctor</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($admissions as $admission)
                    <tr>
                        <td>
                            <a href="{{ route('medical.patients.show', $admission->patient) }}">
                                {{ $admission->patient->full_name ?? 'N/A' }}
                            </a>
                            <span class="text-muted small">({{ $admission->patient->mr_number ?? '' }})</span>
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
                        <td>{{ $admission->admittingDoctor->name ?? 'N/A' }}</td>
                        <td>
                            <span class="badge bg-{{ $admission->status === 'active' ? 'danger' : 'success' }}">
                                {{ ucfirst($admission->status) }}
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('medical.admissions.show', $admission) }}" class="btn btn-info" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('medical.admissions.edit', $admission) }}" class="btn btn-warning" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="bi bi-hospital fs-2 d-block mb-2"></i>
                            No admissions found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $admissions->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection

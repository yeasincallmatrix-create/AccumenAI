@extends('layouts.institute')

@section('title', 'Encounters — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">
            Encounters
            @if(request('patient_id'))
                <small class="text-muted">— patient timeline</small>
            @endif
        </h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-primary" href="{{ route('medical.encounters.create', request()->only('patient_id')) }}">
            <i class="bi bi-plus-lg me-1"></i>New Encounter
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row g-2">
                <div class="col-md-3">
                    <select name="status" class="form-select" onchange="guardTdateSubmit(this)">
                        <option value="">All Statuses</option>
                        @foreach(['open' => 'Open', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <select name="patient_id" class="form-select" onchange="guardTdateSubmit(this)">
                        <option value="">All Patients</option>
                        @foreach($patients as $patient)
                            <option value="{{ $patient->id }}" @selected((string) request('patient_id') === (string) $patient->id)>
                                {{ $patient->full_name }} ({{ $patient->mr_number }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 text-end">
                    <a href="{{ route('medical.encounters.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Patient</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Started</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($encounters as $encounter)
                    <tr>
                        <td><strong>{{ $encounter->encounter_number }}</strong></td>
                        <td>{{ $encounter->patient->full_name ?? '—' }}</td>
                        <td>{{ $encounter->encounter_type }}</td>
                        <td>
                            <span class="badge bg-{{ $encounter->status === 'completed' ? 'success' : ($encounter->status === 'cancelled' ? 'secondary' : 'primary') }}">
                                {{ ucfirst(str_replace('_', ' ', $encounter->status)) }}
                            </span>
                        </td>
                        <td><x-tdate :value="$encounter->started_at" fallback="d M Y, h:i A" /></td>
                        <td class="text-end">
                            <a href="{{ route('medical.encounters.show', $encounter) }}" class="btn btn-sm btn-info" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="bi bi-clipboard2-pulse fs-2 d-block mb-2"></i>
                            No encounters found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $encounters->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection

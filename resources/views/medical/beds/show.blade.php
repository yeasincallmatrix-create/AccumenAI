@extends('layouts.institute')

@section('title', 'Bed Details — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Bed {{ $bed->bed_number }}</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-warning" href="{{ route('medical.beds.edit', $bed) }}">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <a class="btn btn-secondary" href="{{ route('medical.beds.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Bed Info</h6></div>
            <div class="card-body">
                <p><strong>Bed Number:</strong> {{ $bed->bed_number }}</p>
                <p><strong>Ward:</strong>
                    @if($bed->ward)
                        <a href="{{ route('medical.wards.show', $bed->ward) }}">{{ $bed->ward->name }}</a>
                        ({{ strtoupper($bed->ward->type) }})
                    @else
                        N/A
                    @endif
                </p>
                <p class="mb-0"><strong>Status:</strong>
                    <span class="badge bg-{{ $bed->status === 'available' ? 'success' : ($bed->status === 'occupied' ? 'danger' : ($bed->status === 'reserved' ? 'warning' : 'secondary')) }}">
                        {{ ucfirst($bed->status) }}
                    </span>
                </p>
                @if($bed->notes)
                    <hr><p class="mb-0"><strong>Notes:</strong> {{ $bed->notes }}</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Current Occupant</h6></div>
            <div class="card-body">
                @if($activeAdmission && $activeAdmission->patient)
                    <p><strong>Patient:</strong>
                        <a href="{{ route('medical.patients.show', $activeAdmission->patient) }}">
                            {{ $activeAdmission->patient->full_name }}
                        </a>
                        <span class="text-muted">({{ $activeAdmission->patient->mr_number }})</span>
                    </p>
                    <p><strong>Admitted:</strong> <x-tdate :value="$activeAdmission->admission_date" fallback="d M Y" /></p>
                    <p class="mb-0">
                        <a href="{{ route('medical.admissions.show', $activeAdmission) }}" class="btn btn-sm btn-info">
                            <i class="bi bi-eye me-1"></i>View Admission
                        </a>
                    </p>
                @else
                    <p class="text-muted mb-0">No active admission on this bed.</p>
                @endif
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0">Bed Actions</h6></div>
            <div class="card-body d-flex flex-wrap gap-2">
                @if($bed->status === 'occupied')
                    <form action="{{ route('medical.beds.release', $bed) }}" method="POST"
                          onsubmit="return confirm('Release this bed? Any linked active admission will be detached.')">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="bi bi-check-circle me-1"></i>Release Bed
                        </button>
                    </form>
                @endif
                @if($bed->status !== 'occupied')
                    <form action="{{ route('medical.beds.destroy', $bed) }}" method="POST"
                          onsubmit="return confirm('Delete this bed?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="bi bi-trash me-1"></i>Delete Bed
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

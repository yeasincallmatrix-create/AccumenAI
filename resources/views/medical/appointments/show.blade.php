@extends('layouts.institute')

@section('title', 'Appointment Details — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Appointment #{{ $appointment->serial_number }}</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-warning" href="{{ route('medical.appointments.edit', $appointment) }}">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <a class="btn btn-secondary" href="{{ route('medical.appointments.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Appointment Info</h6></div>
            <div class="card-body">
                <p><strong>Patient:</strong>
                    <a href="{{ route('medical.patients.show', $appointment->patient) }}">
                        {{ $appointment->patient->full_name ?? 'N/A' }}
                    </a>
                    @if($appointment->patient)
                        <span class="text-muted">({{ $appointment->patient->mr_number }})</span>
                    @endif
                </p>
                <p><strong>Doctor:</strong> {{ $appointment->doctor->name ?? 'N/A' }}</p>
                <p><strong>Date:</strong> <x-tdate :value="$appointment->appointment_date" fallback="d M Y" /></p>
                <p><strong>Time:</strong> {{ $appointment->appointment_time ? \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') : 'N/A' }}</p>
                <p><strong>Serial:</strong> #{{ $appointment->serial_number }}</p>
                <p class="mb-0"><strong>Status:</strong>
                    <span class="badge bg-{{ $appointment->status === 'completed' ? 'success' : ($appointment->status === 'scheduled' ? 'primary' : ($appointment->status === 'in_progress' ? 'warning' : ($appointment->status === 'cancelled' ? 'danger' : 'secondary'))) }}">
                        {{ ucfirst(str_replace('_', ' ', $appointment->status)) }}
                    </span>
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Clinical Notes</h6></div>
            <div class="card-body">
                <p><strong>Complaints:</strong></p>
                <p class="text-muted">{{ $appointment->complaints ?? '—' }}</p>
                <p><strong>Notes:</strong></p>
                <p class="text-muted mb-0">{{ $appointment->notes ?? '—' }}</p>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0">Status Actions</h6></div>
            <div class="card-body d-flex flex-wrap gap-2">
                @if($appointment->status === 'scheduled')
                    <form action="{{ route('medical.appointments.checkin', $appointment) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-info btn-sm">
                            <i class="bi bi-check-lg me-1"></i>Check In
                        </button>
                    </form>
                @endif
                @if(in_array($appointment->status, ['scheduled', 'checked_in', 'in_progress'], true))
                    <form action="{{ route('medical.appointments.complete', $appointment) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="bi bi-check-all me-1"></i>Complete
                        </button>
                    </form>
                @endif
                @if(!in_array($appointment->status, ['completed', 'cancelled'], true))
                    <form action="{{ route('medical.appointments.destroy', $appointment) }}" method="POST"
                          onsubmit="return confirm('Cancel this appointment?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="bi bi-x-lg me-1"></i>Cancel
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

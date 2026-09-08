@extends('layouts.institute')

@section('title', 'Appointments — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Appointments (OPD)</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-success me-1" href="{{ route('medical.appointments.queue') }}">
            <i class="bi bi-people me-1"></i>Live Queue
        </a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookAppointmentModal">
            <i class="bi bi-plus-lg me-1"></i>Book Appointment
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row g-2">
                <div class="col-md-3">
                    <x-tdate-input name="date" :value="request('date', date('Y-m-d'))" class="form-control" onchange="guardTdateSubmit(this)" />
                </div>
                <div class="col-md-3">
                    <select name="doctor_id" class="form-select" onchange="guardTdateSubmit(this)">
                        <option value="">All Doctors</option>
                        @foreach($doctors as $doctor)
                            <option value="{{ $doctor->id }}" @selected((string) request('doctor_id') === (string) $doctor->id)>
                                {{ $doctor->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select" onchange="guardTdateSubmit(this)">
                        <option value="">All Status</option>
                        @foreach(['scheduled' => 'Scheduled', 'checked_in' => 'Checked In', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'no_show' => 'No Show'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 text-end">
                    <a href="{{ route('medical.appointments.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Serial</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($appointments as $appointment)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $appointment->patient->full_name ?? 'N/A' }}</td>
                        <td>{{ $appointment->doctor->name ?? 'N/A' }}</td>
                        <td><x-tdate :value="$appointment->appointment_date" fallback="d M Y" /></td>
                        <td>{{ $appointment->appointment_time ? \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') : 'N/A' }}</td>
                        <td>#{{ $appointment->serial_number }}</td>
                        <td>
                            <span class="badge bg-{{ $appointment->status === 'completed' ? 'success' : ($appointment->status === 'scheduled' ? 'primary' : ($appointment->status === 'in_progress' ? 'warning' : ($appointment->status === 'cancelled' ? 'danger' : 'secondary'))) }}">
                                {{ ucfirst(str_replace('_', ' ', $appointment->status)) }}
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('medical.appointments.show', $appointment) }}" class="btn btn-info" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('medical.appointments.edit', $appointment) }}" class="btn btn-warning" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-danger" title="Cancel"
                                        onclick="confirmMedicalCancel({{ $appointment->id }})">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
                            No appointments found for this date.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@foreach($appointments as $appointment)
<form id="medical-cancel-form-{{ $appointment->id }}"
      action="{{ route('medical.appointments.destroy', $appointment) }}"
      method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>
@endforeach

@include('medical.patients._quick_create_modal')
@include('medical.appointments._book_modal')
@endsection

@push('scripts')
<script>
function confirmMedicalCancel(id) {
    if (confirm('Are you sure you want to cancel this appointment?')) {
        document.getElementById('medical-cancel-form-' + id).submit();
    }
}
</script>
@endpush

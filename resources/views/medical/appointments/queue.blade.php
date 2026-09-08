@extends('layouts.institute')

@section('title', 'Live Queue — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Live Queue</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.appointments.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
        <button type="button" class="btn btn-primary" onclick="location.reload()">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('medical.appointments.queue') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="queue_doctor">Doctor</label>
                <select id="queue_doctor" name="doctor_id" class="form-select" onchange="guardTdateSubmit(this)">
                    @forelse($doctors as $doctor)
                        <option value="{{ $doctor->id }}" @selected((string) $doctorId === (string) $doctor->id)>
                            {{ $doctor->name }}
                        </option>
                    @empty
                        <option value="">No doctors available</option>
                    @endforelse
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="queue_date">Date</label>
                <x-tdate-input name="date" :value="$date" id="queue_date" class="form-control" onchange="guardTdateSubmit(this)" />
            </div>
            <div class="col-md-5 text-end">
                <span class="badge bg-primary fs-6 me-2">Total: {{ $queueStatus['total'] }}</span>
                <span class="badge bg-warning text-dark fs-6 me-2">Waiting: {{ $queueStatus['waiting'] }}</span>
                <span class="badge bg-info text-dark fs-6 me-2">Checked In: {{ $queueStatus['checked_in'] }}</span>
                <span class="badge bg-success fs-6">In Progress: {{ $queueStatus['in_progress'] }}</span>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h6 class="mb-0">Queue — {{ $queueStatus['estimated_wait_minutes'] ?? 0 }} mins estimated wait</h6>
    </div>
    <div class="card-body">
        @if(!empty($queueStatus['queue']) && count($queueStatus['queue']) > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Serial</th>
                            <th>Patient Name</th>
                            <th>Status</th>
                            <th>Est. Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($queueStatus['queue'] as $item)
                        <tr class="{{ $item['status'] === 'in_progress' ? 'table-success' : '' }}">
                            <td><strong>#{{ $item['serial'] }}</strong></td>
                            <td>{{ $item['patient_name'] }}</td>
                            <td>
                                <span class="badge bg-{{ $item['status'] === 'in_progress' ? 'success' : ($item['status'] === 'checked_in' ? 'info' : 'secondary') }}">
                                    {{ ucfirst(str_replace('_', ' ', $item['status'])) }}
                                </span>
                            </td>
                            <td>{{ $item['estimated_time'] ?? 'N/A' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted text-center py-4 mb-0">No patients in queue.</p>
        @endif
    </div>
</div>
@endsection

@extends('layouts.institute')

@section('title', 'Doctor Profile — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('medical.doctors.index') }}" class="text-decoration-none">Doctors</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $doctor->full_name }}</li>
    </ol>
</nav>

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px;">
                    <i class="bi bi-person-fill text-primary" style="font-size:2.5rem;"></i>
                </div>
                <h5>{{ $doctor->full_name }}</h5>
                <p class="text-muted mb-1">{{ $doctor->specialty_name }}</p>
                <p class="text-muted small">{{ $doctor->department_name }}</p>
                <span class="badge bg-{{ $doctor->is_active ? 'success' : 'danger' }}">{{ $doctor->is_active ? 'Active' : 'Inactive' }}</span>
                <hr>
                <table class="table table-sm text-start">
                    <tr><th>Registration</th><td>{{ $doctor->registration_number }}</td></tr>
                    <tr><th>Qualification</th><td>{{ $doctor->qualification ?? '—' }}</td></tr>
                    <tr><th>Experience</th><td>{{ $doctor->experience_years }} yrs</td></tr>
                    <tr><th>Fee</th><td>৳{{ number_format((float) $doctor->consultation_fee, 2) }}</td></tr>
                    <tr><th>Phone</th><td>{{ $doctor->phone ?? '—' }}</td></tr>
                    <tr><th>Email</th><td>{{ $doctor->email ?? '—' }}</td></tr>
                    <tr><th>Chamber</th><td>{{ $doctor->chamber_address ?? '—' }}</td></tr>
                    <tr><th>Room No.</th><td>{{ $doctor->room_no ?? '—' }}</td></tr>
                </table>
                @if($doctor->bio)
                    <p class="text-start small text-muted">{{ $doctor->bio }}</p>
                @endif
                <div class="d-flex gap-2 justify-content-center mt-3 flex-wrap">
                    <a href="{{ route('medical.doctors.edit', $doctor) }}" class="btn btn-sm btn-warning"><i class="bi bi-pencil me-1"></i>Edit</a>
                    <a href="{{ route('medical.doctors.edit', $doctor) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-calendar-week me-1"></i>Availability</a>
                    <form action="{{ route('medical.doctors.destroy', $doctor) }}" method="POST" onsubmit="return confirm('Remove this doctor?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-danger"><i class="bi bi-trash me-1"></i>Remove</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><strong>Weekly Availability</strong></div>
            <div class="card-body">
                @if($doctor->availabilities->isEmpty())
                    <p class="text-muted mb-0">No availability set for this doctor.</p>
                @else
                    <table class="table table-sm">
                        <thead><tr><th>Day</th><th>Start</th><th>End</th><th>Slot</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach($doctor->availabilities->sortBy(fn($a) => array_search($a->day_of_week, ['saturday','sunday','monday','tuesday','wednesday','thursday','friday'])) as $avail)
                            <tr>
                                <td class="text-capitalize">{{ $avail->day_of_week }}</td>
                                <td>{{ substr((string) $avail->start_time, 0, 5) }}</td>
                                <td>{{ substr((string) $avail->end_time, 0, 5) }}</td>
                                <td>{{ $avail->slot_duration }} min</td>
                                <td><span class="badge bg-{{ $avail->is_available ? 'success' : 'secondary' }}">{{ $avail->is_available ? 'Available' : 'Off' }}</span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

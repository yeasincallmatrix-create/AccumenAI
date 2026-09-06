@extends('layouts.institute')

@section('title', 'Medical Dashboard — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Medical Dashboard</h4>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6 class="card-title">Today's OPD</h6>
                <h2 class="card-text">{{ $todayAppointments ?? 0 }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <h6 class="card-title">Active IPD</h6>
                <h2 class="card-text">{{ $activeAdmissions ?? 0 }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6 class="card-title">Available Beds</h6>
                <h2 class="card-text">{{ $availableBeds ?? 0 }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h6 class="card-title">Today's Revenue</h6>
                <h2 class="card-text">৳{{ $todayRevenue ?? 0 }}</h2>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Recent Appointments</h6></div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    @forelse($recentAppointments ?? [] as $appointment)
                    <li class="border-bottom py-2">
                        <strong>{{ $appointment->patient->full_name ?? 'N/A' }}</strong>
                        <span class="text-muted">with {{ $appointment->doctor->name ?? 'N/A' }}</span>
                        <span class="badge bg-{{ $appointment->status === 'completed' ? 'success' : 'primary' }} float-end">
                            {{ ucfirst(str_replace('_', ' ', $appointment->status)) }}
                        </span>
                    </li>
                    @empty
                    <li class="text-muted">No recent appointments</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Quick Actions</h6></div>
            <div class="card-body">
                <a href="{{ route('medical.patients.create') }}" class="btn btn-primary btn-lg w-100 mb-2">
                    <i class="bi bi-person-plus me-1"></i>Register Patient
                </a>
                <a href="{{ route('medical.appointments.create') }}" class="btn btn-info btn-lg w-100 mb-2">
                    <i class="bi bi-calendar-plus me-1"></i>Book Appointment
                </a>
                <a href="{{ route('medical.appointments.queue') }}" class="btn btn-success btn-lg w-100">
                    <i class="bi bi-people me-1"></i>View Queue
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

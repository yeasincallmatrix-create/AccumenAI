@extends('layouts.institute')

@section('title', 'Driver Details — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">{{ $driver->driver_number }} — {{ $driver->name }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('medical.ambulance.drivers.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
            <a href="{{ route('medical.ambulance.drivers.edit', $driver) }}" class="btn btn-outline-primary btn-sm">Edit</a>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Driver Details</h6>
                    <span class="badge bg-{{ $driver->statusColor() }}">{{ ucfirst(str_replace('_', ' ', $driver->status)) }}</span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Phone / Email</dt><dd class="col-sm-8">{{ $driver->phone }} {{ $driver->email ? '/ ' . $driver->email : '' }}</dd>
                        <dt class="col-sm-4">License</dt><dd class="col-sm-8">{{ $driver->license_number ?? '—' }} ({{ $driver->license_type ?? '—' }}) — exp. {{ $driver->license_expiry?->format('d M Y') ?? '—' }}</dd>
                        <dt class="col-sm-4">Employee Type</dt><dd class="col-sm-8">{{ ucfirst(str_replace('_', ' ', $driver->employee_type)) }}</dd>
                        <dt class="col-sm-4">Joined</dt><dd class="col-sm-8">{{ $driver->joined_date?->format('d M Y') ?? '—' }}</dd>
                        <dt class="col-sm-4">Fitness Expiry</dt><dd class="col-sm-8">{{ $driver->medical_fitness_expiry?->format('d M Y') ?? '—' }}</dd>
                        @if($driver->emergency_contact)<dt class="col-sm-4">Emergency Contact</dt><dd class="col-sm-8">{{ $driver->emergency_contact }}</dd>@endif
                    </dl>
                    @if($driver->isLicenseExpiring())
                        <div class="alert alert-danger mt-2 mb-0">License expiring soon or expired — renew before assigning trips.</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Recent Trips</h6></div>
                <div class="card-body">
                    @forelse($driver->trips as $trip)
                        <div class="border-bottom py-1">
                            <strong>{{ $trip->trip_number }}</strong> — {{ $trip->tripTypeLabel() }}
                            <span class="badge bg-{{ $trip->statusColor() }}">{{ ucfirst($trip->status) }}</span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No trips yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

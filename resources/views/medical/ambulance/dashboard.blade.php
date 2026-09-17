@extends('layouts.institute')

@section('title', 'Ambulance Dashboard — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-truck"></i> Ambulance Fleet</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('medical.ambulance.dispatch-board') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-broadcast"></i> Dispatch Board
            </a>
            @if($user && $user->hasPermission('medical.ambulance.trip.create'))
                <a href="{{ route('medical.ambulance.trips.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> New Trip
                </a>
            @endif
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card shadow-sm border-0 bg-secondary text-white">
                <div class="card-body text-center">
                    <h6 class="mb-0 opacity-75">Fleet</h6>
                    <h2 class="mb-0 mt-1">{{ $fleet['total'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm border-0 bg-success text-white">
                <div class="card-body text-center">
                    <h6 class="mb-0 opacity-75">Available</h6>
                    <h2 class="mb-0 mt-1">{{ $fleet['available'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm border-0 bg-info text-white">
                <div class="card-body text-center">
                    <h6 class="mb-0 opacity-75">Dispatched</h6>
                    <h2 class="mb-0 mt-1">{{ $fleet['dispatched'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm border-0 bg-primary text-white">
                <div class="card-body text-center">
                    <h6 class="mb-0 opacity-75">On Trip</h6>
                    <h2 class="mb-0 mt-1">{{ $fleet['on_trip'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm border-0 bg-warning text-white">
                <div class="card-body text-center">
                    <h6 class="mb-0 opacity-75">Maintenance</h6>
                    <h2 class="mb-0 mt-1">{{ $fleet['maintenance'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-sm border-0 bg-dark text-white">
                <div class="card-body text-center">
                    <h6 class="mb-0 opacity-75">Trips Today</h6>
                    <h2 class="mb-0 mt-1">{{ $todayStats['trips_today'] }}</h2>
                    <small class="opacity-75">{{ $todayStats['completed_today'] }} done · ৳{{ number_format($todayStats['revenue_today'], 0) }}</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Active Trips</h6>
                    <a href="{{ route('medical.ambulance.trips.index') }}" class="btn btn-link btn-sm">All Trips</a>
                </div>
                <div class="card-body">
                    @forelse($activeTrips as $trip)
                        <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                            <div>
                                <strong>{{ $trip->trip_number }}</strong>
                                <span class="badge bg-{{ $trip->priorityColor() }}">{{ ucfirst($trip->priority) }}</span>
                                <span class="badge bg-{{ $trip->statusColor() }}">{{ ucfirst(str_replace('_', ' ', $trip->status)) }}</span>
                                <br><small class="text-muted">
                                    {{ $trip->pickup_location }} → {{ $trip->dropoff_location }} |
                                    {{ $trip->ambulance->vehicle_number ?? 'Unassigned' }} |
                                    {{ $trip->patient->full_name ?? 'No patient' }}
                                </small>
                            </div>
                            <a href="{{ route('medical.ambulance.trips.show', $trip) }}" class="btn btn-outline-primary btn-sm">View</a>
                        </div>
                    @empty
                        <p class="text-muted text-center mb-0">No active trips.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Pending Requests</h6>
                    <a href="{{ route('medical.ambulance.dispatch-board') }}" class="btn btn-link btn-sm">Dispatch</a>
                </div>
                <div class="card-body">
                    @forelse($pendingTrips as $trip)
                        <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                            <div>
                                <strong>{{ $trip->trip_number }}</strong> — {{ $trip->tripTypeLabel() }}
                                <span class="badge bg-{{ $trip->priorityColor() }}">{{ ucfirst($trip->priority) }}</span>
                                <br><small class="text-muted">{{ $trip->pickup_location }} → {{ $trip->dropoff_location }} · {{ $trip->requested_at->diffForHumans() }}</small>
                            </div>
                            <a href="{{ route('medical.ambulance.trips.show', $trip) }}" class="btn btn-outline-primary btn-sm">Dispatch</a>
                        </div>
                    @empty
                        <p class="text-muted text-center mb-0">No pending requests.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

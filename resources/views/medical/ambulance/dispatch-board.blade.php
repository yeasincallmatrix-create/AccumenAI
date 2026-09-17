@extends('layouts.institute')

@section('title', 'Dispatch Board — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0"><i class="bi bi-broadcast"></i> Dispatch Board</h4>
        <a href="{{ route('medical.ambulance.dashboard') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm border-success">
                <div class="card-header bg-success text-white"><h6 class="mb-0">Available Ambulances ({{ $availableAmbulances->count() }})</h6></div>
                <div class="card-body">
                    @forelse($availableAmbulances as $v)
                        <div class="border-bottom py-1"><strong>{{ $v->vehicle_number }}</strong> — {{ $v->typeLabel() }}</div>
                    @empty
                        <p class="text-muted mb-0">None available.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-info">
                <div class="card-header bg-info text-white"><h6 class="mb-0">Available Drivers ({{ $availableDrivers->count() }})</h6></div>
                <div class="card-body">
                    @forelse($availableDrivers as $d)
                        <div class="border-bottom py-1"><strong>{{ $d->name }}</strong> — {{ $d->phone }}</div>
                    @empty
                        <p class="text-muted mb-0">None available.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-warning"><h6 class="mb-0">Pending Requests ({{ $pending->count() }})</h6></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Trip #</th>
                            <th>Type</th>
                            <th>Priority</th>
                            <th>Route</th>
                            <th>Requested</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pending as $trip)
                            <tr>
                                <td><strong>{{ $trip->trip_number }}</strong></td>
                                <td>{{ $trip->tripTypeLabel() }}</td>
                                <td><span class="badge bg-{{ $trip->priorityColor() }}">{{ ucfirst($trip->priority) }}</span></td>
                                <td>{{ $trip->pickup_location }} → {{ $trip->dropoff_location }}</td>
                                <td>{{ $trip->requested_at->diffForHumans() }}</td>
                                <td><a href="{{ route('medical.ambulance.trips.show', $trip) }}" class="btn btn-warning btn-sm">Dispatch</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">No pending requests.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white"><h6 class="mb-0">Active Trips ({{ $active->count() }})</h6></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Trip #</th>
                            <th>Status</th>
                            <th>Vehicle</th>
                            <th>Driver</th>
                            <th>Route</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($active as $trip)
                            <tr>
                                <td><strong>{{ $trip->trip_number }}</strong></td>
                                <td><span class="badge bg-{{ $trip->statusColor() }}">{{ ucfirst(str_replace('_', ' ', $trip->status)) }}</span></td>
                                <td>{{ $trip->ambulance->vehicle_number ?? '—' }}</td>
                                <td>{{ $trip->driver->name ?? '—' }}</td>
                                <td>{{ $trip->pickup_location }} → {{ $trip->dropoff_location }}</td>
                                <td><a href="{{ route('medical.ambulance.trips.show', $trip) }}" class="btn btn-outline-primary btn-sm">Track</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">No active trips.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@extends('layouts.institute')

@section('title', 'Ambulance Trips — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-signpost-2"></i> Ambulance Trips</h4>
        @if($user && $user->hasPermission('medical.ambulance.trip.create'))
            <a href="{{ route('medical.ambulance.trips.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> Request Trip
            </a>
        @endif
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search trip/route..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        @foreach($statuses as $key => $label)
                            <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="trip_type" class="form-select form-select-sm">
                        <option value="">All types</option>
                        @foreach($types as $key => $label)
                            <option value="{{ $key }}" {{ request('trip_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Trip #</th>
                            <th>Type</th>
                            <th>Priority</th>
                            <th>Route</th>
                            <th>Vehicle / Driver</th>
                            <th>Requested</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($trips as $trip)
                            <tr>
                                <td><strong>{{ $trip->trip_number }}</strong></td>
                                <td>{{ $trip->tripTypeLabel() }}</td>
                                <td><span class="badge bg-{{ $trip->priorityColor() }}">{{ ucfirst($trip->priority) }}</span></td>
                                <td>{{ $trip->pickup_location }} → {{ $trip->dropoff_location }}</td>
                                <td>{{ $trip->ambulance->vehicle_number ?? '—' }} / {{ $trip->driver->name ?? '—' }}</td>
                                <td>{{ $trip->requested_at->format('d M H:i') }}</td>
                                <td><span class="badge bg-{{ $trip->statusColor() }}">{{ ucfirst(str_replace('_', ' ', $trip->status)) }}</span></td>
                                <td>
                                    <a href="{{ route('medical.ambulance.trips.show', $trip) }}" class="btn btn-outline-primary btn-sm">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No trips found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $trips->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection

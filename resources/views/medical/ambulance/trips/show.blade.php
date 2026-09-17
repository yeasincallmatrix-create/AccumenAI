@extends('layouts.institute')

@section('title', 'Trip Details — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">{{ $trip->trip_number }} — {{ $trip->tripTypeLabel() }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('medical.ambulance.trips.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
            <a href="{{ route('medical.ambulance.trips.edit', $trip) }}" class="btn btn-outline-primary btn-sm">Edit</a>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Trip Details</h6>
                    <div>
                        <span class="badge bg-{{ $trip->priorityColor() }}">{{ ucfirst($trip->priority) }}</span>
                        <span class="badge bg-{{ $trip->statusColor() }}">{{ ucfirst(str_replace('_', ' ', $trip->status)) }}</span>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Route</dt><dd class="col-sm-8">{{ $trip->pickup_location }} → {{ $trip->dropoff_location }}</dd>
                        <dt class="col-sm-4">Vehicle / Driver</dt><dd class="col-sm-8">{{ $trip->ambulance->vehicle_number ?? 'Unassigned' }} / {{ $trip->driver->name ?? 'Unassigned' }}</dd>
                        <dt class="col-sm-4">Patient</dt><dd class="col-sm-8">{{ $trip->patient->full_name ?? 'Walk-in / none' }}</dd>
                        <dt class="col-sm-4">Requested</dt><dd class="col-sm-8">{{ $trip->requested_at->format('d M Y H:i') }}</dd>
                        <dt class="col-sm-4">Dispatched</dt><dd class="col-sm-8">{{ $trip->dispatched_at?->format('d M Y H:i') ?? '—' }}</dd>
                        <dt class="col-sm-4">At Pickup / Departed</dt><dd class="col-sm-8">{{ $trip->arrived_at_pickup?->format('H:i') ?? '—' }} / {{ $trip->departed_pickup?->format('H:i') ?? '—' }}</dd>
                        <dt class="col-sm-4">Arrived / Completed</dt><dd class="col-sm-8">{{ $trip->arrived_at_dropoff?->format('H:i') ?? '—' }} / {{ $trip->completed_at?->format('d M Y H:i') ?? '—' }}</dd>
                        <dt class="col-sm-4">Distance / Duration</dt><dd class="col-sm-8">{{ $trip->distance_km ?? '—' }} km / {{ $trip->duration_minutes ?? '—' }} min</dd>
                        <dt class="col-sm-4">Fare</dt><dd class="col-sm-8">Base {{ $trip->base_fee }} + Dist {{ $trip->distance_fee }} + Wait {{ $trip->waiting_fee }} = <strong>{{ $trip->total_fee }}</strong> ({{ $trip->payment_status }})</dd>
                        @if($trip->cancellation_reason)<dt class="col-sm-4">Cancelled</dt><dd class="col-sm-8 text-danger">{{ $trip->cancellation_reason }}</dd>@endif
                    </dl>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Trip Timeline</h6></div>
                <div class="card-body">
                    @foreach([['Requested', $trip->requested_at], ['Dispatched', $trip->dispatched_at], ['At Pickup', $trip->arrived_at_pickup], ['Departed Pickup', $trip->departed_pickup], ['Arrived Drop-off', $trip->arrived_at_dropoff], ['Completed', $trip->completed_at]] as [$label, $ts])
                        <div class="d-flex gap-2 py-1 border-bottom">
                            <span class="{{ $ts ? 'text-success' : 'text-muted' }}"><i class="bi {{ $ts ? 'bi-check-circle-fill' : 'bi-circle' }}"></i></span>
                            <strong>{{ $label }}</strong>
                            <span class="ms-auto text-muted small">{{ $ts?->format('d M H:i') ?? '—' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            @if($trip->status === 'requested' && $user && $user->hasPermission('medical.ambulance.trip.dispatch'))
                <div class="card shadow-sm mb-3 border-warning">
                    <div class="card-header bg-warning"><h6 class="mb-0">Dispatch</h6></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('medical.ambulance.trips.dispatch', $trip) }}">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small">Ambulance *</label>
                                <select name="ambulance_id" class="form-select form-select-sm" required>
                                    <option value="">Select...</option>
                                    @foreach($availableAmbulances as $v)
                                        <option value="{{ $v->id }}">{{ $v->vehicle_number }} — {{ $v->typeLabel() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Driver *</label>
                                <select name="driver_id" class="form-select form-select-sm" required>
                                    <option value="">Select...</option>
                                    @foreach($availableDrivers as $d)
                                        <option value="{{ $d->id }}">{{ $d->name }} — {{ $d->phone }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-warning btn-sm w-100">Dispatch Now</button>
                        </form>
                    </div>
                </div>
            @endif

            @if($trip->isActive() && $user && $user->hasPermission('medical.ambulance.trip.dispatch'))
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><h6 class="mb-0">Advance Status</h6></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('medical.ambulance.trips.status', $trip) }}">
                            @csrf
                            <div class="input-group">
                                <select name="status" class="form-select form-select-sm">
                                    @foreach(\App\Models\Medical\AmbulanceTrip::STATUSES as $key => $label)
                                        <option value="{{ $key }}" {{ $trip->status === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm">Update</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            @if(!in_array($trip->status, ['completed', 'cancelled', 'aborted']) && $user && $user->hasPermission('medical.ambulance.trip.complete'))
                <div class="card shadow-sm">
                    <div class="card-header bg-white"><h6 class="mb-0">Cancel Trip</h6></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('medical.ambulance.trips.cancel', $trip) }}">
                            @csrf
                            <input type="text" name="cancellation_reason" class="form-control form-control-sm mb-2" placeholder="Reason *" required>
                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">Cancel Trip</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

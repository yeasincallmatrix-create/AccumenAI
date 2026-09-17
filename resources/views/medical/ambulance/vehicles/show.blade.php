@extends('layouts.institute')

@section('title', 'Ambulance Details — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">{{ $vehicle->vehicle_number }} — {{ $vehicle->typeLabel() }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('medical.ambulance.vehicles.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
            <a href="{{ route('medical.ambulance.vehicles.edit', $vehicle) }}" class="btn btn-outline-primary btn-sm">Edit</a>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Vehicle Details</h6>
                    <span class="badge bg-{{ $vehicle->statusColor() }}">{{ ucfirst(str_replace('_', ' ', $vehicle->status)) }}</span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Registration</dt><dd class="col-sm-8">{{ $vehicle->registration_number ?? '—' }}</dd>
                        <dt class="col-sm-4">Make / Model / Year</dt><dd class="col-sm-8">{{ $vehicle->make ?? '—' }} {{ $vehicle->model ?? '' }} {{ $vehicle->year ?? '' }}</dd>
                        <dt class="col-sm-4">Capacity</dt><dd class="col-sm-8">{{ $vehicle->capacity_patients }} patient(s), {{ $vehicle->capacity_attendants }} attendant(s)</dd>
                        <dt class="col-sm-4">Odometer</dt><dd class="col-sm-8">{{ $vehicle->odometer_km ?? '—' }} km</dd>
                        <dt class="col-sm-4">Last / Next Service</dt><dd class="col-sm-8">{{ $vehicle->last_service_date?->format('d M Y') ?? '—' }} / {{ $vehicle->next_service_date?->format('d M Y') ?? '—' }}</dd>
                        <dt class="col-sm-4">Insurance / Fitness</dt><dd class="col-sm-8">{{ $vehicle->insurance_expiry?->format('d M Y') ?? '—' }} / {{ $vehicle->fitness_expiry?->format('d M Y') ?? '—' }}</dd>
                        @if($vehicle->equipment)
                            <dt class="col-sm-4">Equipment</dt>
                            <dd class="col-sm-8">@foreach($vehicle->equipment as $e)<span class="badge bg-light text-dark border">{{ ucfirst(str_replace('_', ' ', $e)) }}</span> @endforeach</dd>
                        @endif
                        @if($vehicle->notes)<dt class="col-sm-4">Notes</dt><dd class="col-sm-8">{{ $vehicle->notes }}</dd>@endif
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            @if($user && $user->hasPermission('medical.ambulance.fleet.manage'))
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><h6 class="mb-0">Change Status</h6></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('medical.ambulance.vehicles.status', $vehicle) }}">
                            @csrf
                            <div class="input-group">
                                <select name="status" class="form-select form-select-sm">
                                    @foreach(\App\Models\Medical\Ambulance::STATUSES as $key => $label)
                                        <option value="{{ $key }}" {{ $vehicle->status === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm">Update</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Recent Trips</h6></div>
                <div class="card-body">
                    @forelse($vehicle->trips as $trip)
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

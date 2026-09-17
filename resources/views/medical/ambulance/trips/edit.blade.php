@extends('layouts.institute')

@section('title', 'Edit Trip — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Edit {{ $trip->trip_number }}</h4>
        <a href="{{ route('medical.ambulance.trips.show', $trip) }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('medical.ambulance.trips.update', $trip) }}">
                @csrf @method('PUT')
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Trip Type *</label>
                        <select name="trip_type" class="form-select" required>
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}" {{ $trip->trip_type === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            @foreach($priorities as $key => $label)
                                <option value="{{ $key }}" {{ $trip->priority === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Pickup Location *</label>
                        <input type="text" name="pickup_location" class="form-control" value="{{ old('pickup_location', $trip->pickup_location) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Drop-off Location *</label>
                        <input type="text" name="dropoff_location" class="form-control" value="{{ old('dropoff_location', $trip->dropoff_location) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Odometer Start (km)</label>
                        <input type="number" step="0.01" name="odometer_start_km" class="form-control" value="{{ old('odometer_start_km', $trip->odometer_start_km) }}" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Odometer End (km)</label>
                        <input type="number" step="0.01" name="odometer_end_km" class="form-control" value="{{ old('odometer_end_km', $trip->odometer_end_km) }}" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Distance Fee</label>
                        <input type="number" step="0.01" name="distance_fee" class="form-control" value="{{ old('distance_fee', $trip->distance_fee) }}" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Waiting Fee</label>
                        <input type="number" step="0.01" name="waiting_fee" class="form-control" value="{{ old('waiting_fee', $trip->waiting_fee) }}" min="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Driver Notes</label>
                        <textarea name="driver_notes" class="form-control" rows="2">{{ old('driver_notes', $trip->driver_notes) }}</textarea>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

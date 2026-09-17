@extends('layouts.institute')

@section('title', 'Edit Ambulance — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Edit {{ $vehicle->vehicle_number }}</h4>
        <a href="{{ route('medical.ambulance.vehicles.show', $vehicle) }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('medical.ambulance.vehicles.update', $vehicle) }}">
                @csrf @method('PUT')
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Type *</label>
                        <select name="type" class="form-select" required>
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}" {{ $vehicle->type === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Make</label>
                        <input type="text" name="make" class="form-control" value="{{ old('make', $vehicle->make) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Model</label>
                        <input type="text" name="model" class="form-control" value="{{ old('model', $vehicle->model) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Year</label>
                        <input type="number" name="year" class="form-control" value="{{ old('year', $vehicle->year) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Odometer (km)</label>
                        <input type="number" step="0.01" name="odometer_km" class="form-control" value="{{ old('odometer_km', $vehicle->odometer_km) }}" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Next Service</label>
                        <input type="date" name="next_service_date" class="form-control" value="{{ old('next_service_date', $vehicle->next_service_date?->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Insurance Expiry</label>
                        <input type="date" name="insurance_expiry" class="form-control" value="{{ old('insurance_expiry', $vehicle->insurance_expiry?->format('Y-m-d')) }}">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" {{ $vehicle->is_active ? 'checked' : '' }}>
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes', $vehicle->notes) }}</textarea>
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

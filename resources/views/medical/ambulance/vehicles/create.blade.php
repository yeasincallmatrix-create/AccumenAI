@extends('layouts.institute')

@section('title', 'Register Ambulance — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Register Ambulance</h4>
        <a href="{{ route('medical.ambulance.vehicles.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('medical.ambulance.vehicles.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Vehicle Number *</label>
                        <input type="text" name="vehicle_number" class="form-control" value="{{ old('vehicle_number') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Registration Number</label>
                        <input type="text" name="registration_number" class="form-control" value="{{ old('registration_number') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Type *</label>
                        <select name="type" class="form-select" required>
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Make</label>
                        <input type="text" name="make" class="form-control" value="{{ old('make') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Model</label>
                        <input type="text" name="model" class="form-control" value="{{ old('model') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Year</label>
                        <input type="number" name="year" class="form-control" value="{{ old('year') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fuel Type</label>
                        <input type="text" name="fuel_type" class="form-control" value="{{ old('fuel_type') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Odometer (km)</label>
                        <input type="number" step="0.01" name="odometer_km" class="form-control" value="{{ old('odometer_km') }}" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Last Service</label>
                        <input type="date" name="last_service_date" class="form-control" value="{{ old('last_service_date') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Next Service</label>
                        <input type="date" name="next_service_date" class="form-control" value="{{ old('next_service_date') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Insurance Expiry</label>
                        <input type="date" name="insurance_expiry" class="form-control" value="{{ old('insurance_expiry') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Equipment</label>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($equipment as $item)
                                <label class="badge bg-light text-dark border" style="cursor: pointer;">
                                    <input type="checkbox" name="equipment[]" value="{{ $item }}" class="form-check-input me-1">{{ ucfirst(str_replace('_', ' ', $item)) }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Register Vehicle</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

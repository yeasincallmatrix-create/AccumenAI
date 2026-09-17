@extends('layouts.institute')

@section('title', 'Edit Driver — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Edit {{ $driver->driver_number }}</h4>
        <a href="{{ route('medical.ambulance.drivers.show', $driver) }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('medical.ambulance.drivers.update', $driver) }}">
                @csrf @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $driver->name) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Phone *</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone', $driver->phone) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            @foreach($statuses as $key => $label)
                                <option value="{{ $key }}" {{ $driver->status === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">License Number</label>
                        <input type="text" name="license_number" class="form-control" value="{{ old('license_number', $driver->license_number) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">License Type</label>
                        <select name="license_type" class="form-select">
                            <option value="">Select...</option>
                            @foreach($licenseTypes as $key => $label)
                                <option value="{{ $key }}" {{ $driver->license_type === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">License Expiry</label>
                        <input type="date" name="license_expiry" class="form-control" value="{{ old('license_expiry', $driver->license_expiry?->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Employee Type</label>
                        <select name="employee_type" class="form-select">
                            @foreach($employeeTypes as $key => $label)
                                <option value="{{ $key }}" {{ $driver->employee_type === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Medical Fitness Expiry</label>
                        <input type="date" name="medical_fitness_expiry" class="form-control" value="{{ old('medical_fitness_expiry', $driver->medical_fitness_expiry?->format('Y-m-d')) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Emergency Contact</label>
                        <textarea name="emergency_contact" class="form-control" rows="2">{{ old('emergency_contact', $driver->emergency_contact) }}</textarea>
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

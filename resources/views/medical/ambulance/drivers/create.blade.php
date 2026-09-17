@extends('layouts.institute')

@section('title', 'Register Driver — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Register Driver</h4>
        <a href="{{ route('medical.ambulance.drivers.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <p class="text-muted small">Next number: <strong>{{ $driverNumber }}</strong> (assigned on save)</p>
            <form method="POST" action="{{ route('medical.ambulance.drivers.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Phone *</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">License Number</label>
                        <input type="text" name="license_number" class="form-control" value="{{ old('license_number') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">License Type</label>
                        <select name="license_type" class="form-select">
                            <option value="">Select...</option>
                            @foreach($licenseTypes as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">License Expiry</label>
                        <input type="date" name="license_expiry" class="form-control" value="{{ old('license_expiry') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Employee Type</label>
                        <select name="employee_type" class="form-select">
                            @foreach($employeeTypes as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Joined Date</label>
                        <input type="date" name="joined_date" class="form-control" value="{{ old('joined_date') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Medical Fitness Expiry</label>
                        <input type="date" name="medical_fitness_expiry" class="form-control" value="{{ old('medical_fitness_expiry') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Emergency Contact</label>
                        <textarea name="emergency_contact" class="form-control" rows="2">{{ old('emergency_contact') }}</textarea>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Register Driver</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

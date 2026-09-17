@extends('layouts.institute')

@section('title', 'Request Trip — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Request Ambulance Trip</h4>
        <a href="{{ route('medical.ambulance.trips.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <p class="text-muted small">Next number: <strong>{{ $tripNumber }}</strong> (assigned on save)</p>
            <form method="POST" action="{{ route('medical.ambulance.trips.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Trip Type *</label>
                        <select name="trip_type" class="form-select" required>
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            @foreach($priorities as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Patient (optional)</label>
                        <select name="patient_id" class="form-select">
                            <option value="">Walk-in / no patient</option>
                            @foreach($patients as $p)
                                <option value="{{ $p->id }}">{{ $p->full_name ?? ($p->first_name . ' ' . $p->last_name) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Pickup Location *</label>
                        <input type="text" name="pickup_location" class="form-control" value="{{ old('pickup_location') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Drop-off Location *</label>
                        <input type="text" name="dropoff_location" class="form-control" value="{{ old('dropoff_location') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Pickup Address</label>
                        <textarea name="pickup_address" class="form-control" rows="2">{{ old('pickup_address') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Drop-off Address</label>
                        <textarea name="dropoff_address" class="form-control" rows="2">{{ old('dropoff_address') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Patient Condition</label>
                        <textarea name="patient_condition_at_pickup" class="form-control" rows="2">{{ old('patient_condition_at_pickup') }}</textarea>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Base Fee</label>
                        <input type="number" step="0.01" name="base_fee" class="form-control" value="{{ old('base_fee', 0) }}" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Dispatch Notes</label>
                        <input type="text" name="dispatch_notes" class="form-control" value="{{ old('dispatch_notes') }}">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Request Trip</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@extends('layouts.institute')

@section('title', 'Record Vaccination — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-journal-medical"></i> Record Vaccination</h4>
        <a href="{{ route('medical.vaccination.records.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>

    <form method="POST" action="{{ route('medical.vaccination.records.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Record Number</label>
                                <input type="text" class="form-control" value="{{ $recordNumber }}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Patient *</label>
                                <input type="hidden" name="patient_id" class="form-control" required>
                                <input type="text" class="form-control" placeholder="Search patient..." required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Vaccine *</label>
                                <select name="vaccine_master_id" class="form-select" required>
                                    <option value="">Select Vaccine</option>
                                    @foreach($vaccines as $v)
                                        <option value="{{ $v->id }}" {{ old('vaccine_master_id') == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Dose # *</label>
                                <input type="number" name="dose_number" class="form-control" value="{{ old('dose_number', 1) }}" min="1" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Administered Date *</label>
                                <input type="date" name="administered_date" class="form-control" value="{{ old('administered_date', now()->format('Y-m-d')) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Site</label>
                                <select name="site" class="form-select">
                                    <option value="">Select Site</option>
                                    @foreach($sites as $k => $v)
                                        <option value="{{ $k }}" {{ old('site') === $k ? 'selected' : '' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Route</label>
                                <select name="route" class="form-select">
                                    <option value="">Select Route</option>
                                    @foreach($routes as $k => $v)
                                        <option value="{{ $k }}" {{ old('route') === $k ? 'selected' : '' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Dose Volume</label>
                                <input type="text" name="dose_volume" class="form-control" value="{{ old('dose_volume') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Batch Number</label>
                                <input type="text" name="batch_number" class="form-control" value="{{ old('batch_number') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Batch Expiry</label>
                                <input type="date" name="batch_expiry" class="form-control" value="{{ old('batch_expiry') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Manufacturer</label>
                                <input type="text" name="manufacturer" class="form-control" value="{{ old('manufacturer') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Pre-Vaccination Notes</label>
                                <textarea name="pre_vaccination_notes" class="form-control" rows="2">{{ old('pre_vaccination_notes') }}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Post-Vaccination Notes</label>
                                <textarea name="post_vaccination_notes" class="form-control" rows="2">{{ old('post_vaccination_notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><h6 class="mb-0">Adverse Event</h6></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Adverse Event</label>
                            <select name="adverse_event" class="form-select">
                                @foreach($adverseEvents as $k => $v)
                                    <option value="{{ $k }}" {{ old('adverse_event', 'none') === $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Details</label>
                            <textarea name="adverse_event_details" class="form-control" rows="2">{{ old('adverse_event_details') }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><h6 class="mb-0">Fee</h6></div>
                    <div class="card-body">
                        <input type="number" name="fee" class="form-control" value="{{ old('fee', 0) }}" step="0.01" min="0">
                    </div>
                </div>
                <button type="submit" class="btn btn-success w-100"><i class="bi bi-check-circle"></i> Record Vaccination</button>
            </div>
        </div>
    </form>
</div>
@endsection

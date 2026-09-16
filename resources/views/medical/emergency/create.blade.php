@extends('layouts.institute')

@section('title', 'New Emergency Walk-in — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-person-plus"></i> New Walk-in Registration</h4>
        <a href="{{ route('medical.emergency.index') }}" class="btn btn-outline-secondary btn-sm">Back to List</a>
    </div>

    <form method="POST" action="{{ route('medical.emergency.store') }}">
        @csrf

        <div class="row g-3">
            <!-- Visit Number -->
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-danger text-white">
                        <i class="bi bi-clipboard2-pulse"></i> Visit # {{ $visitNumber }}
                    </div>
                </div>
            </div>

            <!-- Patient Info -->
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-person"></i> Patient Information</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-12">
                                <label class="form-label">Patient Name (if unknown, leave blank)</label>
                                <input type="text" name="patient_name_temp" class="form-control" value="{{ old('patient_name_temp') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Age</label>
                                <input type="number" name="patient_age" class="form-control" value="{{ old('patient_age') }}" min="0" max="150">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Gender</label>
                                <select name="patient_gender" class="form-select">
                                    <option value="">Select</option>
                                    <option value="male" @selected(old('patient_gender') === 'male')>Male</option>
                                    <option value="female" @selected(old('patient_gender') === 'female')>Female</option>
                                    <option value="other" @selected(old('patient_gender') === 'other')>Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Phone</label>
                                <input type="text" name="patient_phone" class="form-control" value="{{ old('patient_phone') }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Arrival & Triage -->
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-arrow-right-circle"></i> Arrival & Triage</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Arrival Mode</label>
                                <select name="arrival_mode" class="form-select">
                                    <option value="">Select</option>
                                    @foreach($arrivalModes as $key => $label)
                                        <option value="{{ $key }}" @selected(old('arrival_mode') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Arrived At</label>
                                <input type="datetime-local" name="arrived_at" class="form-control" value="{{ old('arrived_at', now()->format('Y-m-d\TH:i')) }}">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Triage Level</label>
                                <select name="triage_level" class="form-select">
                                    <option value="">Select Triage</option>
                                    @foreach($triageLevels as $key => $label)
                                        <option value="{{ $key }}" @selected(old('triage_level') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Chief Complaint</label>
                                <textarea name="chief_complaint" class="form-control" rows="3" placeholder="Primary reason for visit...">{{ old('chief_complaint') }}</textarea>
                                @error('chief_complaint')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Info -->
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-journal-text"></i> Additional Details</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label">Attending Doctor</label>
                                <select name="attending_doctor_id" class="form-select">
                                    <option value="">Assign Later</option>
                                    @foreach($doctors as $doctor)
                                        <option value="{{ $doctor->id }}" @selected(old('attending_doctor_id') == $doctor->id)>{{ $doctor->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Triage Fee</label>
                                <input type="number" name="triage_fee" class="form-control" value="{{ old('triage_fee', '0') }}" min="0" step="0.01">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Total Fee</label>
                                <input type="number" name="total_fee" class="form-control" value="{{ old('total_fee', '0') }}" min="0" step="0.01">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-danger btn-lg">
                    <i class="bi bi-person-plus"></i> Register Walk-in
                </button>
                <a href="{{ route('medical.emergency.index') }}" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
            </div>
        </div>
    </form>
</div>
@endsection

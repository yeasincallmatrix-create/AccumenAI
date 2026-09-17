@extends('layouts.institute')

@section('title', 'New Blood Request — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-plus-circle"></i> New Blood Request</h4>
        <a href="{{ route('medical.blood-bank.requests.index') }}" class="btn btn-outline-secondary btn-sm">Back to List</a>
    </div>

    <form method="POST" action="{{ route('medical.blood-bank.requests.store') }}">
        @csrf

        <div class="row g-3">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-clipboard2-pulse"></i> Request # {{ $requestNumber }}
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-person"></i> Patient & Doctor</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-12">
                                <label class="form-label">Patient *</label>
                                <select name="patient_id" class="form-select @error('patient_id') is-invalid @enderror" required>
                                    <option value="">Select Patient</option>
                                    @foreach($patients as $patient)
                                        <option value="{{ $patient->id }}" @selected(old('patient_id') == $patient->id)>{{ $patient->full_name }}</option>
                                    @endforeach
                                </select>
                                @error('patient_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Doctor</label>
                                <select name="doctor_id" class="form-select @error('doctor_id') is-invalid @enderror">
                                    <option value="">Select Doctor</option>
                                    @foreach($doctors as $doctor)
                                        <option value="{{ $doctor->id }}" @selected(old('doctor_id') == $doctor->id)>{{ $doctor->name }}</option>
                                    @endforeach
                                </select>
                                @error('doctor_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-droplet"></i> Blood Requirements</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Blood Group *</label>
                                <select name="blood_group" class="form-select @error('blood_group') is-invalid @enderror" required>
                                    <option value="">Select Blood Group</option>
                                    @foreach($bloodGroups as $key => $label)
                                        <option value="{{ $key }}" @selected(old('blood_group') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('blood_group')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Component</label>
                                <select name="component" class="form-select @error('component') is-invalid @enderror">
                                    <option value="">Any Component</option>
                                    @foreach(\App\Models\Medical\BloodUnit::COMPONENTS as $key => $label)
                                        <option value="{{ $key }}" @selected(old('component') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('component')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Units Requested *</label>
                                <input type="number" name="units_requested" class="form-control @error('units_requested') is-invalid @enderror" value="{{ old('units_requested', 1) }}" min="1" required>
                                @error('units_requested')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Urgency *</label>
                                <select name="urgency" class="form-select @error('urgency') is-invalid @enderror" required>
                                    @foreach($urgencyLevels as $key => $label)
                                        <option value="{{ $key }}" @selected(old('urgency', 'routine') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('urgency')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-journal-text"></i> Clinical Information</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Clinical Indication</label>
                                <textarea name="clinical_indication" class="form-control @error('clinical_indication') is-invalid @enderror" rows="3" placeholder="Reason for transfusion, symptoms...">{{ old('clinical_indication') }}</textarea>
                                @error('clinical_indication')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Diagnosis</label>
                                <textarea name="diagnosis" class="form-control @error('diagnosis') is-invalid @enderror" rows="3" placeholder="Patient diagnosis...">{{ old('diagnosis') }}</textarea>
                                @error('diagnosis')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="2">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-lg"></i> Create Request
                </button>
                <a href="{{ route('medical.blood-bank.requests.index') }}" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
            </div>
        </div>
    </form>
</div>
@endsection

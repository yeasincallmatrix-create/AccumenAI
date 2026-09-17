@extends('layouts.institute')

@section('title', 'Edit Blood Request — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-pencil"></i> Edit {{ $bloodRequest->request_number }}</h4>
        <a href="{{ route('medical.blood-bank.requests.show', $bloodRequest) }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <form method="POST" action="{{ route('medical.blood-bank.requests.update', $bloodRequest) }}">
        @csrf
        @method('PUT')

        <div class="row g-3">
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
                                        <option value="{{ $patient->id }}" @selected((string) old('patient_id', $bloodRequest->patient_id) === (string) $patient->id)>{{ $patient->full_name }}</option>
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
                                        <option value="{{ $doctor->id }}" @selected((string) old('doctor_id', $bloodRequest->doctor_id) === (string) $doctor->id)>{{ $doctor->name }}</option>
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
                                    @foreach($bloodGroups as $key => $label)
                                        <option value="{{ $key }}" @selected(old('blood_group', $bloodRequest->blood_group) === $key)>{{ $label }}</option>
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
                                    @foreach($components as $key => $label)
                                        <option value="{{ $key }}" @selected(old('component', $bloodRequest->component) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('component')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Units Requested *</label>
                                <input type="number" name="units_requested" class="form-control @error('units_requested') is-invalid @enderror" value="{{ old('units_requested', $bloodRequest->units_requested) }}" min="1" required>
                                @error('units_requested')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Urgency *</label>
                                <select name="urgency" class="form-select @error('urgency') is-invalid @enderror" required>
                                    @foreach($urgencyLevels as $key => $label)
                                        <option value="{{ $key }}" @selected(old('urgency', $bloodRequest->urgency) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('urgency')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select @error('status') is-invalid @enderror">
                                    @foreach($statuses as $key => $label)
                                        <option value="{{ $key }}" @selected(old('status', $bloodRequest->status) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('status')
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
                                <textarea name="clinical_indication" class="form-control @error('clinical_indication') is-invalid @enderror" rows="3">{{ old('clinical_indication', $bloodRequest->clinical_indication) }}</textarea>
                                @error('clinical_indication')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Diagnosis</label>
                                <textarea name="diagnosis" class="form-control @error('diagnosis') is-invalid @enderror" rows="3">{{ old('diagnosis', $bloodRequest->diagnosis) }}</textarea>
                                @error('diagnosis')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="2">{{ old('notes', $bloodRequest->notes) }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg"></i> Save Changes</button>
                <a href="{{ route('medical.blood-bank.requests.show', $bloodRequest) }}" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
            </div>
        </div>
    </form>
</div>
@endsection

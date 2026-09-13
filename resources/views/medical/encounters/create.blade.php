@extends('layouts.institute')

@section('title', 'New Encounter — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">New Encounter</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.encounters.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('medical.encounters.store') }}" method="POST">
            @csrf
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="patient_id">Patient <span class="text-danger">*</span></label>
                        <select id="patient_id" name="patient_id" class="form-select @error('patient_id') is-invalid @enderror" required>
                            <option value="">Select Patient</option>
                            @foreach($patients as $patient)
                                <option value="{{ $patient->id }}" @selected((string) old('patient_id', request('patient_id')) === (string) $patient->id)>
                                    {{ $patient->full_name }} ({{ clinical_no($patient->mr_number) }})
                                </option>
                            @endforeach
                        </select>
                        @error('patient_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="doctor_id">Clinician <span class="text-danger">*</span></label>
                        <select id="doctor_id" name="doctor_id" class="form-select @error('doctor_id') is-invalid @enderror" required>
                            <option value="">Select Doctor</option>
                            @foreach($doctors as $doctor)
                                <option value="{{ $doctor->id }}" @selected((string) old('doctor_id') === (string) $doctor->id)>
                                    {{ $doctor->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('doctor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="encounter_type">Type <span class="text-danger">*</span></label>
                        <select id="encounter_type" name="encounter_type" class="form-select @error('encounter_type') is-invalid @enderror" required>
                            @foreach(['OPD' => 'OPD', 'EMERGENCY' => 'Emergency', 'IPD' => 'IPD', 'FOLLOW_UP' => 'Follow-up', 'WALK_IN' => 'Walk-in'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('encounter_type', 'OPD') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('encounter_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                @if($selectedAppointment)
                    <input type="hidden" name="appointment_id" value="{{ $selectedAppointment->id }}">
                    <div class="col-md-12">
                        <div class="alert alert-info">
                            Linked appointment: <strong>#{{ $selectedAppointment->id }}</strong>
                            ({{ $selectedAppointment->appointment_date?->format('d M Y') }}).
                        </div>
                    </div>
                @endif
                <div class="col-md-12">
                    <div class="mb-3">
                        <label class="form-label" for="chief_complaint">Chief Complaint</label>
                        <textarea id="chief_complaint" name="chief_complaint" rows="2"
                                  class="form-control @error('chief_complaint') is-invalid @enderror">{{ old('chief_complaint') }}</textarea>
                        @error('chief_complaint')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="diagnosis_text">Diagnosis (text)</label>
                        <input type="text" id="diagnosis_text" name="diagnosis_text" maxlength="1000"
                               class="form-control @error('diagnosis_text') is-invalid @enderror"
                               value="{{ old('diagnosis_text') }}">
                        @error('diagnosis_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="diagnosis_code">Diagnosis Code (only if authoritative)</label>
                        <input type="text" id="diagnosis_code" name="diagnosis_code" maxlength="60"
                               class="form-control @error('diagnosis_code') is-invalid @enderror"
                               value="{{ old('diagnosis_code') }}" placeholder="e.g. ICD-10 code, if verified">
                        @error('diagnosis_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Open Encounter
                </button>
                <a href="{{ route('medical.encounters.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

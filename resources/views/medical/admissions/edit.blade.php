@extends('layouts.institute')

@section('title', 'Edit Admission — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Edit Admission — {{ $admission->patient->full_name ?? 'N/A' }}</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.admissions.show', $admission) }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('medical.admissions.update', $admission) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="patient_id">Patient <span class="text-danger">*</span></label>
                        <select id="patient_id" name="patient_id" class="form-select @error('patient_id') is-invalid @enderror" required>
                            <option value="">Select Patient</option>
                            @foreach($patients as $patient)
                                <option value="{{ $patient->id }}"
                                    @selected((string) old('patient_id', $admission->patient_id) === (string) $patient->id)>
                                    {{ $patient->full_name }} ({{ clinical_no($patient->mr_number) }})
                                </option>
                            @endforeach
                        </select>
                        @error('patient_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="admitting_doctor_id">Admitting Doctor <span class="text-danger">*</span></label>
                        <select id="admitting_doctor_id" name="admitting_doctor_id" class="form-select @error('admitting_doctor_id') is-invalid @enderror" required>
                            <option value="">Select Doctor</option>
                            @foreach($doctors as $doctor)
                                <option value="{{ $doctor->id }}"
                                    @selected((string) old('admitting_doctor_id', $admission->admitting_doctor_id) === (string) $doctor->id)>
                                    {{ $doctor->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('admitting_doctor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="bed_id">Bed</label>
                        <select id="bed_id" name="bed_id" class="form-select @error('bed_id') is-invalid @enderror">
                            <option value="">No bed</option>
                            @if($admission->bed)
                                <option value="{{ $admission->bed->id }}" @selected(old('bed_id', $admission->bed_id) == $admission->bed->id)>
                                    {{ $admission->bed->bed_number }} — {{ $admission->bed->ward->name ?? '' }} (current)
                                </option>
                            @endif
                            @foreach($beds as $bed)
                                <option value="{{ $bed->id }}" @selected((string) old('bed_id', $admission->bed_id) === (string) $bed->id)>
                                    {{ $bed->bed_number }} — {{ $bed->ward->name ?? '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('bed_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="admission_date">Admission Date <span class="text-danger">*</span></label>
                        <x-tdate-input name="admission_date" :value="old('admission_date', $admission->admission_date?->format('Y-m-d'))" id="admission_date" :class="'form-control'.($errors->has('admission_date') ? ' is-invalid' : '')" required />
                        @error('admission_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="admission_time">Admission Time <span class="text-danger">*</span></label>
                        <input type="time" id="admission_time" name="admission_time"
                               class="form-control @error('admission_time') is-invalid @enderror"
                               value="{{ old('admission_time', $admission->admission_time ? \Carbon\Carbon::parse($admission->admission_time)->format('H:i') : '') }}" required>
                        @error('admission_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="primary_diagnosis">Primary Diagnosis</label>
                        <textarea id="primary_diagnosis" name="primary_diagnosis" rows="2"
                                  class="form-control @error('primary_diagnosis') is-invalid @enderror">{{ old('primary_diagnosis', $admission->primary_diagnosis) }}</textarea>
                        @error('primary_diagnosis')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="secondary_diagnosis">Secondary Diagnosis</label>
                        <textarea id="secondary_diagnosis" name="secondary_diagnosis" rows="2"
                                  class="form-control @error('secondary_diagnosis') is-invalid @enderror">{{ old('secondary_diagnosis', $admission->secondary_diagnosis) }}</textarea>
                        @error('secondary_diagnosis')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="mb-3">
                        <label class="form-label" for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="2"
                                  class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $admission->notes) }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Update Admission
                </button>
                <a href="{{ route('medical.admissions.show', $admission) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

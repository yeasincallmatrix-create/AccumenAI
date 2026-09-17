@extends('layouts.institute')

@section('title', 'Create Treatment Plan — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-plus-circle"></i> New Treatment Plan</h4>
        <a href="{{ route('medical.physiotherapy.plans.index') }}" class="btn btn-outline-secondary btn-sm">Back to List</a>
    </div>

    <form method="POST" action="{{ route('medical.physiotherapy.plans.store') }}">
        @csrf

        <div class="row g-3">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-clipboard-check"></i> Plan # {{ $planNumber }}
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-person"></i> Patient & Therapist</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-12">
                                <label class="form-label">Patient *</label>
                                <select name="patient_id" class="form-select @error('patient_id') is-invalid @enderror" required>
                                    <option value="">Select Patient</option>
                                    @foreach($patients as $patient)
                                        <option value="{{ $patient->id }}" @selected(old('patient_id') == $patient->id)>{{ $patient->fullName() }}</option>
                                    @endforeach
                                </select>
                                @error('patient_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Therapist *</label>
                                <select name="therapist_id" class="form-select @error('therapist_id') is-invalid @enderror" required>
                                    <option value="">Select Therapist</option>
                                    @foreach($therapists as $therapist)
                                        <option value="{{ $therapist->id }}" @selected(old('therapist_id') == $therapist->id)>{{ $therapist->name }}</option>
                                    @endforeach
                                </select>
                                @error('therapist_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Referring Doctor</label>
                                <select name="referring_doctor_id" class="form-select @error('referring_doctor_id') is-invalid @enderror">
                                    <option value="">Select Doctor</option>
                                    @foreach($doctors as $doctor)
                                        <option value="{{ $doctor->id }}" @selected(old('referring_doctor_id') == $doctor->id)>{{ $doctor->name }}</option>
                                    @endforeach
                                </select>
                                @error('referring_doctor_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Modality *</label>
                                <select name="modality" class="form-select @error('modality') is-invalid @enderror" required>
                                    <option value="">Select Modality</option>
                                    @foreach($modalities as $key => $label)
                                        <option value="{{ $key }}" @selected(old('modality') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('modality')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-journal-text"></i> Clinical Details</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-12">
                                <label class="form-label">Chief Complaint *</label>
                                <textarea name="chief_complaint" class="form-control @error('chief_complaint') is-invalid @enderror" rows="3" required>{{ old('chief_complaint') }}</textarea>
                                @error('chief_complaint')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Diagnosis</label>
                                <input type="text" name="diagnosis" class="form-control @error('diagnosis') is-invalid @enderror" value="{{ old('diagnosis') }}">
                                @error('diagnosis')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Initial Pain Score (0-10) *</label>
                                <input type="number" name="pain_score_initial" class="form-control @error('pain_score_initial') is-invalid @enderror" value="{{ old('pain_score_initial') }}" min="0" max="10" required>
                                @error('pain_score_initial')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Frequency *</label>
                                <select name="frequency" class="form-select @error('frequency') is-invalid @enderror" required>
                                    <option value="">Select Frequency</option>
                                    @foreach($frequencies as $key => $label)
                                        <option value="{{ $key }}" @selected(old('frequency') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('frequency')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-calendar"></i> Schedule & Sessions</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Sessions Planned *</label>
                                <input type="number" name="sessions_planned" class="form-control @error('sessions_planned') is-invalid @enderror" value="{{ old('sessions_planned') }}" min="1" required>
                                @error('sessions_planned')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Start Date *</label>
                                <input type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date') }}" required>
                                @error('start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Expected End Date</label>
                                <input type="date" name="expected_end_date" class="form-control @error('expected_end_date') is-invalid @enderror" value="{{ old('expected_end_date') }}">
                                @error('expected_end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-currency-dollar"></i> Fee Details</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Fee Per Session</label>
                                <input type="number" name="fee_per_session" class="form-control @error('fee_per_session') is-invalid @enderror" value="{{ old('fee_per_session') }}" min="0" step="0.01">
                                @error('fee_per_session')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Total Fee</label>
                                <input type="number" name="total_fee" class="form-control @error('total_fee') is-invalid @enderror" value="{{ old('total_fee') }}" min="0" step="0.01">
                                @error('total_fee')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-lg"></i> Create Plan
                </button>
                <a href="{{ route('medical.physiotherapy.plans.index') }}" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
            </div>
        </div>
    </form>
</div>
@endsection
@extends('layouts.institute')

@section('title', 'Edit Emergency Visit — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-pencil"></i> Edit {{ $visit->visit_number }}</h4>
        <a href="{{ route('medical.emergency.show', $visit) }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <form method="POST" action="{{ route('medical.emergency.update', $visit) }}">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header">Patient</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-12">
                                <label class="form-label">Patient Name</label>
                                <input type="text" name="patient_name_temp" class="form-control" value="{{ old('patient_name_temp', $visit->patient_name_temp) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Age</label>
                                <input type="number" name="patient_age" class="form-control" value="{{ old('patient_age', $visit->patient_age) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Gender</label>
                                <select name="patient_gender" class="form-select">
                                    <option value="">Select</option>
                                    <option value="male" @selected(old('patient_gender', $visit->patient_gender) === 'male')>Male</option>
                                    <option value="female" @selected(old('patient_gender', $visit->patient_gender) === 'female')>Female</option>
                                    <option value="other" @selected(old('patient_gender', $visit->patient_gender) === 'other')>Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Phone</label>
                                <input type="text" name="patient_phone" class="form-control" value="{{ old('patient_phone', $visit->patient_phone) }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header">Clinical</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-12">
                                <label class="form-label">Chief Complaint</label>
                                <textarea name="chief_complaint" class="form-control" rows="2">{{ old('chief_complaint', $visit->chief_complaint) }}</textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">History</label>
                                <textarea name="history_notes" class="form-control" rows="2">{{ old('history_notes', $visit->history_notes) }}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Examination</label>
                                <textarea name="examination_findings" class="form-control" rows="2">{{ old('examination_findings', $visit->examination_findings) }}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Provisional Dx</label>
                                <textarea name="provisional_diagnosis" class="form-control" rows="2">{{ old('provisional_diagnosis', $visit->provisional_diagnosis) }}</textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Treatment</label>
                                <textarea name="treatment_given" class="form-control" rows="2">{{ old('treatment_given', $visit->treatment_given) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header">Doctor & Status</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-12">
                                <label class="form-label">Attending Doctor</label>
                                <select name="attending_doctor_id" class="form-select">
                                    <option value="">Not Assigned</option>
                                    @foreach($doctors as $doctor)
                                        <option value="{{ $doctor->id }}" @selected(old('attending_doctor_id', $visit->attending_doctor_id) == $doctor->id)>{{ $doctor->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    @foreach($statuses as $key => $label)
                                        <option value="{{ $key }}" @selected(old('status', $visit->status) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Disposition</label>
                                <select name="disposition" class="form-select">
                                    <option value="">Select</option>
                                    @foreach($dispositions as $key => $label)
                                        <option value="{{ $key }}" @selected(old('disposition', $visit->disposition) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Discharge Notes</label>
                                <textarea name="disposition_notes" class="form-control" rows="2">{{ old('disposition_notes', $visit->disposition_notes) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header">Fees</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Triage Fee</label>
                                <input type="number" name="triage_fee" class="form-control" value="{{ old('triage_fee', $visit->triage_fee) }}" min="0" step="0.01">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Total Fee</label>
                                <input type="number" name="total_fee" class="form-control" value="{{ old('total_fee', $visit->total_fee) }}" min="0" step="0.01">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg"></i> Save Changes</button>
                <a href="{{ route('medical.emergency.show', $visit) }}" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
            </div>
        </div>
    </form>
</div>
@endsection

@extends('layouts.institute')

@section('title', 'Document Encounter — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Document — {{ $encounter->encounter_number }}</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.encounters.show', $encounter) }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('medical.encounters.update', $encounter) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-md-12">
                    <div class="mb-3">
                        <label class="form-label" for="chief_complaint">Chief Complaint</label>
                        <textarea id="chief_complaint" name="chief_complaint" rows="2"
                                  class="form-control @error('chief_complaint') is-invalid @enderror">{{ old('chief_complaint', $encounter->chief_complaint) }}</textarea>
                        @error('chief_complaint')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="history_of_present_illness">History</label>
                        <textarea id="history_of_present_illness" name="history_of_present_illness" rows="3"
                                  class="form-control @error('history_of_present_illness') is-invalid @enderror">{{ old('history_of_present_illness', $encounter->history_of_present_illness) }}</textarea>
                        @error('history_of_present_illness')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="examination_notes">Examination</label>
                        <textarea id="examination_notes" name="examination_notes" rows="3"
                                  class="form-control @error('examination_notes') is-invalid @enderror">{{ old('examination_notes', $encounter->examination_notes) }}</textarea>
                        @error('examination_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="assessment_notes">Assessment</label>
                        <textarea id="assessment_notes" name="assessment_notes" rows="3"
                                  class="form-control @error('assessment_notes') is-invalid @enderror">{{ old('assessment_notes', $encounter->assessment_notes) }}</textarea>
                        @error('assessment_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="plan_notes">Plan</label>
                        <textarea id="plan_notes" name="plan_notes" rows="3"
                                  class="form-control @error('plan_notes') is-invalid @enderror">{{ old('plan_notes', $encounter->plan_notes) }}</textarea>
                        @error('plan_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="diagnosis_text">Diagnosis (text)</label>
                        <input type="text" id="diagnosis_text" name="diagnosis_text" maxlength="1000"
                               class="form-control @error('diagnosis_text') is-invalid @enderror"
                               value="{{ old('diagnosis_text', $encounter->diagnosis_text) }}">
                        @error('diagnosis_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="follow_up_notes">Follow-up</label>
                        <input type="text" id="follow_up_notes" name="follow_up_notes" maxlength="2000"
                               class="form-control @error('follow_up_notes') is-invalid @enderror"
                               value="{{ old('follow_up_notes', $encounter->follow_up_notes) }}">
                        @error('follow_up_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Save Documentation
                </button>
                <a href="{{ route('medical.encounters.show', $encounter) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@extends('layouts.institute')

@section('title', 'Edit Discharge Summary — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Edit {{ $summary->summary_number }}</h4>
        <a href="{{ route('medical.records.discharge-summaries.show', $summary) }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('medical.records.discharge-summaries.update', $summary) }}">
                @csrf @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Final Diagnosis</label>
                        <textarea name="final_diagnosis" class="form-control" rows="2">{{ old('final_diagnosis', $summary->final_diagnosis) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Condition on Discharge *</label>
                        <select name="condition_on_discharge" class="form-select" required>
                            @foreach($conditions as $key => $label)
                                <option value="{{ $key }}" {{ $summary->condition_on_discharge === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Hospital Course *</label>
                        <textarea name="hospital_course" class="form-control" rows="3" required>{{ old('hospital_course', $summary->hospital_course) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Procedures Done</label>
                        <textarea name="procedures_done" class="form-control" rows="2">{{ old('procedures_done', $summary->procedures_done) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Investigations</label>
                        <textarea name="investigations_summary" class="form-control" rows="2">{{ old('investigations_summary', $summary->investigations_summary) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Treatment Given</label>
                        <textarea name="treatment_given" class="form-control" rows="2">{{ old('treatment_given', $summary->treatment_given) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Discharge Medications *</label>
                        <textarea name="discharge_medications" class="form-control" rows="3" required>{{ old('discharge_medications', $summary->discharge_medications) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Discharge Instructions *</label>
                        <textarea name="discharge_instructions" class="form-control" rows="3" required>{{ old('discharge_instructions', $summary->discharge_instructions) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Diet Instructions</label>
                        <textarea name="diet_instructions" class="form-control" rows="2">{{ old('diet_instructions', $summary->diet_instructions) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Activity Restrictions</label>
                        <textarea name="activity_restrictions" class="form-control" rows="2">{{ old('activity_restrictions', $summary->activity_restrictions) }}</textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Follow-up Date</label>
                        <input type="date" name="follow_up_date" class="form-control" value="{{ old('follow_up_date', $summary->follow_up_date?->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Follow-up Department</label>
                        <input type="text" name="follow_up_department" class="form-control" value="{{ old('follow_up_department', $summary->follow_up_department) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Follow-up Instructions</label>
                        <input type="text" name="follow_up_instructions" class="form-control" value="{{ old('follow_up_instructions', $summary->follow_up_instructions) }}">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

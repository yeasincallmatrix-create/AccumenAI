@extends('layouts.institute')

@section('title', 'New Discharge Summary — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0"><i class="bi bi-box-arrow-right"></i> New Discharge Summary</h4>
        <a href="{{ route('medical.records.discharge-summaries.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <p class="text-muted small">Next number: <strong>{{ $summaryNumber }}</strong> (assigned on save)</p>
            <form method="POST" action="{{ route('medical.records.discharge-summaries.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Admission *</label>
                        <select name="admission_id" class="form-select" required>
                            <option value="">Select admission...</option>
                            @foreach($admissions as $adm)
                                <option value="{{ $adm->id }}" {{ (string) old('admission_id', $preselectedAdmission) === (string) $adm->id ? 'selected' : '' }}>
                                    #{{ $adm->id }} — {{ $adm->patient->full_name ?? 'N/A' }} ({{ $adm->admission_date?->format('d M Y') }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Discharge Date *</label>
                        <input type="date" name="discharge_date" class="form-control" value="{{ old('discharge_date', date('Y-m-d')) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Condition on Discharge *</label>
                        <select name="condition_on_discharge" class="form-select" required>
                            @foreach($conditions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Admission Diagnosis *</label>
                        <textarea name="admission_diagnosis" class="form-control" rows="2" required>{{ old('admission_diagnosis') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Final Diagnosis</label>
                        <textarea name="final_diagnosis" class="form-control" rows="2">{{ old('final_diagnosis') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Hospital Course *</label>
                        <textarea name="hospital_course" class="form-control" rows="3" required>{{ old('hospital_course') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Procedures Done</label>
                        <textarea name="procedures_done" class="form-control" rows="2">{{ old('procedures_done') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Investigations Summary</label>
                        <textarea name="investigations_summary" class="form-control" rows="2">{{ old('investigations_summary') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Treatment Given</label>
                        <textarea name="treatment_given" class="form-control" rows="2">{{ old('treatment_given') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Discharge Medications *</label>
                        <textarea name="discharge_medications" class="form-control" rows="3" required>{{ old('discharge_medications') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Discharge Instructions *</label>
                        <textarea name="discharge_instructions" class="form-control" rows="3" required>{{ old('discharge_instructions') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Diet Instructions</label>
                        <textarea name="diet_instructions" class="form-control" rows="2">{{ old('diet_instructions') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Activity Restrictions</label>
                        <textarea name="activity_restrictions" class="form-control" rows="2">{{ old('activity_restrictions') }}</textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Follow-up Date</label>
                        <input type="date" name="follow_up_date" class="form-control" value="{{ old('follow_up_date') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Follow-up Department</label>
                        <input type="text" name="follow_up_department" class="form-control" value="{{ old('follow_up_department') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Follow-up Instructions</label>
                        <input type="text" name="follow_up_instructions" class="form-control" value="{{ old('follow_up_instructions') }}">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Create Summary</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

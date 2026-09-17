@extends('layouts.institute')

@section('title', 'New Clinical Note — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0"><i class="bi bi-journal-plus"></i> New Clinical Note (SOAP)</h4>
        <a href="{{ route('medical.records.notes.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <p class="text-muted small">Next number: <strong>{{ $noteNumber }}</strong> (assigned on save)</p>
            <form method="POST" action="{{ route('medical.records.notes.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Patient *</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">Select patient...</option>
                            @foreach($patients as $p)
                                <option value="{{ $p->id }}" {{ (string) old('patient_id', $preselectedPatient) === (string) $p->id ? 'selected' : '' }}>
                                    {{ $p->full_name ?? ($p->first_name . ' ' . $p->last_name) }} ({{ $p->mr_number ?? $p->id }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Note Type *</label>
                        <select name="note_type" class="form-select" required>
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Noted At *</label>
                        <input type="datetime-local" name="noted_at" class="form-control" value="{{ old('noted_at', date('Y-m-d\TH:i')) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Subjective</label>
                        <textarea name="subjective" class="form-control" rows="3">{{ old('subjective') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Objective</label>
                        <textarea name="objective" class="form-control" rows="3">{{ old('objective') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Assessment</label>
                        <textarea name="assessment" class="form-control" rows="3">{{ old('assessment') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Plan</label>
                        <textarea name="plan" class="form-control" rows="3">{{ old('plan') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Free-text Content</label>
                        <textarea name="content" class="form-control" rows="2">{{ old('content') }}</textarea>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Create Note</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

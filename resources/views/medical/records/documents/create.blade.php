@extends('layouts.institute')

@section('title', 'Upload Document — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0"><i class="bi bi-upload"></i> Upload Medical Document</h4>
        <a href="{{ route('medical.records.documents.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <p class="text-muted small">Next number: <strong>{{ $documentNumber }}</strong> (assigned on save)</p>
            <form method="POST" action="{{ route('medical.records.documents.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Patient *</label>
                        <select name="patient_id" class="form-select @error('patient_id') is-invalid @enderror" required>
                            <option value="">Select patient...</option>
                            @foreach($patients as $p)
                                <option value="{{ $p->id }}" {{ (string) old('patient_id', $preselectedPatient) === (string) $p->id ? 'selected' : '' }}>
                                    {{ $p->full_name ?? ($p->first_name . ' ' . $p->last_name) }} ({{ $p->mr_number ?? $p->id }})
                                </option>
                            @endforeach
                        </select>
                        @error('patient_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Document Type *</label>
                        <select name="document_type" class="form-select @error('document_type') is-invalid @enderror" required>
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}" {{ old('document_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('document_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Document Date</label>
                        <input type="date" name="document_date" class="form-control" value="{{ old('document_date', date('Y-m-d')) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" required>
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">File * (max 20MB)</label>
                        <input type="file" name="file" class="form-control @error('file') is-invalid @enderror" required>
                        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Access Level</label>
                        <select name="access_level" class="form-select">
                            @foreach($levels as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tags (comma separated)</label>
                        <input type="text" name="tags" class="form-control" value="{{ old('tags') }}">
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_confidential" value="1" class="form-check-input" id="conf">
                            <label class="form-check-label" for="conf">Confidential</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="is_patient_visible" value="1" class="form-check-input" id="vis">
                            <label class="form-check-label" for="vis">Patient visible</label>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

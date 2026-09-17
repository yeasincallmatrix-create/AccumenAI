@extends('layouts.institute')

@section('title', 'Edit Document — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Edit Document {{ $document->document_number }}</h4>
        <a href="{{ route('medical.records.documents.show', $document) }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('medical.records.documents.update', $document) }}">
                @csrf @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" value="{{ old('title', $document->title) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Type *</label>
                        <select name="document_type" class="form-select" required>
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}" {{ old('document_type', $document->document_type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="document_date" class="form-control" value="{{ old('document_date', $document->document_date?->format('Y-m-d')) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2">{{ old('description', $document->description) }}</textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Access Level</label>
                        <select name="access_level" class="form-select">
                            @foreach($levels as $key => $label)
                                <option value="{{ $key }}" {{ $document->access_level === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-8 d-flex align-items-end gap-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_confidential" value="1" class="form-check-input" {{ $document->is_confidential ? 'checked' : '' }}>
                            <label class="form-check-label">Confidential</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="is_patient_visible" value="1" class="form-check-input" {{ $document->is_patient_visible ? 'checked' : '' }}>
                            <label class="form-check-label">Patient visible</label>
                        </div>
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

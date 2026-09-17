@extends('layouts.institute')

@section('title', 'Edit Clinical Note — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Edit {{ $note->note_number }}</h4>
        <a href="{{ route('medical.records.notes.show', $note) }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('medical.records.notes.update', $note) }}">
                @csrf @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Note Type *</label>
                        <select name="note_type" class="form-select" required>
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}" {{ $note->note_type === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Subjective</label>
                        <textarea name="subjective" class="form-control" rows="3">{{ old('subjective', $note->subjective) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Objective</label>
                        <textarea name="objective" class="form-control" rows="3">{{ old('objective', $note->objective) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Assessment</label>
                        <textarea name="assessment" class="form-control" rows="3">{{ old('assessment', $note->assessment) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Plan</label>
                        <textarea name="plan" class="form-control" rows="3">{{ old('plan', $note->plan) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Free-text Content</label>
                        <textarea name="content" class="form-control" rows="2">{{ old('content', $note->content) }}</textarea>
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

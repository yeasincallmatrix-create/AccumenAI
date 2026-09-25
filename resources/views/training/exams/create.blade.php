@extends('layouts.institute')

@section('title', 'Create Exam — Training — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('training.exams.index') }}" class="text-decoration-none">Exams</a></li>
        <li class="breadcrumb-item active">Create</li>
    </ol>
</nav>

<div class="page-header">
    <h4 class="page-header-title">Create Exam</h4>
</div>

<div class="admin-card">
    <form method="POST" action="{{ route('training.exams.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="title">Exam Title *</label>
                <input type="text" id="title" name="title" class="form-control" maxlength="200" value="{{ old('title') }}" required>
                @error('title') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="batch_id">Batch *</label>
                <select id="batch_id" name="batch_id" class="form-select" required>
                    <option value="">— Select batch —</option>
                    @foreach ($batches as $batch)
                        <option value="{{ $batch->id }}" @selected(old('batch_id', $selectedBatch?->id) == $batch->id)>
                            {{ $batch->name }}@if($batch->batch_code) ({{ $batch->batch_code }})@endif
                        </option>
                    @endforeach
                </select>
                @error('batch_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @if ($batches->isEmpty())
                    <div class="form-text text-warning small">No active batches found. Create a batch first.</div>
                @endif
            </div>
            <div class="col-md-6">
                <label class="form-label" for="exam_date">Exam Date</label>
                <input type="datetime-local" id="exam_date" name="exam_date" class="form-control" value="{{ old('exam_date') }}">
                @error('exam_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="full_marks">Full Marks</label>
                <input type="number" id="full_marks" name="full_marks" class="form-control" step="0.01" min="0.01" value="{{ old('full_marks') }}" required>
                @error('full_marks') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="pass_marks">Pass Marks</label>
                <input type="number" id="pass_marks" name="pass_marks" class="form-control" step="0.01" min="0" value="{{ old('pass_marks') }}" required>
                @error('pass_marks') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="written_percent">Written %</label>
                <input type="number" id="written_percent" name="written_percent" class="form-control" step="0.01" min="0" max="100" value="{{ old('written_percent', 0) }}">
                @error('written_percent') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="practical_percent">Practical %</label>
                <input type="number" id="practical_percent" name="practical_percent" class="form-control" step="0.01" min="0" max="100" value="{{ old('practical_percent', 0) }}">
                @error('practical_percent') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="viva_percent">Viva %</label>
                <input type="number" id="viva_percent" name="viva_percent" class="form-control" step="0.01" min="0" max="100" value="{{ old('viva_percent', 0) }}">
                @error('viva_percent') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="weight_percent">Weight % <span class="text-muted small">(toward batch final)</span></label>
                <input type="number" id="weight_percent" name="weight_percent" class="form-control" step="0.01" min="0" max="100" value="{{ old('weight_percent') }}" placeholder="e.g. 40">
                <div class="form-text">How much this exam counts in the weighted batch result. Leave blank for no weight.</div>
                @error('weight_percent') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    @foreach (['scheduled', 'ongoing', 'completed', 'cancelled'] as $s)
                        <option value="{{ $s }}" {{ old('status', 'scheduled') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                @error('status') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save</button>
            <a href="{{ route('training.exams.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection

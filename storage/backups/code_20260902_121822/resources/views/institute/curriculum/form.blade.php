@extends('layouts.institute')

@section('title', ($curriculum ? 'Edit Curriculum — AccumenAI' : 'New Curriculum — AccumenAI'))

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <a href="{{ $curriculum ? route('curricula.show', $curriculum) : route('curricula.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="bi bi-arrow-left me-1"></i>Curricula
        </a>
        <h4 class="page-header-title">{{ $curriculum ? 'Edit Curriculum v'.$curriculum->version : 'New Curriculum' }}</h4>
        <p class="page-header-desc mb-0">Version numbers are assigned automatically. A curriculum referenced by batches is frozen — changes require a new version.</p>
    </div>
</div>

<div class="admin-card">
    <form method="POST" action="{{ $curriculum ? route('curricula.update', $curriculum) : route('curricula.store') }}">
        @csrf
        @if ($curriculum) @method('PUT') @endif

        <h6 class="text-muted text-uppercase small fw-semibold mb-2">Curriculum header</h6>
        <div class="row g-3 mb-3">
            @if (! $curriculum)
                <div class="col-md-6">
                    <label class="form-label">Course <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" name="course_id" required>
                        <option value="">— Select course —</option>
                        @foreach ($courses as $course)
                            <option value="{{ $course->id }}" @selected((string) old('course_id', $selectedCourseId) === (string) $course->id)>
                                {{ $course->name }} @if ($course->course_code)({{ $course->course_code }})@endif
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="{{ $curriculum ? 'col-md-12' : 'col-md-6' }}">
                <label class="form-label">Title <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" name="title" value="{{ old('title', $curriculum?->title) }}" required maxlength="200">
            </div>
            <div class="col-md-3">
                <label class="form-label">Effective date</label>
                <input type="date" class="form-control form-control-sm" name="effective_date" value="{{ old('effective_date', $curriculum?->effective_date?->format('Y-m-d')) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Total classes</label>
                <input type="number" class="form-control form-control-sm" name="total_classes" value="{{ old('total_classes', $curriculum?->total_classes) }}" min="1" max="100000">
            </div>
            <div class="col-md-3">
                <label class="form-label">Total duration (hours)</label>
                <input type="number" class="form-control form-control-sm" name="total_duration_hours" value="{{ old('total_duration_hours', $curriculum?->total_duration_hours) }}" min="0" step="0.5">
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea class="form-control form-control-sm" name="description" rows="3">{{ old('description', $curriculum?->description) }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Learning objectives <span class="text-muted">(one per line)</span></label>
                <textarea class="form-control form-control-sm" name="learning_objectives" rows="4">{{ old('learning_objectives', is_array($curriculum?->learning_objectives) ? implode("\n", $curriculum->learning_objectives) : '') }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Version notes</label>
                <textarea class="form-control form-control-sm" name="version_notes" rows="4">{{ old('version_notes', $curriculum?->version_notes) }}</textarea>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
            <div>
                @if ($curriculum && ! $referenced)
                    <button type="button" class="btn btn-outline-danger btn-sm" data-confirm-delete
                            data-href="{{ route('curricula.destroy', $curriculum) }}">
                        <i class="bi bi-trash me-1"></i>Delete curriculum
                    </button>
                @endif
            </div>
            <div class="d-flex gap-2">
                <a href="{{ $curriculum ? route('curricula.show', $curriculum) : route('curricula.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>{{ $curriculum ? 'Save changes' : 'Create curriculum' }}</button>
            </div>
        </div>
    </form>
</div>

<form id="deleteForm" method="POST" class="d-none">
    @csrf
    @method('DELETE')
</form>
@endsection

@push('scripts')
<script>
(function () {
    var deleteBtn = document.querySelector('[data-confirm-delete]');
    var deleteForm = document.getElementById('deleteForm');
    if (deleteBtn && deleteForm) {
        deleteBtn.addEventListener('click', function () {
            if (confirm('Delete this curriculum version? This cannot be undone.')) {
                deleteForm.action = deleteBtn.getAttribute('data-href');
                deleteForm.submit();
            }
        });
    }
})();
</script>
@endpush
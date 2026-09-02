@extends('layouts.institute')

@section('title', ($subject ? mawa_lang('subjects.edit_subject') : mawa_lang('subjects.add_subject')) . ' — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $subject ? mawa_e('subjects.edit_subject') : mawa_e('subjects.add_subject') }}</h4>
        <p class="page-header-desc">{{ $subject ? 'Update subject details' : 'Create a new subject for your institute' }} — {{ $institute->name ?? '' }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('courses.manage.subjects.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Subjects
        </a>
    </div>
</div>

<div class="admin-card">
    <form id="subjectForm" method="POST" action="{{ $subject ? route('courses.manage.subjects.update', $subject) : route('courses.manage.subjects.store') }}" data-ajax-enabled>
        @csrf
        @if ($subject)
            @method('PUT')
        @endif

        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="name">{{ mawa_e('subjects.name') }} <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" class="form-control" value="{{ $subject->name ?? '' }}" required maxlength="255" @if($subject) readonly @endif>
                    @if ($subject)
                        <div class="form-text text-warning">
                            <i class="bi bi-lock me-1"></i>{{ mawa_lang('subjects.name_cannot_change') }}
                        </div>
                    @endif
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="short_name">{{ mawa_e('subjects.short_name') }}</label>
                    <input type="text" id="short_name" name="short_name" class="form-control" value="{{ $subject->short_name ?? '' }}" maxlength="100">
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="subject_code">{{ mawa_e('subjects.code') }}</label>
                    <input type="text" id="subject_code" name="subject_code" class="form-control" value="{{ $subject->subject_code ?? '' }}" maxlength="50">
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="subject_type">{{ mawa_e('subjects.type') }} <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" value="{{ ucfirst($derivedSubjectType) }} — {{ $domain === 'academic' ? 'Academic' : ($domain === 'professional' ? 'Professional' : 'Other') }} ({{ $domain }})" disabled readonly>
                    <input type="hidden" name="subject_type" value="{{ $derivedSubjectType }}">
                    <div class="form-text text-muted">
                        <i class="bi bi-shield-lock me-1"></i>Subject type is derived from your institute domain ({{ $domain }}) and cannot be changed.
                    </div>
                    @if ($subject && $subject->subject_type !== $derivedSubjectType)
                        <div class="form-text text-danger">
                            <i class="bi bi-exclamation-triangle me-1"></i>Existing subject domain mismatch — contact support.
                        </div>
                    @endif
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="category_id">{{ mawa_e('subjects.category') }} <span class="text-danger">*</span></label>
                    <select id="category_id" name="category_id" class="form-select" required>
                        <option value="">Select Category</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(($subject->category_id ?? old('category_id')) === $cat->id)>{{ $cat->name }} <span class="badge text-bg-light text-dark subject-type-badge">{{ $cat->subject_type }}</span></option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="status">{{ mawa_e('subjects.status') }} <span class="text-danger">*</span></label>
                    <select id="status" name="status" class="form-select" required>
                        <option value="active" @selected(($subject->status ?? old('status', 'active')) === 'active')>Active</option>
                        <option value="inactive" @selected(($subject->status ?? old('status')) === 'inactive')>Inactive</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label" for="description">{{ mawa_e('subjects.description') }}</label>
                    <textarea id="description" name="description" class="form-control" rows="3">{{ $subject->description ?? '' }}</textarea>
                </div>
            </div>
        </div>

        <div class="card-footer border-top d-flex justify-content-end gap-2">
            <a href="{{ route('courses.manage.subjects.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ mawa_e($subject ? 'common.save' : 'common.create') }}</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // Basic form validation feedback
    var form = document.getElementById('subjectForm');
    if (!form) { return; }

    form.addEventListener('submit', function (e) {
        if (!form.checkValidity()) {
            form.reportValidity();
            e.preventDefault();
            return;
        }

        var submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
        }
    });

    // Category filtering by subject type
    var subjectTypeSelect = document.getElementById('subject_type');
    var categorySelect = document.getElementById('category_id');
    if (subjectTypeSelect && categorySelect) {
        var allOptions = Array.from(categorySelect.options).slice(1); // skip empty option
        subjectTypeSelect.addEventListener('change', function () {
            var type = subjectTypeSelect.value;
            var currentValue = categorySelect.value;
            categorySelect.innerHTML = '<option value="">Select Category</option>';
            allOptions.forEach(function (opt) {
                if (!type || opt.dataset.subjectType === type) {
                    categorySelect.appendChild(opt.cloneNode(true));
                }
            });
            categorySelect.value = currentValue;
        });
    }
})();
</script>
@endpush
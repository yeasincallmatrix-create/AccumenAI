@extends('layouts.institute')

@section('title', 'Add Subjects — ' . $course->name . ' — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('training.courses.index') }}" class="text-decoration-none">Courses</a></li>
        <li class="breadcrumb-item"><a href="{{ route('training.courses.show', $course) }}" class="text-decoration-none">{{ $course->name }}</a></li>
        <li class="breadcrumb-item active">Add Subjects</li>
    </ol>
</nav>

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Add Subjects</h4>
        <p class="page-header-desc mb-0">Attach existing subjects or create a new subject for <strong>{{ $course->name }}</strong></p>
    </div>
    <a href="{{ route('training.courses.show', $course) }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Course
    </a>
</div>

@if (session('status'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@error('subjects')
    <div class="alert alert-danger" role="alert">{{ $message }}</div>
@enderror

<div class="row g-3 mt-1">
    <div class="col-lg-7">
        <div class="admin-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-primary mb-0">
                    <i class="bi bi-check2-square me-1"></i>Attach Existing Subjects
                    <span class="badge bg-secondary ms-1">{{ $availableSubjects->count() }}</span>
                </h6>
            </div>

            @if ($user->hasPermission('training.courses.manage'))
                <form method="POST" action="{{ route('training.courses.course-subjects.attach', $course) }}">
                    @csrf
                    <div class="form-check mb-2">
                        <label class="form-check-label">
                            <input type="checkbox" class="form-check-input" id="selectAllSubjects">
                            Select all
                        </label>
                    </div>

                    <div class="border rounded p-2" style="max-height:420px; overflow-y:auto;">
                        @forelse ($availableSubjects as $subject)
                            <div class="form-check py-1 border-bottom">
                                <label class="form-check-label d-flex align-items-center justify-content-between w-100">
                                    <span>
                                        <input type="checkbox" class="form-check-input me-2 subject-check"
                                               name="subjects[]" value="{{ $subject->id }}"
                                               @checked(in_array((int) $subject->id, $attachedIds, true))>
                                        <span class="fw-semibold">{{ $subject->name }}</span>
                                        @if ($subject->subject_code)
                                            <span class="text-muted small ms-1">{{ $subject->subject_code }}</span>
                                        @endif
                                    </span>
                                    <span class="d-flex align-items-center gap-2">
                                        @if ($subject->category)
                                            <span class="badge text-bg-light text-dark small">{{ $subject->category->name }}</span>
                                        @endif
                                        @if (in_array((int) $subject->id, $attachedIds, true))
                                            <span class="badge text-bg-success">Attached</span>
                                        @endif
                                    </span>
                                </label>
                            </div>
                        @empty
                            <div class="text-muted small p-3 text-center">
                                No active subjects found. Create one using the form on the right.
                            </div>
                        @endforelse
                    </div>

                    <div class="form-text text-muted small mt-2 mb-3">
                        Checked subjects will be attached. Unchecked subjects already attached will be removed.
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Save Attached Subjects
                    </button>
                </form>
            @else
                <div class="border rounded p-2" style="max-height:420px; overflow-y:auto;">
                    @forelse ($availableSubjects as $subject)
                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                            <span>
                                <span class="fw-semibold">{{ $subject->name }}</span>
                                @if ($subject->subject_code)
                                    <span class="text-muted small ms-1">{{ $subject->subject_code }}</span>
                                @endif
                            </span>
                            @if (in_array((int) $subject->id, $attachedIds, true))
                                <span class="badge text-bg-success">Attached</span>
                            @endif
                        </div>
                    @empty
                        <div class="text-muted small p-3 text-center">No active subjects found.</div>
                    @endforelse
                </div>
            @endif
        </div>
    </div>

    <div class="col-lg-5">
        <div class="admin-card h-100">
            <h6 class="fw-bold text-primary mb-3">
                <i class="bi bi-plus-square me-1"></i>Create New Subject
            </h6>

            @if ($user->hasPermission('training.courses.manage'))
                <form method="POST" action="{{ route('training.courses.course-subjects.create', $course) }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="name">Subject Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" maxlength="255"
                                   value="{{ old('name') }}" required placeholder="e.g. Adobe Premiere Pro">
                            @error('name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="short_name">Short Name</label>
                            <input type="text" id="short_name" name="short_name" class="form-control" maxlength="100"
                                   value="{{ old('short_name') }}" placeholder="e.g. APP">
                            @error('short_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="subject_code">Code</label>
                            <input type="text" id="subject_code" name="subject_code" class="form-control" maxlength="50"
                                   value="{{ old('subject_code') }}" placeholder="e.g. VD-02">
                            @error('subject_code') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="category_id">Category <span class="text-danger">*</span></label>
                            <select id="category_id" name="category_id" class="form-select" required>
                                <option value="">— Select category —</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}" @selected(old('category_id', $course->category_id) == $cat->id)>
                                        {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3"
                                      placeholder="Optional description">{{ old('description') }}</textarea>
                            @error('description') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    @if ($categories->isEmpty())
                        <div class="alert alert-warning small mt-3 mb-0">
                            No categories found. Create a course category first.
                        </div>
                    @endif

                    <button type="submit" class="btn btn-primary mt-3" @disabled($categories->isEmpty())>
                        <i class="bi bi-plus-lg me-1"></i>Create &amp; Attach Subject
                    </button>
                </form>
            @else
                <p class="text-muted mb-0">You don't have permission to create subjects.</p>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var selectAll = document.getElementById('selectAllSubjects');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.subject-check').forEach(function (cb) {
                cb.checked = selectAll.checked;
            });
        });
    }
})();
</script>
@endpush

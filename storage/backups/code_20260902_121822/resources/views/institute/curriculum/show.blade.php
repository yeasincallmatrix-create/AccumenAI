@extends('layouts.institute')

@section('title', ($curriculum->title ?? 'Curriculum') . ' — AccumenAI')

@php
    $statusBadge = ['draft' => 'text-bg-warning', 'active' => 'text-bg-success', 'archived' => 'text-bg-secondary'];
    $statusNames = ['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived'];
    $canEdit = $user->hasPermission('curriculum.manage');
@endphp

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <a href="{{ route('curricula.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="bi bi-arrow-left me-1"></i>Curricula
        </a>
        <h4 class="page-header-title">
            {{ $curriculum->title }} <span class="badge bg-dark bg-opacity-75 ms-1">v{{ $curriculum->version }}</span>
            <span class="badge {{ $statusBadge[$curriculum->status] ?? 'text-bg-secondary' }}">{{ $statusNames[$curriculum->status] ?? $curriculum->status }}</span>
        </h4>
        <p class="page-header-desc mb-0">
            {{ $curriculum->course?->name ?? '—' }} @if ($curriculum->course?->course_code)({{ $curriculum->course->course_code }})@endif
            · Effective {{ $curriculum->effective_date?->format('d M Y') ?? 'not set' }}
        </p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        @if ($canEdit && $curriculum->status === 'draft')
            <form method="POST" action="{{ route('curricula.activate', $curriculum) }}">
                @csrf
                <button class="btn btn-success btn-sm"><i class="bi bi-check-circle me-1"></i>Activate version</button>
            </form>
        @endif
        @if ($canEdit && ! $referenced)
            <a href="{{ route('curricula.edit', $curriculum) }}" class="btn btn-outline-secondary btn-sm">Edit</a>
        @endif
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if ($referenced)
    <div class="alert alert-warning">
        <i class="bi bi-lock me-1"></i> This version is referenced by {{ $curriculum->batches()->withoutGlobalScopes()->where('institute_id', $curriculum->institute_id)->count() }} batch(es). It is frozen — make changes in a new version.
    </div>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="admin-card h-100">
            <div class="text-muted small text-uppercase fw-semibold">Description</div>
            <p class="mb-0 mt-1">{{ $curriculum->description ?: '—' }}</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card h-100">
            <div class="text-muted small text-uppercase fw-semibold">Structure</div>
            <p class="mb-0 mt-1">{{ $curriculum->modules->count() }} module(s) · {{ $curriculum->modules->sum(fn ($m) => $m->lessons->count()) }} lesson(s) · {{ $curriculum->total_classes ?? '—' }} classes · {{ $curriculum->total_duration_hours ?? '—' }} hrs</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card h-100">
            <div class="text-muted small text-uppercase fw-semibold">Version notes</div>
            <p class="mb-0 mt-1">{{ $curriculum->version_notes ?: '—' }}</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card h-100">
            <div class="text-muted small text-uppercase fw-semibold">Learning objectives</div>
            <ul class="mb-0 mt-1 ps-3">
                @forelse ($curriculum->learning_objectives ?? [] as $objective)
                    <li>{{ $objective }}</li>
                @empty
                    <li class="text-muted">—</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

@if ($canEdit && ! $referenced)
    <div class="admin-card mb-3">
        <form method="POST" action="{{ route('curricula.modules.store', $curriculum) }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-3">
                <label class="form-label mb-1">Module name <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" name="name" required maxlength="200" placeholder="e.g. Module 1 — Fundamentals">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Code</label>
                <input type="text" class="form-control form-control-sm" name="code" maxlength="40">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Type</label>
                <select class="form-select form-select-sm" name="module_type">
                    <option value="">—</option>
                    @foreach (['theory', 'practical', 'project', 'thesis'] as $type)
                        <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Total marks</label>
                <input type="number" class="form-control form-control-sm" name="total_marks" min="0" step="0.01">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Credit hours</label>
                <input type="number" class="form-control form-control-sm" name="credit_hours" min="0" step="0.01">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Class count</label>
                <input type="number" class="form-control form-control-sm" name="class_count" min="1">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Duration (hrs)</label>
                <input type="number" class="form-control form-control-sm" name="duration_hours" min="0" step="0.5">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Display order</label>
                <input type="number" class="form-control form-control-sm" name="display_order" value="0" min="0">
            </div>
            <div class="col-md-2 d-flex align-items-center" style="height:38px">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" name="is_optional" value="1" id="module_optional">
                    <label class="form-check-label" for="module_optional">Optional</label>
                </div>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary btn-sm w-100"><i class="bi bi-plus-lg me-1"></i>Add module</button>
            </div>
        </form>
    </div>
@endif

@forelse ($curriculum->modules as $module)
    <div class="admin-card mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <h6 class="mb-0">
                <span class="badge text-bg-dark me-1">{{ $loop->iteration }}</span>
                {{ $module->name }}
                @if ($module->code)<span class="text-muted small ms-1">{{ $module->code }}</span>@endif
                @if ($module->is_optional)<span class="badge text-bg-info">optional</span>@endif
                @if ($module->module_type)<span class="badge text-bg-light text-dark border ms-1">{{ ucfirst($module->module_type) }}</span>@endif
            </h6>
            <div class="d-flex gap-2 align-items-center">
                @if ($canEdit && ! $referenced)
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-module-toggle="module-edit-{{ $module->id }}">Edit</button>
                    <form method="POST" action="{{ route('curricula.modules.destroy', $module) }}" onsubmit="return confirm('Delete this module and its lessons?');">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                @endif
            </div>
        </div>

        <div class="text-muted small mb-2">
            @if ($module->theory_marks !== null || $module->practical_marks !== null || $module->viva_marks !== null)
                Marks — theory {{ $module->theory_marks ?? '—' }} · practical {{ $module->practical_marks ?? '—' }} · viva {{ $module->viva_marks ?? '—' }} · total {{ $module->total_marks ?? '—' }}
            @else
                <span class="text-muted">No mark breakdown set (assessment stays in the assessment pipeline).</span>
            @endif
            @if ($module->class_count || $module->duration_hours)
                &nbsp;·&nbsp; {{ $module->class_count ?? '—' }} classes · {{ $module->duration_hours ?? '—' }} hrs
            @endif
        </div>

        @if ($module->description)
            <p class="text-muted small mb-2">{{ $module->description }}</p>
        @endif

        @if ($canEdit && ! $referenced)
            <div id="module-edit-{{ $module->id }}" class="border rounded p-3 mb-3 bg-light d-none">
                <form method="POST" action="{{ route('curricula.modules.update', $module) }}" class="row g-2 align-items-end">
                    @csrf
                    @method('PUT')
                    <div class="col-md-3">
                        <label class="form-label mb-1">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" name="name" value="{{ $module->name }}" required maxlength="200">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Code</label>
                        <input type="text" class="form-control form-control-sm" name="code" value="{{ $module->code }}" maxlength="40">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Type</label>
                        <select class="form-select form-select-sm" name="module_type">
                            <option value="">—</option>
                            @foreach (['theory', 'practical', 'project', 'thesis'] as $type)
                                <option value="{{ $type }}" @selected($module->module_type === $type)>{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Theory marks</label>
                        <input type="number" class="form-control form-control-sm" name="theory_marks" value="{{ $module->theory_marks }}" min="0" step="0.01">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Practical marks</label>
                        <input type="number" class="form-control form-control-sm" name="practical_marks" value="{{ $module->practical_marks }}" min="0" step="0.01">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Viva marks</label>
                        <input type="number" class="form-control form-control-sm" name="viva_marks" value="{{ $module->viva_marks }}" min="0" step="0.01">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Total marks</label>
                        <input type="number" class="form-control form-control-sm" name="total_marks" value="{{ $module->total_marks }}" min="0" step="0.01">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Credit hours</label>
                        <input type="number" class="form-control form-control-sm" name="credit_hours" value="{{ $module->credit_hours }}" min="0" step="0.01">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Class count</label>
                        <input type="number" class="form-control form-control-sm" name="class_count" value="{{ $module->class_count }}" min="1">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Duration (hrs)</label>
                        <input type="number" class="form-control form-control-sm" name="duration_hours" value="{{ $module->duration_hours }}" min="0" step="0.5">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Display order</label>
                        <input type="number" class="form-control form-control-sm" name="display_order" value="{{ $module->display_order }}" min="0">
                    </div>
                    <div class="col-md-2 d-flex align-items-center" style="height:38px">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="is_optional" value="1" id="module_optional_{{ $module->id }}" @checked($module->is_optional)>
                            <label class="form-check-label" for="module_optional_{{ $module->id }}">Optional</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-1">Description</label>
                        <input type="text" class="form-control form-control-sm" name="description" value="{{ $module->description }}">
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-primary btn-sm">Save</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-module-toggle="module-edit-{{ $module->id }}">Close</button>
                    </div>
                </form>
            </div>
        @endif

        @if ($canEdit && ! $referenced)
            <div class="mb-2">
                <form method="POST" action="{{ route('curricula.lessons.store', $module) }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label mb-1">Lesson title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" name="title" required maxlength="200" placeholder="e.g. Lesson 1 — Introduction">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Duration (min)</label>
                        <input type="number" class="form-control form-control-sm" name="duration_minutes" min="1">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Display order</label>
                        <input type="number" class="form-control form-control-sm" name="display_order" value="0" min="0">
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>Add lesson</button>
                    </div>
                </form>
            </div>
        @endif

        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr class="text-muted small">
                    <th style="width:36px">#</th>
                    <th>Lesson</th>
                    <th>Duration</th>
                    <th>Objective / reference</th>
                    @if ($canEdit && ! $referenced)
                        <th class="text-end" style="width:120px">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($module->lessons as $lesson)
                    <tr>
                        <td class="text-muted">{{ $loop->iteration }}</td>
                        <td class="fw-semibold">{{ $lesson->title }}</td>
                        <td class="text-muted">{{ $lesson->duration_minutes ? $lesson->duration_minutes.' min' : '—' }}</td>
                        <td class="text-muted small">
                            @if ($lesson->learning_objective) <div>{{ $lesson->learning_objective }}</div> @endif
                            @if ($lesson->content_reference) <div class="text-truncate" style="max-width:320px">{{ $lesson->content_reference }}</div> @endif
                        </td>
                        @if ($canEdit && ! $referenced)
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-module-toggle="lesson-edit-{{ $lesson->id }}">Edit</button>
                                <form method="POST" action="{{ route('curricula.lessons.destroy', $lesson) }}" class="d-inline" onsubmit="return confirm('Delete this lesson?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        @endif
                    </tr>
                    @if ($canEdit && ! $referenced)
                        <tr id="lesson-edit-{{ $lesson->id }}" class="d-none">
                            <td></td>
                            <td colspan="4">
                                <form method="POST" action="{{ route('curricula.lessons.update', $lesson) }}" class="row g-2 align-items-end bg-light border rounded p-3">
                                    @csrf
                                    @method('PUT')
                                    <div class="col-md-4">
                                        <label class="form-label mb-1">Title <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" name="title" value="{{ $lesson->title }}" required maxlength="200">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-1">Duration (min)</label>
                                        <input type="number" class="form-control form-control-sm" name="duration_minutes" value="{{ $lesson->duration_minutes }}" min="1">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-1">Display order</label>
                                        <input type="number" class="form-control form-control-sm" name="display_order" value="{{ $lesson->display_order }}" min="0">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label mb-1">Content reference</label>
                                        <input type="text" class="form-control form-control-sm" name="content_reference" value="{{ $lesson->content_reference }}" maxlength="500">
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label mb-1">Learning objective</label>
                                        <input type="text" class="form-control form-control-sm" name="learning_objective" value="{{ $lesson->learning_objective }}">
                                    </div>
                                    <div class="col-md-4 d-flex gap-2">
                                        <button class="btn btn-primary btn-sm">Save</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-module-toggle="lesson-edit-{{ $lesson->id }}">Close</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="5" class="text-muted py-3 text-center">No lessons in this module yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@empty
    <div class="admin-card">
        <p class="text-center text-muted py-4 mb-0">This curriculum has no modules yet.</p>
    </div>
@endforelse

<div class="admin-card">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <h6 class="mb-0"><i class="bi bi-paperclip me-1"></i>Course materials</h6>
        @if ($canEdit)
            <form method="POST" action="{{ route('courses.manage.materials.store', $curriculum->course) }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                @csrf
                <div class="col-auto">
                    <input type="file" class="form-control form-control-sm" name="file" required>
                </div>
                <div class="col-auto">
                    <input type="text" class="form-control form-control-sm" name="title" placeholder="Title (optional)" maxlength="200">
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-primary"><i class="bi bi-upload me-1"></i>Upload</button>
                </div>
            </form>
        @endif
    </div>
    <table class="table table-sm align-middle mb-0">
        <thead>
            <tr class="text-muted small">
                <th>Title</th>
                <th>Type</th>
                <th>Size</th>
                @if ($canEdit)
                    <th class="text-end">Actions</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($materials as $material)
                <tr>
                    <td>
                        <a href="{{ asset('storage/'.$material->file_path) }}" target="_blank" class="text-decoration-none">{{ $material->title }}</a>
                    </td>
                    <td class="text-muted">{{ $material->file_type }}</td>
                    <td class="text-muted">{{ $material->file_size ? number_format($material->file_size / 1024, 1).' KB' : '—' }}</td>
                    @if ($canEdit)
                        <td class="text-end">
                            <form method="POST" action="{{ route('courses.manage.materials.destroy', $material) }}" class="d-inline" onsubmit="return confirm('Delete this material?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-muted py-3 text-center">No materials uploaded yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

@push('scripts')
<script>
(function () {
    document.querySelectorAll('[data-module-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = document.getElementById(btn.getAttribute('data-module-toggle'));
            if (target) { target.classList.toggle('d-none'); }
        });
    });
})();
</script>
@endpush
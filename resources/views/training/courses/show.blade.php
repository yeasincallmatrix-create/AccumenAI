@extends('layouts.institute')

@section('title', $course->name . ' — AccumenAI')

@php
    $statusBadge = ['active' => 'text-bg-success', 'inactive' => 'text-bg-secondary', 'draft' => 'text-bg-warning'];
    $statusNames = ['active' => 'Active', 'inactive' => 'Inactive', 'draft' => 'Draft'];
    $levelNames = ['basic' => 'Basic', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'];
    $list = function ($value) {
        if (is_array($value)) {
            return array_values(array_filter($value, fn ($v) => trim((string) $v) !== ''));
        }
        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $value)), fn ($v) => $v !== ''));
    };
    $requirements = $list($course->requirements);
    $outcomes = $list($course->outcomes);
    $prerequisites = $list($course->prerequisites);
@endphp

@section('content')
<div class="page-header d-flex flex-wrap align-items-start justify-content-between gap-3">
    <div class="page-header-text">
        <a href="{{ route('training.courses.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="bi bi-arrow-left me-1"></i>Course Master
        </a>
        <h4 class="page-header-title">
            {{ $course->name }}
            @if ($course->course_code)
                <span class="badge text-bg-dark bg-opacity-75 ms-1">{{ $course->course_code }}</span>
            @endif
            <span class="badge {{ $statusBadge[$course->status] ?? 'text-bg-secondary' }} ms-1">{{ $statusNames[$course->status] ?? $course->status }}</span>
            @if ($course->is_featured)
                <span class="badge text-bg-info ms-1"><i class="bi bi-star-fill me-1"></i>Featured</span>
            @endif
        </h4>
        <p class="page-header-desc mb-0">{{ $course->short_description ?: 'Course detail overview — curriculum, pricing, subjects and materials.' }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2 pt-1" style="flex-shrink:0;">
        <a href="{{ route('curricula.index', ['course_id' => $course->id]) }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-journal-text me-1"></i>Curriculum
        </a>
        <a href="{{ route('training.courses.edit', $course) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit Course
        </a>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-md-3 col-6">
        <div class="admin-card h-100 text-center">
            <div class="fs-3 fw-bold text-primary">{{ number_format($course->fee ?? 0, 0) }}</div>
            <div class="text-muted small">Course Fee</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="admin-card h-100 text-center">
            <div class="fs-3 fw-bold text-primary">
                @if (($course->duration_value ?? 0) > 0)
                    {{ rtrim(rtrim(number_format((float) $course->duration_value, 2, '.', ''), '0'), '.') }} {{ $course->duration_type }}
                @else
                    —
                @endif
            </div>
            <div class="text-muted small">Duration</div>
        </div>
    </div>
    <div class="col-md-2 col-4">
        <div class="admin-card h-100 text-center">
            <div class="fs-3 fw-bold text-primary">{{ $course->subjects_count ?? 0 }}</div>
            <div class="text-muted small">Subjects</div>
        </div>
    </div>
    <div class="col-md-2 col-4">
        <div class="admin-card h-100 text-center">
            <div class="fs-3 fw-bold text-primary">{{ $course->materials_count ?? 0 }}</div>
            <div class="text-muted small">Materials</div>
        </div>
    </div>
    <div class="col-md-2 col-4">
        <div class="admin-card h-100 text-center">
            <div class="fs-3 fw-bold text-primary">{{ $course->batches_count ?? 0 }}</div>
            <div class="text-muted small">Batches</div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-4">
        <div class="admin-card h-100">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-journal-bookmark me-1"></i>Course Details</h6>
            @if ($course->banner)
                <img src="{{ asset('storage/' . $course->banner) }}" alt="{{ $course->name }}" class="img-fluid rounded mb-3" style="max-height:160px; width:100%; object-fit:cover;">
            @endif
            <dl class="row mb-0 profile-dl">
                <dt class="col-5">Code</dt>
                <dd class="col-7 fw-semibold text-primary">{{ $course->course_code ?? '—' }}</dd>
                <dt class="col-5">Short name</dt>
                <dd class="col-7">{{ $course->short_name ?? '—' }}</dd>
                <dt class="col-5">Category</dt>
                <dd class="col-7">{{ $course->category?->name ?? '—' }}</dd>
                <dt class="col-5">Sub category</dt>
                <dd class="col-7">{{ $course->subCategory?->name ?? '—' }}</dd>
                <dt class="col-5">Level</dt>
                <dd class="col-7">{{ $levelNames[$course->level] ?? ucfirst($course->level ?? '—') }}</dd>
                <dt class="col-5">Language</dt>
                <dd class="col-7">{{ $course->language ?? '—' }}</dd>
                <dt class="col-5">Mode</dt>
                <dd class="col-7">{{ $course->mode ? ucfirst($course->mode) : '—' }}</dd>
                <dt class="col-5">Status</dt>
                <dd class="col-7">
                    <span class="badge {{ $statusBadge[$course->status] ?? 'text-bg-secondary' }}">{{ $statusNames[$course->status] ?? $course->status }}</span>
                </dd>
            </dl>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-card h-100">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-clock-history me-1"></i>Duration &amp; Structure</h6>
            <dl class="row mb-0 profile-dl">
                <dt class="col-6">Duration</dt>
                <dd class="col-6">
                    @if (($course->duration_value ?? 0) > 0)
                        {{ rtrim(rtrim(number_format((float) $course->duration_value, 2, '.', ''), '0'), '.') }} {{ $course->duration_type }}
                    @else
                        —
                    @endif
                </dd>
                <dt class="col-6">Weekly classes</dt>
                <dd class="col-6">{{ $course->weekly_classes ?? '—' }}</dd>
                <dt class="col-6">Total classes</dt>
                <dd class="col-6">{{ $course->total_classes ?? '—' }}</dd>
                <dt class="col-6">Total hours</dt>
                <dd class="col-6">{{ $course->total_hours ?? '—' }}</dd>
                <dt class="col-6">Batch capacity</dt>
                <dd class="col-6">{{ $course->batch_capacity_default ?? '—' }}</dd>
                <dt class="col-6">Display order</dt>
                <dd class="col-6">{{ $course->display_order ?? 0 }}</dd>
            </dl>

            <h6 class="fw-bold text-primary mt-4 mb-3"><i class="bi bi-cash-coin me-1"></i>Pricing</h6>
            <dl class="row mb-0 profile-dl">
                <dt class="col-6">Course fee</dt>
                <dd class="col-6 fw-semibold text-primary">{{ number_format($course->fee ?? 0, 0) }}</dd>
                <dt class="col-6">Discount</dt>
                <dd class="col-6">{{ number_format($course->discount ?? 0, 0) }}</dd>
                <dt class="col-6">Admission fee</dt>
                <dd class="col-6">{{ number_format($course->admission_fee ?? 0, 0) }}</dd>
                <dt class="col-6">Exam fee</dt>
                <dd class="col-6">{{ number_format($course->exam_fee ?? 0, 0) }}</dd>
                <dt class="col-6">Certificate fee</dt>
                <dd class="col-6">{{ number_format($course->certificate_fee ?? 0, 0) }}</dd>
            </dl>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-card h-100">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-card-text me-1"></i>Description</h6>
            <p class="mb-3">{!! nl2br(e($course->description ?: 'No description provided.')) !!}</p>

            <h6 class="fw-bold text-primary mb-2"><i class="bi bi-check2-square me-1"></i>Outcomes</h6>
            @if (count($outcomes))
                <ul class="mb-3 ps-3">
                    @foreach ($outcomes as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            @else
                <p class="text-muted mb-3">—</p>
            @endif

            <h6 class="fw-bold text-primary mb-2"><i class="bi bi-exclamation-circle me-1"></i>Requirements</h6>
            @if (count($requirements))
                <ul class="mb-3 ps-3">
                    @foreach ($requirements as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            @else
                <p class="text-muted mb-3">—</p>
            @endif

            <h6 class="fw-bold text-primary mb-2"><i class="bi bi-signpost me-1"></i>Prerequisites</h6>
            @if (count($prerequisites))
                <ul class="mb-0 ps-3">
                    @foreach ($prerequisites as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            @else
                <p class="text-muted mb-0">—</p>
            @endif
        </div>
    </div>
</div>

<div class="admin-card mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold text-primary mb-0">
            <i class="bi bi-collection me-1"></i>Subjects
            <span class="badge bg-secondary ms-1">{{ $course->subjects_count ?? 0 }}</span>
        </h6>
        <div class="d-flex gap-2">
            @if ($user->hasPermission('training.courses.manage'))
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                    <i class="bi bi-plus-lg me-1"></i>Add Subject
                </button>
            @endif
            <a href="{{ route('training.courses.subjects.index') }}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-list-ul me-1"></i>Manage Subjects
            </a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Subject</th>
                    <th>Code</th>
                    <th>Short name</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($course->subjects as $subject)
                    <tr>
                        <td class="text-muted">{{ $loop->iteration }}</td>
                        <td class="fw-semibold">
                            <a class="text-decoration-none" href="{{ route('training.courses.subjects.edit', $subject) }}">{{ $subject->name }}</a>
                        </td>
                        <td class="text-muted">{{ $subject->subject_code ?? '—' }}</td>
                        <td>{{ $subject->short_name ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $subject->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($subject->status) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No subjects linked to this course yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('training.courses._add_subject_modal')

<div class="admin-card mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold text-primary mb-0">
            <i class="bi bi-folder2 me-1"></i>Course Materials
            <span class="badge bg-secondary ms-1">{{ $course->materials_count ?? 0 }}</span>
        </h6>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($course->materials as $material)
                    <tr>
                        <td class="text-muted">{{ $loop->iteration }}</td>
                        <td class="fw-semibold">{{ $material->title }}</td>
                        <td class="text-muted">{{ $material->file_type ? strtoupper($material->file_type) : '—' }}</td>
                        <td>{{ $material->file_size ? number_format($material->file_size / 1024, 1) . ' KB' : '—' }}</td>
                        <td>
                            <span class="badge {{ $material->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($material->status) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No materials uploaded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

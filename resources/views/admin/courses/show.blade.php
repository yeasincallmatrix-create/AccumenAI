@extends('layouts.admin')

@section('title', $course->name . ' — AccumenAI')

@section('content')
@php
    $statusBadge = [
        'active'   => 'text-bg-success',
        'inactive' => 'text-bg-secondary',
        'draft'    => 'text-bg-warning',
    ];
@endphp

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $course->name }}
            @if ($course->is_featured)
                <span class="badge text-bg-warning ms-1">Featured</span>
            @endif
        </h4>
        <p class="page-header-desc">{{ $course->course_code }}</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="{{ $selectedInstituteId ? route('admin.courses.assignment', ['institute_id' => $selectedInstituteId]) : route('admin.courses.index') }}">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="icon" style="background:rgba(13,110,253,.1); color:var(--primary);"><i class="bi bi-journal-bookmark-fill"></i></div>
            <div class="num">{{ number_format($course->fee, 0) }}</div>
            <div class="label">Fee ({{ $course->institute->country ?? 'BD' }})</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="icon" style="background:rgba(25,135,84,.12); color:#198754;"><i class="bi bi-people-fill"></i></div>
            <div class="num">{{ $enrollments->total() }}</div>
            <div class="label">Enrolled students</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="icon" style="background:rgba(255,193,7,.15); color:#b8860b;"><i class="bi bi-collection-fill"></i></div>
            <div class="num">{{ $course->subjects->count() }}</div>
            <div class="label">Subjects</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="icon" style="background:rgba(111,66,193,.12); color:#6f42c1;"><i class="bi bi-building"></i></div>
            <div class="num">{{ $assignedInstitutes->count() }}</div>
            <div class="label">Assigned institutes</div>
        </div>
    </div>
</div>

<div class="admin-card">
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="course-tab" data-bs-toggle="tab" data-bs-target="#course-pane" type="button" role="tab" aria-controls="course-pane" aria-selected="true">
                <i class="bi bi-journal-text me-1"></i> Course
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="subjects-tab" data-bs-toggle="tab" data-bs-target="#subjects-pane" type="button" role="tab" aria-controls="subjects-pane" aria-selected="false">
                <i class="bi bi-collection me-1"></i> Subjects
                <span class="badge text-bg-primary ms-1">{{ $course->subjects->count() }}</span>
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="course-pane" role="tabpanel" aria-labelledby="course-tab">
            <dl class="row mb-4">
                <dt class="col-sm-4">Course code</dt><dd class="col-sm-8">{{ $course->course_code }}</dd>
                <dt class="col-sm-4">Category</dt><dd class="col-sm-8">{{ $course->category->name ?? '—' }}</dd>
                <dt class="col-sm-4">Sub-category</dt><dd class="col-sm-8">{{ $course->subCategory->name ?? '—' }}</dd>
                <dt class="col-sm-4">Level</dt><dd class="col-sm-8">{{ ucwords(str_replace('_', ' ', $course->level)) }}</dd>
                <dt class="col-sm-4">Status</dt>
                <dd class="col-sm-8"><span class="badge {{ $statusBadge[$course->status] ?? 'text-bg-secondary' }}">{{ $course->status }}</span></dd>
                <dt class="col-sm-4">Fee</dt><dd class="col-sm-8">{{ mawa_currency_symbol($course->institute->country ?? null) }} {{ number_format($course->fee, 0) }}</dd>
                <dt class="col-sm-4">Discount</dt><dd class="col-sm-8">{{ $course->discount ? number_format($course->discount, 0) : '—' }}</dd>
                <dt class="col-sm-4">Admission fee</dt><dd class="col-sm-8">{{ $course->admission_fee ? number_format($course->admission_fee, 0) : '—' }}</dd>
                <dt class="col-sm-4">Exam fee</dt><dd class="col-sm-8">{{ $course->exam_fee ? number_format($course->exam_fee, 0) : '—' }}</dd>
                <dt class="col-sm-4">Certificate fee</dt><dd class="col-sm-8">{{ $course->certificate_fee ? number_format($course->certificate_fee, 0) : '—' }}</dd>
                <dt class="col-sm-4">Duration</dt>
                <dd class="col-sm-8">
                    {{ $course->duration_value ? $course->duration_value . ' ' . ucwords(str_replace('_', ' ', $course->duration_type)) : '—' }}
                </dd>
                <dt class="col-sm-4">Weekly classes</dt><dd class="col-sm-8">{{ $course->weekly_classes ?? '—' }}</dd>
                <dt class="col-sm-4">Total classes</dt><dd class="col-sm-8">{{ $course->total_classes ?? '—' }}</dd>
                <dt class="col-sm-4">Class duration</dt><dd class="col-sm-8">{{ $course->class_duration_minutes ? $course->class_duration_minutes . ' min' : '—' }}</dd>
                <dt class="col-sm-4">Mode</dt><dd class="col-sm-8">{{ $course->mode ? ucwords(str_replace('_', ' ', $course->mode)) : '—' }}</dd>
                <dt class="col-sm-4">Batch capacity</dt><dd class="col-sm-8">{{ $course->batch_capacity_default ?? '—' }}</dd>
                <dt class="col-sm-4">Description</dt><dd class="col-sm-8">{{ $course->description ?: '—' }}</dd>
            </dl>

            @if ($assignedInstitutes->isNotEmpty() || $enrollments->isNotEmpty())
            <hr>
            <ul class="nav nav-tabs mb-3" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="students-tab" data-bs-toggle="tab" data-bs-target="#students-pane" type="button" role="tab" aria-controls="students-pane" aria-selected="true">
                        <i class="bi bi-people me-1"></i> Enrolled Students
                        <span class="badge text-bg-primary ms-1">{{ $enrollments->total() }}</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="institutes-tab" data-bs-toggle="tab" data-bs-target="#institutes-pane" type="button" role="tab" aria-controls="institutes-pane" aria-selected="false">
                        <i class="bi bi-building me-1"></i> Assigned Institutes
                        <span class="badge text-bg-primary ms-1">{{ $assignedInstitutes->count() }}</span>
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="students-pane" role="tabpanel" aria-labelledby="students-tab">
                    @if ($enrollments->isEmpty())
                        <p class="text-muted text-center py-4 mb-0">No students are enrolled in this course yet.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Institute</th>
                                        <th>Batch</th>
                                        <th>Roll</th>
                                        <th>Enrollment date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($enrollments as $enrollment)
                                        <tr>
                                            <td class="text-muted">{{ $enrollment->student->student_id ?? '—' }}</td>
                                            <td>
                                                <a class="fw-semibold text-decoration-none" href="{{ route('admin.students.show', $enrollment->student) }}">
                                                    {{ $enrollment->student->full_name }}
                                                </a>
                                            </td>
                                            <td>{{ $enrollment->institute->name ?? '—' }}</td>
                                            <td>{{ $enrollment->batch->name ?? '—' }}</td>
                                            <td>{{ $enrollment->roll_number ?? '—' }}</td>
                                            <td>{{ $enrollment->enrollment_date ? \Illuminate\Support\Carbon::parse($enrollment->enrollment_date)->format('d M Y') : '—' }}</td>
                                            <td><span class="badge text-bg-secondary">{{ $enrollment->status }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 d-flex flex-column align-items-center gap-2">
                            {{ $enrollments->appends(request()->query())->links('pagination::bootstrap-5') }}
                            <span class="text-muted small">{{ $enrollments->total() }} enrollments</span>
                        </div>
                    @endif
                </div>

                <div class="tab-pane fade" id="institutes-pane" role="tabpanel" aria-labelledby="institutes-tab">
                    @if ($assignedInstitutes->isEmpty())
                        <p class="text-muted text-center py-4 mb-0">No institutes are assigned this course yet.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Institute</th>
                                        <th>Country</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($assignedInstitutes as $inst)
                                        <tr>
                                            <td>
                                                <a class="fw-semibold text-decoration-none" href="{{ route('admin.institutes.show', $inst) }}">{{ $inst->name }}</a>
                                            </td>
                                            <td>{{ $inst->country ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
            @endif
        </div>

        <div class="tab-pane fade" id="subjects-pane" role="tabpanel" aria-labelledby="subjects-tab">
            @if ($course->subjects->isEmpty())
                <p class="text-muted text-center py-4 mb-0">No subjects are attached to this course yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Code</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($course->subjects as $subject)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $subject->name }}</div>
                                        @if ($subject->short_name)
                                            <div class="text-muted small">{{ $subject->short_name }}</div>
                                        @endif
                                    </td>
                                    <td class="text-muted">{{ $subject->subject_code }}</td>
                                    <td>{{ ucwords($subject->subject_type) }}</td>
                                    <td>{{ $subject->category->name ?? '—' }}</td>
                                    <td>
                                        <span class="badge {{ $statusBadge[$subject->status] ?? 'text-bg-secondary' }}">{{ $subject->status }}</span>
                                    </td>
                                    <td class="text-muted">{{ \Illuminate\Support\Str::limit($subject->description, 60) ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
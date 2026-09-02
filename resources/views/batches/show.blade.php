@extends('layouts.institute')

@section('title', $batch->name . ' — AccumenAI')

@section('content')
@php
    $statusBadge = [
        'upcoming'  => 'bg-secondary',
        'running'   => 'bg-success',
        'ongoing'   => 'bg-success',
        'completed' => 'bg-primary',
        'cancelled' => 'bg-danger',
        'archived'  => 'bg-dark',
    ];
    $enrollStatusBadge = [
        'active'      => 'bg-success',
        'completed'   => 'bg-primary',
        'dropped'     => 'bg-secondary',
        'transferred' => 'bg-info',
    ];
    $statusNames = [
        'upcoming'  => mawa_lang('status.upcoming'),
        'running'   => mawa_lang('status.running'),
        'ongoing'   => mawa_lang('status.running'),
        'completed' => mawa_lang('status.completed'),
        'cancelled' => mawa_lang('status.cancelled'),
        'archived'  => mawa_lang('status.archived'),
    ];
    // Form dropdown should show single 'ongoing' for both legacy 'running' and 'ongoing'
    $statusFormNames = collect($statusNames)->reject(fn($v,$k) => $k === 'running')->all();
    $shiftNames = [
        'morning' => mawa_lang('options.shift_morning'),
        'day'     => mawa_lang('options.shift_day'),
        'evening' => mawa_lang('options.shift_evening'),
        'weekend' => mawa_lang('options.shift_weekend'),
        'online'  => mawa_lang('options.shift_online'),
    ];
    $examStatusBadge = [
        'scheduled' => 'bg-secondary',
        'ongoing'   => 'bg-info',
        'completed' => 'bg-success',
        'cancelled' => 'bg-danger',
    ];
    $examStatusNames = [
        'scheduled' => mawa_lang('exams.schedule'),
        'ongoing'   => mawa_lang('exams.ongoing'),
        'completed' => mawa_lang('exams.completed'),
        'cancelled' => mawa_lang('exams.cancelled'),
    ];
    $fmtDate = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d M Y') : '—';
    $capacityPct = $batch->seat_capacity > 0 ? min(100, (int) round($batch->seat_filled / $batch->seat_capacity * 100)) : 0;
    $capacityBarClass = $capacityPct >= 100 ? 'bg-danger' : ($capacityPct >= 80 ? 'bg-warning' : 'bg-success');
@endphp

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
        <a href="{{ route('batches.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="bi bi-arrow-left me-1"></i>{{ mawa_e('batches.back_to_batches') }}
        </a>
        <h4 class="page-header-title">
            {{ $batch->name }}
            <span class="badge bg-dark bg-opacity-75 ms-1">{{ $batch->batch_code }}</span>
            <span class="badge {{ $statusBadge[$batch->status] ?? 'bg-secondary' }} ms-1">{{ $statusNames[$batch->status] ?? $batch->status }}</span>
        </h4>
    </div>
    @if ($user->hasPermission('courses.manage') || ($user->hasPermission('batches.manage') && $batch->status !== 'cancelled'))
        <div class="d-flex flex-wrap gap-2">
            @if ($user->hasPermission('courses.manage'))
                <button type="button" class="btn btn-primary" data-manage-course-subjects
                        data-href="{{ route('courses.subjects.sync', $batch->course_id) }}">
                    <i class="bi bi-journal-plus me-1"></i>{{ mawa_e('courses.add_subjects') }}
                </button>
            @endif
            @if ($user->hasPermission('batches.manage') && $batch->status !== 'cancelled')
                @if (in_array('running', $allowedTransitions ?? [], true))
                    <form class="d-inline" method="POST" action="{{ route('batches.status', $batch) }}">
                        @csrf
                        <input type="hidden" name="status" value="running">
                        <button class="btn btn-success" type="submit"><i class="bi bi-play-fill me-1"></i>{{ mawa_e('batches.start_batch') }}</button>
                    </form>
                @endif
                @if (in_array('completed', $allowedTransitions ?? [], true))
                    <form class="d-inline" method="POST" action="{{ route('batches.status', $batch) }}">
                        @csrf
                        <input type="hidden" name="status" value="completed">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i>{{ mawa_e('batches.complete_batch') }}</button>
                    </form>
                @endif
                @if (in_array('cancelled', $allowedTransitions ?? [], true))
                    <form class="d-inline" method="POST" action="{{ route('batches.status', $batch) }}"
                          data-ajax-delete="1" data-confirm="{{ mawa_lang('batches.confirm_cancel') }}">
                        @csrf
                        <input type="hidden" name="status" value="cancelled">
                        <button class="btn btn-outline-danger" type="submit"><i class="bi bi-x-circle me-1"></i>{{ mawa_e('batches.cancel_batch') }}</button>
                    </form>
                @endif
                <button type="button" class="btn btn-outline-primary" data-edit-batch="{{ $batch->id }}">
                    <i class="bi bi-pencil me-1"></i>{{ mawa_e('actions.edit') }}
                </button>
                @if ($batch->status === 'archived')
                    <form class="d-inline" method="POST" action="{{ route('batches.unarchive', $batch) }}"
                          data-ajax-delete="1" data-confirm="{{ mawa_lang('batches.confirm_archive') }}">
                        @csrf
                        <button class="btn btn-outline-dark" type="submit">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>{{ mawa_lang('batches.unarchived') }}
                        </button>
                    </form>
                @else
                    <form class="d-inline" method="POST" action="{{ route('batches.archive', $batch) }}"
                          data-ajax-delete="1" data-confirm="{{ mawa_lang('batches.confirm_archive') }}">
                        @csrf
                        <button class="btn btn-outline-dark" type="submit">
                            <i class="bi bi-archive me-1"></i>{{ mawa_lang('status.archived') }}
                        </button>
                    </form>
                @endif
            @endif
        </div>
    @endif
</div>

<div class="row g-3 mt-1">

    <!-- Batch details card -->
    <div class="col-lg-4">
        <div class="admin-card h-100">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-person-vcard me-1"></i>{{ mawa_e('batches.title') }}</h6>
            <dl class="row mb-0 profile-dl">
                <dt class="col-5">{{ mawa_e('batches.table_code') }}</dt>
                <dd class="col-7 fw-semibold text-primary">{{ $batch->batch_code }}</dd>
                <dt class="col-5">{{ mawa_e('batches.table_course') }}</dt>
                <dd class="col-7">{{ $batch->course?->name ?? 'Not provided' }}</dd>
                @php $isProfessionalBatch = \App\Support\InstituteDomain::isProfessional($batch->institute ? $batch->institute : \App\Models\Institute::find($batch->institute_id)); @endphp
                @if(!$isProfessionalBatch)
                <dt class="col-5">{{ mawa_e('batches.academic_year') }}</dt>
                <dd class="col-7">{{ $batch->academicYear?->name ?? 'Not assigned' }}</dd>
                @else
                <dt class="col-5">Session</dt>
                <dd class="col-7">{{ $batch->academicYear?->name ?? '—' }}</dd>
                @endif
                <dt class="col-5">{{ mawa_e('batches.branch') }}</dt>
                <dd class="col-7">{{ $batch->branch?->name ?? 'Not provided' }}</dd>
                <dt class="col-5">{{ mawa_e('batches.teacher') }}</dt>
                <dd class="col-7">{{ $batch->teacher?->user?->name ?? 'Not assigned' }}</dd>
                <dt class="col-5">{{ mawa_e('batches.room') }}</dt>
                <dd class="col-7">{{ $batch->room?->name ?? 'Not assigned' }}</dd>
            </dl>

            @if (($instructors ?? collect())->isNotEmpty())
                <hr class="my-3">
                <h6 class="fw-bold text-primary mb-2"><i class="bi bi-person-video3 me-1"></i>{{ mawa_e('batches.instructors') }}</h6>
                <ul class="list-unstyled mb-0">
                    @foreach ($instructors as $assignment)
                        <li class="d-flex align-items-center gap-2 py-1">
                            <i class="bi bi-person-circle text-muted"></i>
                            <span>{{ trim(($assignment->teacher->first_name ?? '').' '.($assignment->teacher->last_name ?? '')) ?: 'Teacher' }}</span>
                            @if ($assignment->responsibility)
                                <span class="badge text-bg-light border ms-auto">{{ str_replace('_', ' ', ucfirst($assignment->responsibility)) }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-muted small mb-0 mt-2">{{ mawa_e('batches.no_instructors') }}</p>
            @endif
        </div>
    </div>

    <!-- Schedule card -->
    <div class="col-lg-4">
        <div class="admin-card h-100">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-calendar3 me-1"></i>{{ mawa_e('batches.shift') }}</h6>
            <dl class="row mb-0 profile-dl">
                <dt class="col-5">{{ mawa_e('batches.shift') }}</dt>
                <dd class="col-7">{{ $shiftNames[$batch->shift] ?? $batch->shift }}</dd>
                <dt class="col-5">{{ mawa_e('batches.start_date') }}</dt>
                <dd class="col-7">{{ $batch->start_date ? \Illuminate\Support\Carbon::parse($batch->start_date)->format('d M Y') : 'Not provided' }}</dd>
                <dt class="col-5">{{ mawa_e('batches.end_date') }}</dt>
                <dd class="col-7">{{ $batch->end_date ? \Illuminate\Support\Carbon::parse($batch->end_date)->format('d M Y') : 'Not provided' }}</dd>
                <dt class="col-5">{{ mawa_e('batches.table_seats') }}</dt>
                <dd class="col-7"><span class="fw-semibold text-primary">{{ $batch->seat_filled }}</span> / {{ $batch->seat_capacity }}</dd>
                <dd class="col-12">
                    <div class="progress" style="height:8px" title="{{ $batch->seat_filled }}/{{ $batch->seat_capacity }} {{ mawa_e('batches.filled') }}">
                        <div class="progress-bar {{ $capacityBarClass }}" role="progressbar" style="width: {{ $capacityPct }}%" aria-valuenow="{{ $capacityPct }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </dd>
                <dt class="col-5">{{ mawa_e('batches.available_seats') }}</dt>
                <dd class="col-7 fw-semibold text-success">{{ $availableSeats }}</dd>
                <dt class="col-5">{{ mawa_e('batches.status') }}</dt>
                <dd class="col-7">
                    <span class="badge {{ $statusBadge[$batch->status] ?? 'bg-secondary' }}">{{ $statusNames[$batch->status] ?? $batch->status }}</span>
                </dd>
            </dl>
        </div>
    </div>

    <!-- Stats card -->
    <div class="col-lg-4">
        <div class="admin-card h-100">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-mortarboard me-1"></i>{{ mawa_e('batches.enrollments') }}</h6>
            <div class="row g-3 text-center">
                <div class="col-6">
                    <div class="border rounded p-3">
                        <div class="fs-3 fw-bold text-primary">{{ $enrollments->count() }}</div>
                        <div class="text-muted small">{{ mawa_e('batches.student') }}</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="border rounded p-3">
                        <div class="fs-3 fw-bold text-success">{{ max(0, $batch->seat_capacity - $batch->seat_filled) }}</div>
                        <div class="text-muted small">{{ mawa_e('batches.seats_of') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Batch sections: Students / Exams -->
<div class="mt-4">
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-students-tab" data-bs-toggle="tab" data-bs-target="#tab-students" type="button" role="tab" aria-controls="tab-students" aria-selected="true">
                <i class="bi bi-people me-1"></i>{{ mawa_e('batches.enrollments') }}
                <span class="badge bg-secondary ms-1">{{ $enrollments->count() }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-exams-tab" data-bs-toggle="tab" data-bs-target="#tab-exams" type="button" role="tab" aria-controls="tab-exams" aria-selected="false">
                <i class="bi bi-clipboard-check me-1"></i>{{ mawa_e('exams.heading') }}
                <span class="badge bg-secondary ms-1">{{ $exams->count() }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-subjects-tab" data-bs-toggle="tab" data-bs-target="#tab-subjects" type="button" role="tab" aria-controls="tab-subjects" aria-selected="false">
                <i class="bi bi-journal-text me-1"></i>{{ mawa_e('exams.subjects') }}
                <span class="badge bg-secondary ms-1">{{ $batch->course?->subjects?->count() ?? 0 }}</span>
            </button>
        </li>
        @if (optional(auth('institute_user')->user())->hasPermission('documents.view'))
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-documents-tab" data-bs-toggle="tab" data-bs-target="#tab-documents" type="button" role="tab" aria-controls="tab-documents" aria-selected="false">
                    <i class="bi bi-folder2-open me-1"></i>Documents
                </button>
            </li>
        @endif
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-students" role="tabpanel" aria-labelledby="tab-students-tab">
            <div class="admin-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-primary mb-0">
                    <i class="bi bi-people me-1"></i>{{ mawa_e('batches.enrollments') }}
                    <span class="badge bg-secondary ms-1">{{ $enrollments->count() }}</span>
                </h6>
                <a href="{{ route('training.enrollments.create', ['batch_id' => $batch->id]) }}" class="btn btn-primary btn-sm"><i class="bi bi-person-plus me-1"></i> Enroll Trainee</a>
            </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                        <th>{{ mawa_e('batches.roll_number') }}</th>
                        <th>{{ mawa_e('batches.student') }}</th>
                        <th>{{ mawa_e('batches.student_id_number') }}</th>
                        <th>{{ mawa_e('batches.branch') }}</th>
                        <th>{{ mawa_e('batches.phone') }}</th>
                        <th>{{ mawa_e('batches.enrollment_date') }}</th>
                        <th>{{ mawa_e('batches.status') }}</th>
                        @if ($user->hasPermission('batches.manage'))
                            <th class="text-end">{{ mawa_e('actions.actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($enrollments as $enrollment)
                        <tr>
                            <td class="text-muted">{{ $loop->iteration }}</td>
                            <td class="fw-semibold">{{ $enrollment->roll_number ?: '—' }}</td>
                            <td>
                                <a class="fw-semibold text-decoration-none" href="{{ route('students.show', $enrollment->student_id) }}">
                                    {{ $enrollment->student->full_name }}
                                </a>
                            </td>
                            <td>{{ $enrollment->student->student_id ?? '—' }}</td>
                            <td>{{ $enrollment->student->branch?->name ?? '—' }}</td>
                            <td>{{ $enrollment->student->phone ?? '—' }}</td>
                            <td>{{ $enrollment->enrollment_date ? \Illuminate\Support\Carbon::parse($enrollment->enrollment_date)->format('d M Y') : '—' }}</td>
                            <td>
                                <span class="badge {{ $enrollStatusBadge[$enrollment->status] ?? 'bg-secondary' }}">{{ ucfirst($enrollment->status) }}</span>
                            </td>
                            @if ($user->hasPermission('batches.manage'))
                                <td class="text-end">
                                    @if ($enrollment->status === 'active')
                                        <div class="d-inline-flex gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-transfer-student="{{ $enrollment->student_id }}" data-student-name="{{ $enrollment->student->full_name }}" title="{{ mawa_e('batches.transfer') }}">
                                                <i class="bi bi-arrow-left-right"></i>
                                            </button>
                                            <form class="d-inline" method="POST" action="{{ route('batches.remove-student', [$batch, $enrollment->student_id]) }}"
                                                  data-ajax-delete="1" data-confirm="{{ mawa_lang('batches.remove_confirm') }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" type="submit" title="{{ mawa_e('batches.remove') }}">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $user->hasPermission('batches.manage') ? 9 : 8 }}" class="text-center text-muted py-4">{{ mawa_e('batches.enrolled_empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-exams" role="tabpanel" aria-labelledby="tab-exams-tab">
        <div class="admin-card">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h6 class="fw-bold text-primary mb-0">
                    <i class="bi bi-clipboard-check me-1"></i>{{ mawa_e('exams.heading') }}
                    <span class="badge bg-secondary ms-1">{{ $exams->count() }}</span>
                </h6>
                @if ($user->hasPermission('exams.manage') && $batch->status !== 'cancelled')
                    <button type="button" class="btn btn-primary btn-sm" data-send-exam
                            data-batch-id="{{ $batch->id }}" data-batch-name="{{ $batch->name }}"
                            data-href="{{ route('exams.send-to-exam', $batch) }}">
                        <i class="bi bi-plus-circle me-1"></i>{{ mawa_e('exams.create_exam') }}
                    </button>
                @endif
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ mawa_e('exams.table_title') }}</th>
                            <th>{{ mawa_e('exams.exam_date') }}</th>
                            <th>{{ mawa_e('exams.table_marks') }}</th>
                            <th>{{ mawa_e('exams.table_students') }}</th>
                            <th>{{ mawa_e('batches.table_status') }}</th>
                            <th class="text-end">{{ mawa_e('actions.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($exams as $exam)
                            <tr>
                                <td class="text-muted">{{ $loop->iteration }}</td>
                                <td class="fw-semibold">
                                    <a class="fw-semibold text-decoration-none" href="{{ route('exams.show', $exam) }}">{{ $exam->title }}</a>
                                </td>
                                <td>{{ $exam->exam_date ? \Illuminate\Support\Carbon::parse($exam->exam_date)->format('d M Y, h:i A') : '—' }}</td>
                                <td>{{ rtrim(rtrim(number_format($exam->full_marks, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($exam->pass_marks, 2), '0'), '.') }}</td>
                                <td><span class="badge bg-secondary">{{ $exam->results_count }}</span></td>
                                <td>
                                    <span class="badge {{ $examStatusBadge[$exam->status] ?? 'bg-secondary' }}">{{ $examStatusNames[$exam->status] ?? $exam->status }}</span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('exams.show', $exam) }}" class="btn btn-sm btn-outline-primary" title="{{ mawa_e('exams.marks_entry') }}">
                                        <i class="bi bi-clipboard-check"></i>
                                    </a>
                                    @if ($exam->status !== 'cancelled')
                                        <form class="d-inline" method="POST" action="{{ route('exams.destroy', $exam) }}"
                                              data-ajax-delete="1" data-confirm="{{ mawa_lang('exams.cancel_confirm') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit" title="{{ mawa_e('exams.cancelled') }}">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">{{ mawa_e('exams.empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-subjects" role="tabpanel" aria-labelledby="tab-subjects-tab">
        <div class="admin-card">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h6 class="fw-bold text-primary mb-0">
                    <i class="bi bi-journal-text me-1"></i>{{ mawa_e('exams.subjects') }}
                    <span class="badge bg-secondary ms-1">{{ $batch->course?->subjects?->count() ?? 0 }}</span>
                </h6>
                @if ($user->hasPermission('courses.manage'))
                    <button type="button" class="btn btn-primary btn-sm" data-manage-course-subjects
                            data-href="{{ route('courses.subjects.sync', $batch->course_id) }}">
                        <i class="bi bi-plus-circle me-1"></i>{{ mawa_e('courses.add_subjects') }}
                    </button>
                @endif
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ mawa_e('classes.table_subject') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($batch->course?->subjects ?? [] as $subject)
                            <tr>
                                <td class="text-muted">{{ $loop->iteration }}</td>
                                <td class="fw-semibold">{{ $subject->name }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-center text-muted py-4">{{ mawa_e('courses.subjects_empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if (optional(auth('institute_user')->user())->hasPermission('documents.view'))
            <div class="tab-pane fade" id="tab-documents" role="tabpanel" aria-labelledby="tab-documents-tab">
                <div class="admin-card">
                    @include('documents._panel', ['entityType' => 'batch', 'entityId' => $batch->id])
                </div>
            </div>
        @endif
    </div>
</div>

@if ($user->hasPermission('exams.manage'))
    @include('exams._send_modal', ['sendExamSubjects' => $sendExamSubjects ?? []])
@endif

@include('courses._subject_modal', [
    'subjectCourse' => $batch->course,
    'subjectOptions' => $subjectOptions ?? [],
    'attachedSubjectIds' => $attachedSubjectIds ?? [],
])

@if ($user->hasPermission('batches.manage'))
    {{-- Edit Batch modal --}}
    <div class="modal fade" id="batchModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form class="modal-content" method="POST" action="{{ route('batches.store') }}" id="batchForm" data-ajax-enabled>
                @csrf
                <input type="hidden" name="_method" id="b_method" value="">
                <input type="hidden" name="batch_id" id="b_batch_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="b_modal_title">{{ mawa_e('batches.edit') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="e_name">{{ mawa_e('batches.name') }} *</label>
                            <input type="text" id="e_name" name="name" class="form-control" maxlength="120" required>
                            @error('name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="e_batch_code">{{ mawa_e('batches.table_code') }}</label>
                            <input type="text" id="e_batch_code" class="form-control bg-light" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="e_course_id">{{ mawa_e('batches.course') }} *</label>
                            <select id="e_course_id" name="course_id" class="form-select" required>
                                <option value="">{{ mawa_e('batches.select') }}</option>
                                @foreach(($courses ?? []) as $courseOpt)
                                    <option value="{{ $courseOpt->id }}">{{ $courseOpt->name }}</option>
                                @endforeach
                            </select>
                            @error('course_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        @if(\App\Support\InstituteDomain::isAcademic($institute ?? null))
                        <div class="col-md-4">
                            <label class="form-label" for="e_academic_year_id">{{ mawa_e('batches.academic_year') }}</label>
                            <select id="e_academic_year_id" name="academic_year_id" class="form-select">
                                <option value="">{{ mawa_e('batches.select') }}</option>
                                @foreach ($academicYears ?? [] as $year)
                                    <option value="{{ $year->id }}">{{ $year->name }}</option>
                                @endforeach
                            </select>
                            @error('academic_year_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        @endif
                        <div class="col-md-4">
                            <label class="form-label" for="e_shift">{{ mawa_e('batches.shift') }}</label>
                            <select id="e_shift" name="shift" class="form-select">
                                @foreach ($shiftNames as $slug => $label)
                                    <option value="{{ $slug }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('shift') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="e_status">{{ mawa_e('batches.status') }}</label>
                            <select id="e_status" name="status" class="form-select">
                                @foreach ($statusFormNames as $slug => $label)
                                    <option value="{{ $slug }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="form-text small text-muted">Changing status does not affect existing exam results or certificates.</div>
                            @error('status') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="e_start_date">{{ mawa_e('batches.start_date') }} *</label>
                            <input type="date" id="e_start_date" name="start_date" class="form-control" required>
                            @error('start_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="e_end_date">{{ mawa_e('batches.end_date') }}</label>
                            <input type="date" id="e_end_date" name="end_date" class="form-control">
                            @error('end_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="e_seat_capacity">{{ mawa_e('batches.seat_capacity') }}</label>
                            <input type="number" id="e_seat_capacity" name="seat_capacity" class="form-control" min="1" max="10000">
                            @error('seat_capacity') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label for="e_attendance_threshold" class="form-label">Attendance Threshold (%)</label>
                            <input type="number" id="e_attendance_threshold" name="attendance_threshold" class="form-control form-control-sm" min="0" max="100" value="{{ old('attendance_threshold', $batch->attendance_threshold ?? 80) }}" required>
                            <small class="form-text text-muted">Minimum attendance % required for certificate eligibility.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="e_teacher_id">Trainer</label>
                            <select id="e_teacher_id" name="teacher_id" class="form-select">
                                <option value="">— Select trainer —</option>
                                @foreach(($instructors ?? []) as $ins)
                                    <option value="{{ $ins->id }}">{{ $ins->first_name }} {{ $ins->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ mawa_e('actions.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ mawa_e('actions.save') }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Transfer Student modal --}}
    <div class="modal fade" id="transferModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="POST" action="{{ route('batches.transfer', $batch) }}" id="transferForm" data-ajax-enabled>
                @csrf
                <input type="hidden" name="student_id" id="t_student_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title">{{ mawa_e('batches.transfer_to') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small" id="t_student_name"></p>
                    <div>
                        <label class="form-label" for="t_target_batch_id">{{ mawa_e('batches.transfer_to') }} *</label>
                        <select id="t_target_batch_id" name="target_batch_id" class="form-select" required>
                            <option value="">{{ mawa_e('batches.select') }}</option>
                            @foreach ($transferTargets as $target)
                                <option value="{{ $target->id }}">{{ $target->name }} ({{ $target->batch_code }})</option>
                            @endforeach
                        </select>
                        @error('target_batch_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    @if ($transferTargets->isEmpty())
                        <div class="alert alert-warning small mt-3 mb-0">{{ mawa_e('batches.no_transfer_target') }}</div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ mawa_e('actions.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ mawa_e('batches.transfer') }}</button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    var KEY = 'batchTab-' + @json($batch->id);
    document.querySelectorAll('#tab-students-tab, #tab-exams-tab, #tab-subjects-tab').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            localStorage.setItem(KEY, tab.getAttribute('data-bs-target'));
        });
    });
    var saved = localStorage.getItem(KEY);
    if (saved) {
        var anchor = document.querySelector('#tab-students-tab[data-bs-target="' + saved + '"]');
        if (anchor) {
            var target = document.querySelector(saved);
            if (target) {
                document.querySelectorAll('.tab-pane').forEach(function (p) { p.classList.remove('show', 'active'); });
                document.querySelectorAll('.nav-link').forEach(function (n) { n.classList.remove('active'); });
                anchor.classList.add('active');
                anchor.setAttribute('aria-selected', 'true');
                target.classList.add('show', 'active');
            }
        }
    }
})();
</script>
@php
    $batchData = [
        'id' => $batch->id,
        'name' => $batch->name,
        'batch_code' => $batch->batch_code,
        'course_id' => $batch->course_id,
        'academic_year_id' => $batch->academic_year_id,
        'teacher_id' => $batch->teacher_id,
        'shift' => $batch->shift,
        'status' => $batch->status,
        'start_date' => $batch->start_date ? \Illuminate\Support\Carbon::parse($batch->start_date)->format('Y-m-d') : null,
        'end_date' => $batch->end_date ? \Illuminate\Support\Carbon::parse($batch->end_date)->format('Y-m-d') : null,
        'seat_capacity' => $batch->seat_capacity,
        'attendance_threshold' => $batch->attendance_threshold ?? 80,
        'courses' => $courses->map(fn ($course) => ['id' => $course->id, 'name' => $course->name])->values()->all(),
        'academicYears' => ($academicYears ?? collect())->map(fn ($year) => ['id' => $year->id, 'name' => $year->name])->values()->all(),
    ];
@endphp
<script>
(function () {
    var modalEl = document.getElementById('batchModal');
    var form = document.getElementById('batchForm');
    if (!modalEl || !form || !window.Monetix || !Monetix.request) { return; }

    var B = @json($batchData);

    function setVal(id, val) {
        var el = document.getElementById(id);
        if (el && val !== undefined && val !== null) { el.value = val; }
    }

    // Auto-generate batch name as "[Course Name] | batch [UserInput]"
    (function() {
        var courseSelect = document.getElementById('e_course_id');
        var nameInput = document.getElementById('e_name');
        if (courseSelect && nameInput) {
            courseSelect.addEventListener('change', function() {
                if (!this.value) return;
                var courseName = (this.options[this.selectedIndex]?.text || '').trim();
                if (!courseName) return;
                var currentName = nameInput.value || '';
                if (!currentName.startsWith(courseName)) {
                    nameInput.value = courseName + ' | batch ';
                    nameInput.focus();
                    try { nameInput.setSelectionRange(nameInput.value.length, nameInput.value.length); } catch(e) {}
                }
            });
        }
    })();

    function fillForm(formEl) {
        formEl.action = @json(route('batches.update', $batch));
        setVal('b_method', 'PUT');
        setVal('b_batch_id', B.id);
        setVal('e_name', B.name);
        setVal('e_batch_code', B.batch_code);
        var courseSelect = document.getElementById('e_course_id');
        courseSelect.innerHTML = '<option value="">{{ mawa_lang('batches.select') }}</option>'
            + (B.courses || []).map(function (c) {
                return '<option value="' + c.id + '"' + (c.id == B.course_id ? ' selected' : '') + '>' + c.name + '</option>';
            }).join('');
        var yearSelect = document.getElementById('e_academic_year_id');
        if (yearSelect) {
            yearSelect.innerHTML = '<option value="">{{ mawa_lang('batches.select') }}</option>'
                + (B.academicYears || []).map(function (y) {
                    return '<option value="' + y.id + '"' + (y.id == B.academic_year_id ? ' selected' : '') + '>' + y.name + '</option>';
                }).join('');
        }
        setVal('e_teacher_id', B.teacher_id);
        setVal('e_shift', B.shift);
        // Backwards compatibility: map legacy 'running' to 'ongoing'
        setVal('e_status', B.status === 'running' ? 'ongoing' : B.status);
        setVal('e_start_date', B.start_date || '');
        setVal('e_end_date', B.end_date || '');
        setVal('e_seat_capacity', B.seat_capacity);
        setVal('e_attendance_threshold', B.attendance_threshold ?? 80);
    }

    if (window.Monetix && Monetix.delegate) {
        Monetix.delegate('click', '[data-edit-batch]', function (e, btn) {
            var currentModal = document.getElementById('batchModal');
            var currentForm = document.getElementById('batchForm');
            if (!currentModal || !currentForm) { return; }
            fillForm(currentForm);
            bootstrap.Modal.getOrCreateInstance(currentModal).show();
        }, 'mtx-batch-edit');
    }

    function clearErrors() {
        form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        form.querySelectorAll('.text-danger.small').forEach(function (el) { el.remove(); });
        var errBox = document.getElementById('batchFormErrors');
        if (errBox) { errBox.remove(); }
    }

    function showErrors(errors) {
        clearErrors();
        Object.keys(errors || {}).forEach(function (key) {
            var field = form.querySelector('[name="' + key + '"]');
            if (field) {
                field.classList.add('is-invalid');
                var msg = document.createElement('div');
                msg.className = 'text-danger small mt-1';
                msg.textContent = (errors[key] || []).join(', ');
                field.parentNode.insertBefore(msg, field.nextSibling);
            }
        });
    }

    form.addEventListener('submit', function (e) {
        if (!form.hasAttribute('data-ajax-enabled')) { return; }
        e.preventDefault();
        clearErrors();
        var submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) { submitBtn.disabled = true; }
        var methodInput = form.querySelector('input[name="_method"]');
        var method = (methodInput && methodInput.value) ? methodInput.value : 'POST';
        Monetix.request(form.action, {
            method: method,
            body: new FormData(form),
        }).then(function (res) {
            if (submitBtn) { submitBtn.disabled = false; }
            if (res && res.errors) { showErrors(res.errors); return; }
            if (res && res.success === false) {
                if (Monetix.toast) { Monetix.toast(res.message || '{{ mawa_lang('batches.save_failed') }}', 'danger'); }
                return;
            }
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) { modal.hide(); }
            if (Monetix.toast) { Monetix.toast(res && res.message, 'success'); }
            if (Monetix.loadPage) { Monetix.loadPage(window.location.pathname + window.location.search, { preserveFocus: false }); }
        }).catch(function () {
            if (submitBtn) { submitBtn.disabled = false; }
            if (Monetix.toast) { Monetix.toast('{{ mawa_lang('batches.save_failed') }}', 'danger'); }
        });
    });
})();
</script>

<script>
(function () {
    var modalEl = document.getElementById('transferModal');
    var form = document.getElementById('transferForm');
    if (!modalEl || !form || !window.Monetix || !Monetix.request) { return; }

    if (window.Monetix && Monetix.delegate) {
        Monetix.delegate('click', '[data-transfer-student]', function (e, btn) {
            var currentModal = document.getElementById('transferModal');
            if (!currentModal) { return; }
            var sid = btn.getAttribute('data-transfer-student');
            var name = btn.getAttribute('data-student-name') || '';
            document.getElementById('t_student_id').value = sid;
            var nameEl = document.getElementById('t_student_name');
            if (nameEl) { nameEl.textContent = name; }
            var select = document.getElementById('t_target_batch_id');
            if (select) { select.value = ''; }
            bootstrap.Modal.getOrCreateInstance(currentModal).show();
        }, 'mtx-transfer-student');
    }

    form.addEventListener('submit', function (e) {
        if (!form.hasAttribute('data-ajax-enabled')) { return; }
        e.preventDefault();
        var submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) { submitBtn.disabled = true; }
        Monetix.request(form.action, {
            method: 'POST',
            body: new FormData(form),
        }).then(function (res) {
            if (submitBtn) { submitBtn.disabled = false; }
            if (res && res.errors) {
                Object.keys(res.errors).forEach(function (key) {
                    var field = form.querySelector('[name="' + key + '"]');
                    if (field) {
                        field.classList.add('is-invalid');
                        var msg = document.createElement('div');
                        msg.className = 'text-danger small mt-1';
                        msg.textContent = (res.errors[key] || []).join(', ');
                        field.parentNode.insertBefore(msg, field.nextSibling);
                    }
                });
                return;
            }
            if (res && res.success === false) {
                if (Monetix.toast) { Monetix.toast(res.message || '{{ mawa_lang('batches.save_failed') }}', 'danger'); }
                return;
            }
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) { modal.hide(); }
            if (Monetix.toast) { Monetix.toast(res && res.message, 'success'); }
            if (Monetix.loadPage) { Monetix.loadPage(window.location.pathname + window.location.search, { preserveFocus: false }); }
        }).catch(function () {
            if (submitBtn) { submitBtn.disabled = false; }
            if (Monetix.toast) { Monetix.toast('{{ mawa_lang('batches.save_failed') }}', 'danger'); }
        });
    });
})();
</script>
@endpush

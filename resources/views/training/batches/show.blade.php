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
    };
    $statusNames = [
        'upcoming'  => 'Upcoming',
        'running'   => 'Running',
        'ongoing'   => 'Ongoing',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'archived'  => 'Archived',
    ];
    $examStatusBadge = [
        'scheduled' => 'bg-secondary',
        'ongoing'   => 'bg-info',
        'completed' => 'bg-success',
        'cancelled' => 'bg-danger',
    ];
    $capacityPct = ($batch->seat_capacity ?? 0) > 0 ? min(100, (int) round(($batch->seat_filled ?? 0) / $batch->seat_capacity * 100)) : 0;
    $capacityBarClass = $capacityPct >= 100 ? 'bg-danger' : ($capacityPct >= 80 ? 'bg-warning' : 'bg-success');
    $enrollments = $batch->enrollments;
@endphp

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
        <a href="{{ route('training.batches.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="bi bi-arrow-left me-1"></i>Back to Batches
        </a>
        <h4 class="page-header-title">
            {{ $batch->name }}
            @if ($batch->batch_code)
                <span class="badge bg-dark bg-opacity-75 ms-1">{{ $batch->batch_code }}</span>
            @endif
            <span class="badge {{ $statusBadge[$batch->status] ?? 'bg-secondary' }} ms-1">{{ $statusNames[$batch->status] ?? $batch->status }}</span>
        </h4>
    </div>
    @if ($user->hasPermission('batches.manage') ?? true)
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('training.batches.edit', $batch->id) }}" class="btn btn-outline-primary">
                <i class="bi bi-pencil me-1"></i>Edit
            </a>
        </div>
    @endif
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-4">
        <div class="admin-card h-100">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-person-vcard me-1"></i>Batch Details</h6>
            <dl class="row mb-0 profile-dl">
                <dt class="col-5">Code</dt>
                <dd class="col-7 fw-semibold text-primary">{{ $batch->batch_code ?? '—' }}</dd>
                <dt class="col-5">Status</dt>
                <dd class="col-7">
                    <span class="badge {{ $statusBadge[$batch->status] ?? 'bg-secondary' }}">{{ $statusNames[$batch->status] ?? $batch->status }}</span>
                </dd>
                <dt class="col-5">Shift</dt>
                <dd class="col-7">{{ ucfirst($batch->shift ?? '—') }}</dd>
            </dl>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-card h-100">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-calendar3 me-1"></i>Schedule</h6>
            <dl class="row mb-0 profile-dl">
                <dt class="col-5">Start Date</dt>
                <dd class="col-7"><x-tdate :value="$batch->start_date" fallback="d M Y" empty="Not provided" /></dd>
                <dt class="col-5">End Date</dt>
                <dd class="col-7"><x-tdate :value="$batch->end_date" fallback="d M Y" empty="Not provided" /></dd>
                <dt class="col-5">Seats</dt>
                <dd class="col-7"><span class="fw-semibold text-primary">{{ $batch->seat_filled ?? 0 }}</span> / {{ $batch->seat_capacity ?? '—' }}</dd>
                <dd class="col-12">
                    <div class="progress" style="height:8px">
                        <div class="progress-bar {{ $capacityBarClass }}" role="progressbar" style="width: {{ $capacityPct }}%" aria-valuenow="{{ $capacityPct }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </dd>
            </dl>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-card h-100">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-mortarboard me-1"></i>Enrollments</h6>
            <div class="row g-3 text-center">
                <div class="col-6">
                    <div class="border rounded p-3">
                        <div class="fs-3 fw-bold text-primary">{{ $enrollments->count() }}</div>
                        <div class="text-muted small">Trainees</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="border rounded p-3">
                        <div class="fs-3 fw-bold text-success">{{ $availableSeats ?? max(0, ($batch->seat_capacity ?? 0) - ($batch->seat_filled ?? 0)) }}</div>
                        <div class="text-muted small">Seats Left</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mt-4">
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-students-tab" data-bs-toggle="tab" data-bs-target="#tab-students" type="button" role="tab" aria-controls="tab-students" aria-selected="true">
                <i class="bi bi-people me-1"></i>Enrollments
                <span class="badge bg-secondary ms-1">{{ $enrollments->count() }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-exams-tab" data-bs-toggle="tab" data-bs-target="#tab-exams" type="button" role="tab" aria-controls="tab-exams" aria-selected="false">
                <i class="bi bi-clipboard-check me-1"></i>Exams
                <span class="badge bg-secondary ms-1">{{ ($exams ?? collect())->count() }}</span>
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-students" role="tabpanel" aria-labelledby="tab-students-tab">
            <div class="admin-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-primary mb-0">
                        <i class="bi bi-people me-1"></i>Enrollments
                        <span class="badge bg-secondary ms-1">{{ $enrollments->count() }}</span>
                    </h6>
                    <a href="{{ route('training.enrollments.create', ['batch_id' => $batch->id]) }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-person-plus me-1"></i>Enroll Trainee
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Roll</th>
                                <th>Trainee</th>
                                <th>Enrollment Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($enrollments as $enrollment)
                                <tr>
                                    <td class="text-muted">{{ $loop->iteration }}</td>
                                    <td class="fw-semibold">{{ $enrollment->roll_no ?: '—' }}</td>
                                    <td>
                                        <a class="fw-semibold text-decoration-none" href="{{ route('training.students.show', $enrollment->student_id) }}">
                                            {{ $enrollment->student?->full_name ?? 'Trainee #' . $enrollment->student_id }}
                                        </a>
                                    </td>
                                    <td><x-tdate :value="$enrollment->enrollment_date" fallback="d M Y" empty="—" /></td>
                                    <td>
                                        <span class="badge {{ $enrollStatusBadge[$enrollment->status] ?? 'bg-secondary' }}">{{ ucfirst($enrollment->status) }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No trainees enrolled yet.</td>
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
                        <i class="bi bi-clipboard-check me-1"></i>Exams
                        <span class="badge bg-secondary ms-1">{{ ($exams ?? collect())->count() }}</span>
                    </h6>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Title</th>
                                <th>Exam Date</th>
                                <th>Marks</th>
                                <th>Results</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse (($exams ?? collect()) as $exam)
                                <tr>
                                    <td class="text-muted">{{ $loop->iteration }}</td>
                                    <td class="fw-semibold">
                                        <a class="text-decoration-none" href="{{ route('training.exams.show', $exam->id) }}">{{ $exam->title }}</a>
                                    </td>
                                    <td><x-tdate :value="$exam->exam_date" fallback="d M Y" empty="—" /></td>
                                    <td>{{ rtrim(rtrim(number_format($exam->full_marks ?? 0, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($exam->pass_marks ?? 0, 2), '0'), '.') }}</td>
                                    <td><span class="badge bg-secondary">{{ $exam->results_count ?? 0 }}</span></td>
                                    <td>
                                        <span class="badge {{ $examStatusBadge[$exam->status] ?? 'bg-secondary' }}">{{ ucfirst($exam->status ?? '—') }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('training.exams.show', $exam->id) }}" class="btn btn-sm btn-outline-primary">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No exams for this batch yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

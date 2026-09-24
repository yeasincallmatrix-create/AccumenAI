@extends('layouts.institute')

@section('title', $exam->title . ' — AccumenAI')

@section('content')
@php
    $statusBadge = [
        'scheduled' => 'bg-secondary',
        'ongoing'   => 'bg-info',
        'completed' => 'bg-success',
        'cancelled' => 'bg-danger',
    ];
    $statusNames = [
        'scheduled' => 'Scheduled',
        'ongoing'   => 'Ongoing',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];
    $resultBadge = [
        'pass'   => 'text-success',
        'fail'   => 'text-danger',
        'absent' => 'text-muted',
    ];
    $full = rtrim(rtrim(number_format($exam->full_marks ?? 0, 2), '0'), '.');
    $pass = rtrim(rtrim(number_format($exam->pass_marks ?? 0, 2), '0'), '.');
    $results = $exam->results;
@endphp

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
        <a href="{{ route('training.exams.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="bi bi-arrow-left me-1"></i>Exams
        </a>
        <h4 class="page-header-title">
            {{ $exam->title }}
            <span class="badge {{ $statusBadge[$exam->status] ?? 'bg-secondary' }} ms-1">{{ $statusNames[$exam->status] ?? $exam->status }}</span>
        </h4>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-4">
        <div class="admin-card h-100">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-clipboard-check me-1"></i>Exam Details</h6>
            <dl class="row mb-0 profile-dl">
                <dt class="col-5">Batch</dt>
                <dd class="col-7 fw-semibold">
                    @if ($exam->batch)
                        <a class="text-decoration-none" href="{{ route('training.batches.show', $exam->batch_id) }}">{{ $exam->batch->name }}</a>
                    @else
                        —
                    @endif
                </dd>
                <dt class="col-5">Exam Date</dt>
                <dd class="col-7"><x-tdate :value="$exam->exam_date" fallback="d M Y" empty="—" /></dd>
                <dt class="col-5">Full Marks</dt>
                <dd class="col-7">{{ $full }}</dd>
                <dt class="col-5">Pass Marks</dt>
                <dd class="col-7">{{ $pass }}</dd>
                <dt class="col-5">Status</dt>
                <dd class="col-7">
                    <span class="badge {{ $statusBadge[$exam->status] ?? 'bg-secondary' }}">{{ $statusNames[$exam->status] ?? $exam->status }}</span>
                </dd>
            </dl>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-card h-100">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-pie-chart me-1"></i>Weight Distribution</h6>
            <dl class="row mb-0 profile-dl">
                <dt class="col-7">Written %</dt>
                <dd class="col-5">{{ $exam->written_percent ?? 0 }}%</dd>
                <dt class="col-7">Practical %</dt>
                <dd class="col-5">{{ $exam->practical_percent ?? 0 }}%</dd>
                <dt class="col-7">Viva %</dt>
                <dd class="col-5">{{ $exam->viva_percent ?? 0 }}%</dd>
            </dl>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-card h-100">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-mortarboard me-1"></i>Results Summary</h6>
            <div class="row g-3 text-center">
                <div class="col-4">
                    <div class="border rounded p-3">
                        <div class="fs-3 fw-bold text-primary">{{ $results->count() }}</div>
                        <div class="text-muted small">Results</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="border rounded p-3">
                        <div class="fs-3 fw-bold text-success">{{ $results->where('result_status', 'pass')->count() }}</div>
                        <div class="text-muted small">Pass</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="border rounded p-3">
                        <div class="fs-3 fw-bold text-danger">{{ $results->where('result_status', 'fail')->count() }}</div>
                        <div class="text-muted small">Fail</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mt-4">
    <div class="admin-card">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h6 class="fw-bold text-primary mb-0">
                <i class="bi bi-pencil-square me-1"></i>Results
                <span class="badge bg-secondary ms-1">{{ $results->count() }}</span>
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Trainee</th>
                        <th class="text-center">Marks</th>
                        <th class="text-center">Status</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($results as $result)
                        <tr>
                            <td class="text-muted">{{ $loop->iteration }}</td>
                            <td class="fw-semibold">
                                @if ($result->student_id)
                                    <a class="text-decoration-none" href="{{ route('training.students.show', $result->student_id) }}">
                                        {{ $result->student?->full_name ?? 'Trainee #' . $result->student_id }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-center fw-semibold">{{ rtrim(rtrim(number_format($result->marks_obtained ?? 0, 2), '0'), '.') }} / {{ $full }}</td>
                            <td class="text-center">
                                <span class="fw-semibold {{ $resultBadge[$result->result_status] ?? 'text-muted' }}">{{ ucfirst($result->result_status ?? '—') }}</span>
                            </td>
                            <td>{{ $result->remarks ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No results recorded for this exam yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection

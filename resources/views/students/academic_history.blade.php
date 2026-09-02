@extends('layouts.institute')

@section('title', 'Academic History — ' . $student->full_name . ' — AccumenAI')

@section('content')
@php
    $placementStatusBadge = [
        'active'      => 'text-bg-success',
        'completed'   => 'text-bg-primary',
        'transferred' => 'text-bg-info',
        'dropped'     => 'text-bg-secondary',
    ];
    $verdictBadge = [
        'promoted'     => ['Promoted', 'text-bg-success'],
        'conditional'  => ['Conditional', 'text-bg-warning'],
        'repeat'       => ['Repeat', 'text-bg-danger'],
        'not_promoted' => ['Not Promoted', 'text-bg-danger'],
        'completed'    => ['Completed', 'text-bg-info'],
        'graduated'    => ['Graduated', 'text-bg-info'],
        'pending'      => ['Pending', 'text-bg-secondary'],
    ];
@endphp

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
        <a href="{{ route('students.show', $student) }}" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="bi bi-arrow-left me-1"></i>Back to Profile
        </a>
        <h4 class="page-header-title">
            <i class="bi bi-mortarboard me-1 text-primary"></i>Academic History — {{ $student->full_name }}
            @if ($student->student_id)
                <span class="text-muted small fw-normal ms-1">{{ $student->student_id }}</span>
            @endif
        </h4>
    </div>
    @if ($academicYears->isNotEmpty())
        <div class="d-flex align-items-center gap-2 no-print">
            @if (isset($canViewAttendanceReport) && $canViewAttendanceReport)
                <a href="{{ route('academic-attendance.reports.student', $student) }}" class="btn btn-sm btn-outline-primary" target="_blank">
                    <i class="bi bi-clipboard-data me-1"></i>View Attendance Report
                </a>
            @endif
            <a href="{{ route('students.academic-transcript', $student) }}" class="btn btn-sm btn-outline-primary" target="_blank">
                <i class="bi bi-file-earmark-text me-1"></i>Print Transcript
            </a>
            <form method="GET" action="{{ route('students.academic-history', $student) }}" class="d-flex align-items-center gap-2">
            <select name="academic_year_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="">All academic years</option>
                @foreach ($academicYears as $year)
                    <option value="{{ $year->id }}" @selected((int) $selectedYearId === (int) $year->id)>
                        {{ $year->name ?: ($year->code ?: 'Year #' . $year->id) }}
                    </option>
                @endforeach
            </select>
            @if ($selectedYearId)
                <a href="{{ route('students.academic-history', $student) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-lg"></i>
                </a>
            @endif
            </form>
        </div>
    @endif
</div>

@if ($timeline->isNotEmpty())
    @php
        $lifecycleBadge = [
            'active'       => ['Active', 'text-bg-secondary'],
            'promoted'     => ['Promoted', 'text-bg-success'],
            'conditional'  => ['Conditional', 'text-bg-warning'],
            'repeat'       => ['Repeat', 'text-bg-danger'],
            'not_promoted' => ['Not Promoted', 'text-bg-danger'],
            'completed'    => ['Completed', 'text-bg-info'],
            'graduated'    => ['Graduated', 'text-bg-info'],
            'withdrawn'    => ['Withdrawn', 'text-bg-secondary'],
            'transferred'  => ['Transferred', 'text-bg-primary'],
            'pending'      => ['Pending', 'text-bg-secondary'],
        ];
        $lifecycleChip = $lifecycleBadge[$lifecycle['outcome']] ?? [ucfirst($lifecycle['outcome']), 'text-bg-secondary'];
    @endphp
    <div class="admin-card mb-3">
        <div class="table-toolbar">
            <div class="toolbar-info">
                <i class="bi bi-award"></i>
                <span class="fw-semibold">Academic Lifecycle</span>
            </div>
        </div>
        <div class="p-3 d-flex flex-wrap align-items-center gap-3">
            <span class="badge {{ $lifecycleChip[1] }} fs-6 px-3 py-2">{{ $lifecycleChip[0] }}</span>
            <div>
                @if ($lifecycle['isGraduation'] || $lifecycle['isCompletion'])
                    <div class="fw-semibold">
                        {{ $lifecycle['isGraduation'] ? 'Officially graduated' : 'Officially completed the academic program' }}
                    </div>
                    <div class="small text-muted">
                        @if ($lifecycle['approvedDate'])
                            Approved {{ $lifecycle['approvedDate']->format('M j, Y') }}
                        @endif
                        @if ($lifecycle['item']?->placement?->academicYear?->name)
                            · from {{ $lifecycle['item']->placement->academicYear->name }}
                        @endif
                        @if ($lifecycle['item']?->placement?->classGrade?->name)
                            · {{ $lifecycle['item']->placement->classGrade->name }}
                        @endif
                    </div>
                    @if (($certificateRequestable ?? false))
                        <form method="POST" action="{{ route('students.certificate-request', $student) }}" class="mt-2" onsubmit="return confirm('Submit a certificate request for {{ $student->full_name }}? The request is reviewed and issued by the platform registry.');">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-award me-1"></i>Request certificate
                            </button>
                        </form>
                    @endif
                @elseif ($lifecycle['isWithdrawal'])
                    <div class="fw-semibold">Officially withdrawn from the academic program</div>
                    <div class="small text-muted">The current academic placement has been closed as withdrawn.</div>
                @elseif ($lifecycle['isTransfer'])
                    <div class="fw-semibold">Officially transferred from the current placement</div>
                    <div class="small text-muted">The current academic placement has been closed as transferred.</div>
                @elseif ($lifecycle['progressingTo'])
                    <div class="fw-semibold">Progressing to {{ $lifecycle['progressingTo']->name }}</div>
                    <div class="small text-muted">Latest approved promotion advances this student.</div>
                @else
                    <div class="fw-semibold">{{ $lifecycle['outcome'] === 'active' ? 'Academic journey in progress' : 'Latest approved promotion outcome' }}</div>
                    @if ($lifecycle['approvedDate'])
                        <div class="small text-muted">Approved {{ $lifecycle['approvedDate']->format('M j, Y') }}</div>
                    @else
                        <div class="small text-muted">No official outcome recorded yet.</div>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endif

@if ($timeline->isEmpty())
    <div class="admin-card mt-2">
        <div class="p-4 text-center text-muted">
            <i class="bi bi-calendar-x fs-3 d-block mb-2"></i>
            No academic placements recorded
            @if ($selectedYearId)
                for the selected year.
                <a href="{{ route('students.academic-history', $student) }}" class="text-decoration-none">View all years</a>.
            @else
                for this student yet.
            @endif
        </div>
    </div>
@else
    <div class="row g-3 mt-1">
        <div class="col-12">
            @foreach ($timeline as $entry)
                @php
                    $placement = $entry['placement'];
                    $uid = $placement->id;
                    $snapshot = $entry['snapshot'];
                    $result = $entry['result'];
                    $rows = $entry['rows']->sortBy(fn ($row) => $row->subject?->name ?? 'ZZ' . $row->subject_id);
                    $promotion = $entry['promotion'];
                    $decision = $promotion !== null ? $promotion->getRelation('decision') : null;
                @endphp
                <div class="admin-card mb-3">
                    <div class="table-toolbar d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="toolbar-info">
                            <i class="bi bi-calendar3"></i>
                            <span class="fw-semibold">{{ $placement->academicYear?->name ?? 'Year #' . $placement->academic_year_id }}</span>
                            <span class="text-muted mx-1">·</span>
                            <span>{{ $placement->classGrade?->name ?? 'Class removed' }}</span>
                            @if ($placement->academicGroup)
                                <span class="text-muted mx-1">·</span>
                                <span>{{ $placement->academicGroup->name }}</span>
                            @endif
                            <span class="badge {{ $placementStatusBadge[$placement->status] ?? 'text-bg-secondary' }} ms-2">{{ ucfirst($placement->status) }}</span>
                            @if ($entry['isCurrent'] && ! $selectedYearId)
                                <span class="badge text-bg-light border ms-1">Current</span>
                            @endif
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            @if ($snapshot)
                                <span class="badge text-bg-success">Published</span>
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#subjects{{ $uid }}" aria-expanded="false" aria-controls="subjects{{ $uid }}">
                                    <i class="bi bi-layout-text-window-reverse me-1"></i>Subjects
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="p-3">
                        <div class="d-flex flex-wrap gap-3 align-items-start">
                            {{-- GPA summary --}}
                            <div class="me-3">
                                <div class="text-muted small text-uppercase">GPA</div>
                                <div class="fs-3 fw-bold text-primary">
                                    @if ($snapshot && is_numeric($snapshot->gpa))
                                        {{ number_format($snapshot->gpa, 2) }}
                                    @else
                                        <span class="text-muted fs-5">—</span>
                                    @endif
                                </div>
                                @if ($snapshot && ! is_numeric($snapshot->gpa))
                                    <div class="small text-muted" title="{{ $snapshot->gpa_reason }}">
                                        {{ $snapshot->gpa_status === 'computed' ? 'Not computed yet' : 'Unavailable' }}
                                    </div>
                                @endif
                            </div>

                            {{-- Attendance summary (only shown when the academic
                                 year's date range makes the grouping reliable) --}}
                            @if (isset($attendanceByPlacement[$placement->id]))
                                @php $attendance = $attendanceByPlacement[$placement->id]; @endphp
                                <div>
                                    <div class="text-muted small text-uppercase">Attendance</div>
                                    <div class="fw-semibold">
                                        {{ $attendance['present_percent'] !== null ? number_format($attendance['present_percent'], 1) . '%' : '—' }}
                                    </div>
                                    <div class="small text-muted">
                                        {{ $attendance['present'] }} present · {{ $attendance['absent'] }} absent
                                        @if ((int) $attendance['late'] > 0)· {{ $attendance['late'] }} late @endif
                                        @if ((int) $attendance['leave'] > 0)· {{ $attendance['leave'] }} leave @endif
                                    </div>
                                </div>
                            @endif

                            @if ($snapshot)
                                <div>
                                    <div class="text-muted small text-uppercase">Result</div>
                                    <div class="fw-semibold">{{ $result?->name ?? 'Published result' }}</div>
                                    <div class="small text-muted">
                                        Published {{ $result?->published_at?->format('M j, Y') ?? '' }}
                                        @if ($result?->scheme?->academicYear?->name)
                                            · {{ $result->scheme->academicYear->name }}
                                        @endif
                                    </div>
                                </div>
                                <div class="d-flex gap-2 mt-auto">
                                    <span class="badge text-bg-success badge-soft">
                                        <i class="bi bi-check-circle me-1"></i>{{ $snapshot->passed_count }} passed
                                    </span>
                                    @if ((int) $snapshot->failed_count > 0)
                                        <span class="badge text-bg-danger badge-soft">
                                            <i class="bi bi-x-circle me-1"></i>{{ $snapshot->failed_count }} failed
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>

                        {{-- In-flight / unpublished notice --}}
                        @if (! $snapshot && $entry['inProgress'])
                            <div class="alert alert-secondary rounded-0 border-0 small my-2 py-2 mb-0">
                                <i class="bi bi-hourglass-split me-1"></i>Final result for this year is being prepared and is not published yet.
                            </div>
                        @elseif (! $snapshot)
                            <div class="alert alert-light rounded-0 border small my-2 py-2 mb-0 text-muted">
                                <i class="bi bi-inbox me-1"></i>No final result published for this year.
                            </div>
                        @endif

                        {{-- Promotion verdict --}}
                        <hr class="my-3">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <i class="bi bi-arrow-up-right-circle text-muted"></i>
                            <span class="text-muted small text-uppercase me-1">Promotion</span>
                            @if ($promotion)
                                @php
                                    $verdict = $verdictBadge[$promotion->decision] ?? [ucfirst($promotion->decision), 'text-bg-secondary'];
                                @endphp
                                <span class="badge {{ $verdict[1] }}">{{ $verdict[0] }}</span>
                                @if ($decision)
                                    <span class="badge text-bg-light border">{{ ucfirst($decision->status) }}</span>
                                @endif
                                @if ($promotion->nextPlacement?->academicYear?->name)
                                    <span class="small text-muted">→ {{ $promotion->nextPlacement->academicYear->name }}
                                        @if ($promotion->targetClassGrade){{ $promotion->targetClassGrade->name }}@endif
                                        @if ($promotion->targetAcademicGroup){{ $promotion->targetAcademicGroup->name }}@endif
                                    </span>
                                @endif
                            @else
                                <span class="small text-muted">No approved promotion decision recorded.</span>
                            @endif
                        </div>

                        {{-- Selected subjects when there is no snapshot --}}
                        @if (! $snapshot && $placement->selections->isNotEmpty())
                            <div class="mt-3">
                                <div class="text-muted small text-uppercase mb-2">Subjects studied</div>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($placement->selections as $selection)
                                        <span class="badge text-bg-light border px-3 py-2">
                                            {{ $selection->subject?->name ?? 'Subject removed' }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Per-subject snapshot --}}
                    @if ($snapshot)
                        <div class="collapse" id="subjects{{ $uid }}">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Subject</th>
                                            <th class="text-center">Grade</th>
                                            <th class="text-center">Points</th>
                                            <th class="text-center">Aggregate</th>
                                            <th class="text-center">Credits</th>
                                            <th class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($rows as $row)
                                            <tr>
                                                <td>
                                                    <span class="fw-semibold">{{ $row->subject?->name ?? 'Subject #' . $row->subject_id }}</span>
                                                    @if ($row->optional)
                                                        <span class="badge text-bg-light border ms-1">Optional</span>
                                                    @endif
                                                    @if ($row->grade !== null && ! $row->gpa_included)
                                                        <div class="small text-muted"><i class="bi bi-info-circle me-1"></i>Not counted in GPA</div>
                                                    @endif
                                                </td>
                                                <td class="text-center fw-semibold">{{ $row->grade ?? '—' }}</td>
                                                <td class="text-center">
                                                    {{ $row->grade_point !== null ? number_format($row->grade_point, 2) : '—' }}
                                                </td>
                                                <td class="text-center">
                                                    @if ($row->aggregate !== null)
                                                        {{ rtrim(rtrim(number_format($row->aggregate, 2), '0'), '.') }}%
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if ($row->credits !== null)
                                                        {{ rtrim(rtrim(number_format($row->credits, 2), '0'), '.') }}
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if ($row->subject_status === 'PASS')
                                                        <span class="badge text-bg-success">Pass</span>
                                                    @elseif ($row->subject_status === 'FAIL')
                                                        <span class="badge text-bg-danger">Fail</span>
                                                    @else
                                                        <span class="text-muted small">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">No subject rows recorded in the published snapshot.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif

@php
    $certStatusBadge = [
        'active'  => 'text-bg-success',
        'revoked' => 'text-bg-danger',
        'pending' => 'text-bg-warning',
        'rejected' => 'text-bg-secondary',
    ];
@endphp

<div class="admin-card mt-3">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-award"></i>
            <span class="fw-semibold">Certificates</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Certificate No.</th>
                    <th>Type</th>
                    <th>Course</th>
                    <th>Batch</th>
                    <th>Issue Date</th>
                    <th>Status</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($certificates as $certificate)
                    <tr>
                        <td class="text-muted">{{ $loop->iteration }}</td>
                        <td class="fw-semibold">{{ $certificate->certificate_number ?? '—' }}</td>
                        <td>{{ $certificate->type?->name ?? '—' }}</td>
                        <td>{{ $certificate->course?->name ?? 'Not provided' }}</td>
                        <td>
                            {{ $certificate->batch?->name ?? 'Not provided' }}
                            @if ($certificate->batch?->batch_code)
                                <small class="text-muted d-block">{{ $certificate->batch->batch_code }}</small>
                            @endif
                        </td>
                        <td>{{ $certificate->issue_date?->format('d M Y') ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $certStatusBadge[$certificate->status] ?? 'text-bg-secondary' }}">{{ ucfirst($certificate->status) }}</span>
                        </td>
                        <td class="text-end">
                            @if ($certificate->verification_url)
                                <a href="{{ $certificate->verification_url }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>View Certificate
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No certificates issued for this student yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
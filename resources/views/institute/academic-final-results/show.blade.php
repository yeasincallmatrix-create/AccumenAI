@extends('layouts.standalone')

@section('title', $result->name.' — Final Result — AccumenAI')
@section('page_title', 'Final Result')

@php
    $statusBadge = [
        'review'    => ['Review', 'text-bg-secondary'],
        'approved'  => ['Approved', 'text-bg-info'],
        'locked'    => ['Locked', 'text-bg-warning'],
        'published' => ['Published', 'text-bg-success'],
    ];
    $rowStatus = [
        'computed'     => ['Computed', 'text-success'],
        'incomplete'   => ['Incomplete', 'text-warning'],
        'absent_only'  => ['Absent', 'text-muted'],
        'not_eligible' => ['—', 'text-muted'],
        'no_grade_scale' => ['No scale', 'text-muted'],
        'no_band'      => ['No band', 'text-warning'],
    ];
@endphp

@section('content')

<div class="standalone-heading">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <h4 class="mb-0">{{ $result->name }}</h4>
        <span class="badge {{ $statusBadge[$result->status][1] ?? 'text-bg-secondary' }}">{{ $statusBadge[$result->status][0] ?? ucfirst($result->status) }}</span>
        <a href="{{ route('settings.academic.final-results.policy', $result->scheme) }}" class="small text-muted">policy</a>
    </div>
    <p class="mb-2">
        {{ $result->scheme?->academicYear?->name ?? '—' }} ·
        {{ $result->scheme?->classGrade?->name ?? '—' }}
        @if ($result->scheme?->academicGroup) · {{ $result->scheme->academicGroup->name }} @endif
        @if ($result->scheme?->branch) · Branch: {{ $result->scheme->branch->name }} @endif
    </p>

    <div class="d-flex gap-2 flex-wrap align-items-center">
        @if ($result->canApprove())
            <form method="POST" action="{{ route('settings.academic.final-results.approve', $result) }}">
                @csrf
                <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-check-circle me-1"></i>Approve</button>
            </form>
        @endif

        @if ($result->canSendBackToReview())
            <form method="POST" action="{{ route('settings.academic.final-results.send-to-review', $result) }}">
                @csrf
                <button class="btn btn-sm btn-outline-warning" type="submit"><i class="bi bi-arrow-counterclockwise me-1"></i>Send back to review</button>
            </form>
        @endif

        @if ($result->canLock())
            <form method="POST" action="{{ route('settings.academic.final-results.lock', $result) }}" data-confirm="Lock this final result? Numbers will be frozen for this cycle and participating assessments will stop accepting marks edits.">
                @csrf
                <button class="btn btn-sm btn-warning" type="submit"><i class="bi bi-lock me-1"></i>Lock</button>
            </form>
        @endif

        @if ($result->canPublish())
            <form method="POST" action="{{ route('settings.academic.final-results.publish', $result) }}" data-confirm="Publish this final result? It becomes the official result for this cycle.">
                @csrf
                <button class="btn btn-sm btn-success" type="submit"><i class="bi bi-send me-1"></i>Publish</button>
            </form>
        @endif

        @if ($result->status === \App\Models\AcademicFinalResult::STATUS_PUBLISHED)
            <a href="{{ route('settings.academic.final-results.result-sheet', $result) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>Print Result Sheet
            </a>
            <a href="{{ route('settings.academic.final-results.export', $result) }}" class="btn btn-sm btn-outline-success">
                <i class="bi bi-filetype-csv me-1"></i>Export CSV
            </a>
        @endif

        <a href="{{ route('settings.academic.final-results.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>All Final Results
        </a>

        <a href="{{ route('settings.academic.final-results.readiness', $result->scheme) }}" class="btn btn-sm btn-outline-success">
            <i class="bi bi-signpost-2 me-1"></i>Result Readiness
        </a>
        <a href="{{ route('settings.academic.final-results.preflight', $result->scheme) }}" class="btn btn-sm btn-outline-info">
            <i class="bi bi-rocket-takeoff me-1"></i>Pre-flight Check
        </a>
    </div>
</div>

@if ($finalized)
    <div class="alert alert-info rounded-0 border-0 small mb-3">
        <i class="bi bi-lock-fill me-1"></i>
        This result was locked at <strong>{{ $result->locked_at?->format('M j, Y g:ia') }}</strong> by
        <strong>{{ $result->locker?->name ?? 'a staff member' }}</strong>. The numbers below are the frozen snapshot
        @if ($result->published_at)
            and were published at <strong>{{ $result->published_at->format('M j, Y g:ia') }}</strong>.
        @else
            and no longer change with edits to the source marks.
        @endif
    </div>
@else
    <div class="alert alert-secondary rounded-0 border-0 small mb-3">
        <i class="bi bi-calculator me-1"></i>
        Live derived preview — computed from the current marks under this policy's settings. Nothing is stored until the result is locked.
    </div>
@endif

<div class="admin-card mb-3">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-person-vcard"></i>
            <span class="fw-semibold">{{ $finalized ? 'Snapshot' : 'Preview' }}</span>
            @unless ($finalized)
                <span class="text-muted small ms-2">Never stored, never alters marks.</span>
            @endunless
        </div>
    </div>

    @if (! $render['weights_valid'])
        <div class="alert alert-warning rounded-0 mb-0 border-0 small">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            Total configured weight is {{ $render['total_weight'] }}%; the result cannot be locked until it equals 100%.
        </div>
    @endif

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Student</th>
                    @foreach ($render['subjects'] as $subject)
                        <th class="text-center">{{ $subject->name }}</th>
                    @endforeach
                    <th class="text-center">GPA</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($render['rows'] as $row)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $row['student']?->full_name ?? 'Student #'.$row['placement_id'] }}</div>
                            @if ($row['student']?->student_id)
                                <div class="small text-muted">{{ $row['student']->student_id }}</div>
                            @endif
                            @if ($finalized && $row['student'] !== null)
                                <a href="{{ route('settings.academic.final-results.report', [$result, $row['placement_id']]) }}" class="btn btn-sm btn-link btn-report-card p-0 small">
                                    <i class="bi bi-printer me-1"></i>Report card
                                </a>
                            @endif
                        </td>
                        @foreach ($render['subjects'] as $subject)
                            @php
                                $cell = collect($row['cells'])->firstWhere('subject_id', $subject->id);
                            @endphp
                            <td class="text-center">
                                @if ($cell === null)
                                    <span class="text-muted small">—</span>
                                @elseif ($cell['grade'] !== null)
                                    <span class="fw-semibold">{{ $cell['grade'] }}</span>
                                    @if ($cell['aggregate'] !== null)
                                        <div class="small text-muted">{{ rtrim(rtrim(number_format($cell['aggregate'], 2), '0'), '.') }}%</div>
                                    @endif
                                    @if ($cell['subject_status'])
                                        <span class="badge {{ $cell['subject_status'] === 'PASS' ? 'text-bg-success' : 'text-bg-danger' }}">{{ $cell['subject_status'] }}</span>
                                    @endif
                                @else
                                    @php $rs = $rowStatus[$cell['status']] ?? ['—', 'text-muted']; @endphp
                                    <span class="small {{ $rs[1] }}">{{ $rs[0] }}</span>
                                @endif
                                @if ($cell && $cell['reason'])
                                    <div class="small text-muted" title="{{ $cell['reason'] }}"><i class="bi bi-info-circle me-1"></i>{{ $cell['reason'] }}</div>
                                @endif
                            </td>
                        @endforeach
                        <td class="text-center">
                            @if (is_numeric($row['gpa']))
                                <span class="fw-semibold">{{ number_format($row['gpa'], 2) }}</span>
                            @elseif ($row['gpa_status'] === 'computed')
                                <span class="text-muted">—</span>
                            @else
                                <span class="small text-muted" title="{{ $row['gpa_reason'] }}">Unavailable</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($render['subjects']) + 2 }}" class="text-center text-muted py-4">
                            No students are placed in this context.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
@extends('layouts.standalone')

@section('title', 'Result Readiness — '.$assessment->name.' — AccumenAI')
@section('page_title', 'Result Readiness')

@php
    $readinessBadge = [
        'ready' => 'text-bg-success',
        'ready_with_exceptions' => 'text-bg-warning',
        'not_ready' => 'text-bg-danger',
    ];
    $readinessLabel = [
        'ready' => 'READY',
        'ready_with_exceptions' => 'READY WITH EXCEPTIONS',
        'not_ready' => 'NOT READY',
    ];
    $subjectBadge = [
        'complete' => ['✓', 'text-success'],
        'incomplete' => ['!', 'text-warning'],
        'absent' => ['A', 'text-muted'],
        'missing' => ['✗', 'text-danger'],
        'not_eligible' => ['—', 'text-muted'],
    ];
    $studentBadge = [
        'complete' => ['Complete', 'text-bg-success'],
        'incomplete' => ['Incomplete', 'text-bg-warning'],
        'absent' => ['Absent', 'text-bg-secondary'],
        'missing' => ['Missing marks', 'text-bg-danger'],
        'no_assessment' => ['No assessment record', 'text-bg-danger'],
        'not_eligible' => ['Not applicable', 'text-bg-light'],
    ];
    $subjectStatusLabel = [
        'complete' => 'Complete',
        'incomplete' => 'Incomplete',
        'absent' => 'Absent',
        'missing' => 'Missing marks',
        'not_eligible' => 'Not selected',
    ];
@endphp

@section('content')

<div class="standalone-heading">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <h4 class="mb-0">
            <i class="bi bi-clipboard-check me-1"></i>{{ $assessment->name }} · Result Readiness
            <span class="badge {{ $readinessBadge[$readiness['readiness']] ?? 'text-bg-secondary' }} ms-1 text-uppercase">
                {{ $readinessLabel[$readiness['readiness']] ?? $readiness['readiness'] }}
            </span>
        </h4>
        <div class="ms-auto d-flex gap-2">
            <a href="{{ route('settings.academic.assessments.readiness.export', $assessment) }}" class="btn btn-sm btn-outline-success">
                <i class="bi bi-filetype-csv me-1"></i>Export Exceptions CSV
            </a>
            <a href="{{ route('settings.academic.assessments.show', $assessment) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Assessment
            </a>
        </div>
    </div>
    <p class="text-muted small mb-0 mt-1">
        {{ $assessment->academicYear?->name ?? '—' }} ·
        {{ $assessment->classGrade?->name ?? '—' }}
        @if ($assessment->academicGroup) · {{ $assessment->academicGroup->name }} @endif
        @if ($assessment->assessmentType) · {{ $assessment->assessmentType->name }} @endif
        @if ($assessment->branch) · Branch: {{ $assessment->branch->name }} @endif
        · {{ $readiness['subjects_included'] }} subject(s) · {{ $readiness['summary']['eligible_students'] }} eligible student(s)
    </p>
</div>

@if ($readiness['reasons'])
    <div class="alert {{ $readiness['readiness'] === 'ready' ? 'alert-success' : ($readiness['readiness'] === 'ready_with_exceptions' ? 'alert-warning' : 'alert-danger') }} small py-2 mb-3">
        <i class="bi bi-info-circle me-1"></i>
        @foreach ($readiness['reasons'] as $reason)
            <div>{{ $reason }}</div>
        @endforeach
    </div>
@endif

<div class="admin-card mb-3">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-people"></i>
            <span class="fw-semibold">Readiness Summary</span>
        </div>
    </div>
    <div class="p-3">
        @php $summary = $readiness['summary']; @endphp
        <div class="row g-3 text-center">
            <div class="col-6 col-md-2">
                <div class="fs-4 fw-semibold">{{ $summary['eligible_students'] }}</div>
                <div class="text-muted small">Eligible Students</div>
            </div>
            <div class="col-6 col-md-2">
                <div class="fs-4 fw-semibold text-success">{{ $summary['complete'] }}</div>
                <div class="text-muted small">Complete</div>
            </div>
            <div class="col-6 col-md-2">
                <div class="fs-4 fw-semibold text-warning">{{ $summary['incomplete'] }}</div>
                <div class="text-muted small">Incomplete</div>
            </div>
            <div class="col-6 col-md-2">
                <div class="fs-4 fw-semibold text-muted">{{ $summary['absent'] }}</div>
                <div class="text-muted small">Absent</div>
            </div>
            <div class="col-6 col-md-2">
                <div class="fs-4 fw-semibold text-danger">{{ $summary['missing'] }}</div>
                <div class="text-muted small">Missing Marks</div>
            </div>
            <div class="col-6 col-md-2">
                <div class="fs-4 fw-semibold text-danger">{{ $summary['no_assessment'] }}</div>
                <div class="text-muted small">No Assessment Record</div>
            </div>
        </div>
    </div>
</div>

@if ($readiness['subjects_with_missing_marks']->count())
    <div class="alert alert-warning small py-2 mb-3">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Subjects with missing (unrecorded) marks:
        @foreach ($readiness['subjects_with_missing_marks'] as $subject)
            <strong>{{ $subject['name'] }}</strong> ({{ $subject['missing'] }})@if (! $loop->last), @endif
        @endforeach
    </div>
@endif

<div class="admin-card mb-3">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-journal-text"></i>
            <span class="fw-semibold">Subjects</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th class="text-center" style="width:90px">Components</th>
                    <th class="text-center" style="width:90px">Eligible</th>
                    <th class="text-center" style="width:90px"><span class="text-success">Complete</span></th>
                    <th class="text-center" style="width:90px"><span class="text-warning">Incomplete</span></th>
                    <th class="text-center" style="width:90px"><span class="text-muted">Absent</span></th>
                    <th class="text-center" style="width:90px"><span class="text-danger">Missing</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($readiness['subject_summary'] as $subject)
                    <tr @class(['table-danger' => $subject['missing'] > 0, 'table-warning' => $subject['missing'] === 0 && $subject['incomplete'] > 0])>
                        <td class="fw-semibold">{{ $subject['name'] }}</td>
                        <td class="text-center">{{ $subject['components'] }}</td>
                        <td class="text-center">{{ $subject['eligible'] }}</td>
                        <td class="text-center">{{ $subject['complete'] }}</td>
                        <td class="text-center">{{ $subject['incomplete'] }}</td>
                        <td class="text-center">{{ $subject['absent'] }}</td>
                        <td class="text-center">{{ $subject['missing'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-3">No subjects are configured for this assessment.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-people"></i>
            <span class="fw-semibold">Student-level Readiness</span>
        </div>
        <div class="toolbar-actions">
            <span class="text-muted small">✓ Complete · ! Incomplete · A Absent · ✗ Missing · — Not selected</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:44px">#</th>
                    <th>Student</th>
                    @foreach ($readiness['subject_summary'] as $subject)
                        <th class="text-center" style="width:56px" title="{{ $subject['name'] }}">
                            {{ \Illuminate\Support\Str::limit($subject['name'], 12) }}
                        </th>
                    @endforeach
                    <th class="text-center" style="width:110px">Overall Readiness</th>
                    <th class="text-center" style="width:160px">Needs Attention</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($readiness['rows'] as $index => $row)
                    @php [$label, $badge] = $studentBadge[$row['status']] ?? [$row['status'], 'text-bg-light']; @endphp
                    <tr @class(['table-danger' => in_array($row['status'], ['no_assessment', 'missing'], true), 'table-warning' => $row['status'] === 'incomplete'])>
                        <td class="text-muted">{{ $index + 1 }}</td>
                        <td>
                            <div class="fw-semibold">{{ $row['student']?->full_name ?? ('Student #'.$row['placement']->student_id) }}</div>
                            @if ($row['student']?->student_id)
                                <div class="small text-muted">{{ $row['student']->student_id }}</div>
                            @endif
                        </td>
                        @foreach ($readiness['subject_summary'] as $subjectConfig)
                            @php
                                $cell = $row['cells'][$subjectConfig['config']->id] ?? null;
                                [$symbol, $color] = $cell ? ($subjectBadge[$cell['status']] ?? ['—', 'text-muted']) : ['—', 'text-muted'];
                            @endphp
                            <td class="text-center" title="{{ $cell ? ($subjectConfig['name'].' — '.$subjectStatusLabel[$cell['status']]) : '' }}">
                                <span class="fs-5 {{ $color }}">{{ $symbol }}</span>
                            </td>
                        @endforeach
                        <td class="text-center">
                            <span class="badge {{ $badge }}">{{ $label }}</span>
                        </td>
                        <td class="text-center">
                            @if ($row['status'] === 'complete' || $row['status'] === 'not_eligible')
                                <span class="text-muted small">None</span>
                            @else
                                @php
                                    $attention = collect($row['cells'])
                                        ->filter(fn ($cell) => $cell !== null && in_array($cell['status'], ['missing', 'incomplete', 'absent'], true))
                                        ->map(fn ($cell) => $cell['name'].' ('.$subjectStatusLabel[$cell['status']].')')
                                        ->values();
                                @endphp
                                <span class="text-danger small">{{ $attention->implode(', ') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $readiness['subjects_included'] + 4 }}" class="text-center text-muted py-3">
                            No students are currently placed in this class
                            @if ($assessment->academicGroup)/group @endif for the selected academic year.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
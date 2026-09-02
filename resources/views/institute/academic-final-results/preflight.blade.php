@extends('layouts.standalone')

@section('title', 'Final Result Pre-flight — '.$report['scheme']->name.' — AccumenAI')
@section('page_title', 'Final Result Pre-flight')

@php
    $checkBadge = [
        'pass' => ['PASS', 'text-bg-success'],
        'warning' => ['WARNING', 'text-bg-warning'],
        'blocked' => ['BLOCKED', 'text-bg-danger'],
    ];
    $studentBadge = [
        'complete' => ['Complete', 'text-bg-success'],
        'incomplete' => ['Incomplete', 'text-bg-warning'],
        'absent' => ['Absent', 'text-bg-secondary'],
        'missing' => ['Missing marks', 'text-bg-danger'],
        'no_assessment' => ['No assessment record', 'text-bg-danger'],
        'not_eligible' => ['Not applicable', 'text-bg-light'],
    ];
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
    $summary = $report['coverage']['summary'];
@endphp

@section('content')

<div class="standalone-heading">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <h4 class="mb-0">
            <i class="bi bi-rocket-takeoff me-1"></i>{{ $report['scheme']->name }} · Final Result Pre-flight
            <span class="badge {{ $report['verdict']['allowed'] ? 'text-bg-success' : 'text-bg-danger' }} ms-1">
                {{ $report['verdict']['label'] }}
            </span>
            <span class="badge {{ $report['scheme']->weightIsValid() ? 'text-bg-success' : 'text-bg-warning' }}">Total weight: {{ $report['scheme']->totalWeight() }}%</span>
        </h4>
        <div class="ms-auto d-flex gap-2">
            <a href="{{ route('settings.academic.final-results.readiness', $report['scheme']) }}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-signpost-2 me-1"></i>Result Readiness
            </a>
            <a href="{{ route('settings.academic.final-results.policy', $report['scheme']) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Policy
            </a>
        </div>
    </div>
    <p class="text-muted small mb-0 mt-1">
        {{ $report['scheme']->academicYear?->name ?? '—' }} ·
        {{ $report['scheme']->classGrade?->name ?? '—' }}
        @if ($report['scheme']->academicGroup) · {{ $report['scheme']->academicGroup->name }} @endif
        @if ($report['scheme']->branch) · Branch: {{ $report['scheme']->branch->name }} @endif
    </p>
</div>

<div class="alert {{ $report['verdict']['allowed'] ? 'alert-success' : 'alert-danger' }} rounded-0 border-0 small mb-3">
    <i class="bi {{ $report['verdict']['allowed'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill' }} me-1"></i>
    <strong>{{ $report['verdict']['label'] }}</strong> —
    {{ $report['verdict']['blocking_count'] }} blocking issue(s) ·
    {{ $report['verdict']['warning_count'] }} warning(s).
    This page is read-only — nothing is generated, locked or published.
</div>

@if ($report['verdict']['warnings'])
    <div class="alert alert-warning small py-2 mb-3">
        <i class="bi bi-exclamation-triangle me-1"></i>
        @foreach ($report['verdict']['warnings'] as $warning)
            <div>{{ $warning }}</div>
        @endforeach
    </div>
@endif

@if ($report['verdict']['blocking'])
    <div class="alert alert-danger small py-2 mb-3">
        <i class="bi bi-x-octagon me-1"></i>
        @foreach ($report['verdict']['blocking'] as $issue)
            <div>{{ $issue }}</div>
        @endforeach
    </div>
@endif

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-geo-alt"></i> <span class="fw-semibold">Academic Scope</span></div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Scope</th>
                            <th>Value</th>
                            <th class="text-end" style="width:110px">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report['scope'] as $check)
                            @php [$label, $badge] = $checkBadge[$check['status']] ?? [$check['status'], 'text-bg-light']; @endphp
                            <tr>
                                <td class="fw-semibold">{{ $check['label'] }}</td>
                                <td class="text-muted">{{ $check['value'] }}</td>
                                <td class="text-end">
                                    <span class="badge {{ $badge }}" title="{{ $check['reason'] }}">{{ $label }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="admin-card h-100">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-sliders"></i> <span class="fw-semibold">Policy & Configuration</span></div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Check</th>
                            <th>Value</th>
                            <th class="text-end" style="width:110px">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_merge([$report['policy']], $report['configuration']) as $check)
                            @php [$label, $badge] = $checkBadge[$check['status']] ?? [$check['status'], 'text-bg-light']; @endphp
                            <tr>
                                <td class="fw-semibold">{{ $check['label'] }}</td>
                                <td class="text-muted">{{ $check['value'] }}</td>
                                <td class="text-end">
                                    <span class="badge {{ $badge }}" title="{{ $check['reason'] }}">{{ $label }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="admin-card mb-3">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-clipboard-check"></i>
            <span class="fw-semibold">Readiness Summary</span>
            <span class="badge {{ $readinessBadge[$report['coverage']['readiness']] ?? 'text-bg-secondary' }} ms-2 text-uppercase">
                {{ $readinessLabel[$report['coverage']['readiness']] ?? $report['coverage']['readiness'] }}
            </span>
        </div>
        <div class="toolbar-actions">
            <span class="text-muted small">Data completeness is reported as warnings — the engine supports incomplete / absent marks.</span>
        </div>
    </div>
    <div class="p-3">
        @php
            $studentsNotReady = $summary['incomplete'] + $summary['missing'] + $summary['no_assessment'] + $summary['not_eligible'];
        @endphp
        <div class="row g-3 text-center">
            <div class="col-6 col-md-3 col-lg-2">
                <div class="fs-4 fw-semibold">{{ $summary['eligible_students'] }}</div>
                <div class="text-muted small">Eligible Students</div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <div class="fs-4 fw-semibold text-success">{{ $summary['complete'] }}</div>
                <div class="text-muted small">Ready Students</div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <div class="fs-4 fw-semibold text-secondary">{{ $summary['absent'] }}</div>
                <div class="text-muted small">Legitimate Absences</div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <div class="fs-4 fw-semibold text-warning">{{ $summary['incomplete'] }}</div>
                <div class="text-muted small">Incomplete</div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <div class="fs-4 fw-semibold text-danger">{{ $summary['missing'] + $summary['no_assessment'] }}</div>
                <div class="text-muted small">Missing / No Record</div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="fs-4 fw-semibold text-danger">{{ $studentsNotReady }}</div>
                <div class="text-muted small">Students Not Ready</div>
            </div>
        </div>
    </div>
</div>

<div class="admin-card mb-3">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-exclamation-octagon"></i>
            <span class="fw-semibold">Student Exceptions</span>
        </div>
        <div class="toolbar-actions">
            <span class="text-muted small">Reused from the result-readiness gate; none of these block generation.</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Student ID</th>
                    <th>Missing Assessment</th>
                    <th>Missing Subject</th>
                    <th>Incomplete Assessment</th>
                    <th>Absent Assessment</th>
                    <th>Readiness</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['coverage']['exceptions'] as $row)
                    @php [$label, $badge] = $studentBadge[$row['status']] ?? [$row['status'], 'text-bg-light']; @endphp
                    <tr @class(['table-danger' => in_array($row['status'], ['no_assessment', 'missing'], true), 'table-warning' => $row['status'] === 'incomplete'])>
                        <td class="fw-semibold">{{ $row['student']?->full_name ?? ('Student #'.$row['placement']->student_id) }}</td>
                        <td>{{ $row['student']?->student_id ?? '—' }}</td>
                        <td>{{ $row['missing_assessments'] ? implode(', ', $row['missing_assessments']) : '—' }}</td>
                        <td>{{ $row['missing_subjects'] ? implode(', ', $row['missing_subjects']) : '—' }}</td>
                        <td>{{ $row['incomplete_assessments'] ? implode(', ', $row['incomplete_assessments']) : '—' }}</td>
                        <td>{{ $row['absent_assessments'] ? implode(', ', $row['absent_assessments']) : '—' }}</td>
                        <td><span class="badge {{ $badge }}">{{ $label }}</span></td>
                        <td class="text-muted small">{{ $row['reason'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-3">
                            No student exceptions — every placed student is complete.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
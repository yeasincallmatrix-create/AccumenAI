@extends('layouts.standalone')

@section('title', 'Final Result Readiness — '.$readiness['scheme']->name.' — AccumenAI')
@section('page_title', 'Final Result Readiness')

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
    $studentBadge = [
        'complete' => ['Complete', 'text-bg-success'],
        'incomplete' => ['Incomplete', 'text-bg-warning'],
        'absent' => ['Absent', 'text-bg-secondary'],
        'missing' => ['Missing marks', 'text-bg-danger'],
        'no_assessment' => ['No assessment record', 'text-bg-danger'],
        'not_eligible' => ['Not applicable', 'text-bg-light'],
    ];
    $summary = $readiness['summary'];
    $studentsNotReady = $summary['incomplete'] + $summary['missing'] + $summary['no_assessment'] + $summary['not_eligible'];
@endphp

@section('content')

<div class="standalone-heading">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <h4 class="mb-0">
            <i class="bi bi-signpost-2 me-1"></i>{{ $readiness['scheme']->name }} · Final Result Readiness
            <span class="badge {{ $readinessBadge[$readiness['readiness']] ?? 'text-bg-secondary' }} ms-1 text-uppercase">
                {{ $readinessLabel[$readiness['readiness']] ?? $readiness['readiness'] }}
            </span>
            <span class="badge {{ $readiness['scheme']->weightIsValid() ? 'text-bg-success' : 'text-bg-warning' }}">Total weight: {{ $readiness['scheme']->totalWeight() }}%</span>
        </h4>
        <div class="ms-auto d-flex gap-2">
            <a href="{{ route('settings.academic.final-results.readiness.export', $readiness['scheme']) }}" class="btn btn-sm btn-outline-success">
                <i class="bi bi-filetype-csv me-1"></i>Export Exceptions CSV
            </a>
            <a href="{{ route('settings.academic.final-results.policy', $readiness['scheme']) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Policy
            </a>
        </div>
    </div>
    <p class="text-muted small mb-0 mt-1">
        {{ $readiness['scheme']->academicYear?->name ?? '—' }} ·
        {{ $readiness['scheme']->classGrade?->name ?? '—' }}
        @if ($readiness['scheme']->academicGroup) · {{ $readiness['scheme']->academicGroup->name }} @endif
        @if ($readiness['scheme']->branch) · Branch: {{ $readiness['scheme']->branch->name }} @endif
        · Policy: {{ $readiness['policy']?->name ?? 'not configured yet' }}
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
            <i class="bi bi-grid-3x3-gap"></i>
            <span class="fw-semibold">Readiness Summary</span>
        </div>
    </div>
    <div class="p-3">
        <div class="row g-3 text-center">
            <div class="col-6 col-md-3 col-lg-2">
                <div class="fs-4 fw-semibold">{{ $summary['required_assessments'] }}</div>
                <div class="text-muted small">Required Assessments</div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <div class="fs-4 fw-semibold text-success">{{ $summary['ready_assessments'] }}</div>
                <div class="text-muted small">Ready Assessments</div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <div class="fs-4 fw-semibold text-warning">{{ $summary['with_exceptions_assessments'] }}</div>
                <div class="text-muted small">Assessments With Exceptions</div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <div class="fs-4 fw-semibold text-danger">{{ $summary['not_ready_assessments'] }}</div>
                <div class="text-muted small">Not Ready Assessments</div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="fs-4 fw-semibold">{{ $summary['eligible_students'] }}</div>
                <div class="text-muted small">Eligible Students</div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <div class="fs-4 fw-semibold text-success">{{ $summary['complete'] }}</div>
                <div class="text-muted small">Ready Students</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="fs-4 fw-semibold text-secondary">{{ $summary['absent'] }}</div>
                <div class="text-muted small">Students With Exceptions</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="fs-4 fw-semibold text-danger">{{ $studentsNotReady }}</div>
                <div class="text-muted small">Students Not Ready</div>
            </div>
        </div>
    </div>
</div>

<div class="admin-card mb-3">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-clipboard-check"></i>
            <span class="fw-semibold">Required Assessments</span>
        </div>
        <div class="toolbar-actions">
            <span class="text-muted small">Drilled down per assessment from the assessment-level result-readiness.</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Assessment</th>
                    <th style="width:140px">Type</th>
                    <th class="text-center" style="width:90px">Weight</th>
                    <th class="text-center" style="width:90px">Subjects</th>
                    <th class="text-center" style="width:90px">Eligible</th>
                    <th class="text-center" style="width:70px"><span class="text-success">Complete</span></th>
                    <th class="text-center" style="width:80px"><span class="text-warning">Incomplete</span></th>
                    <th class="text-center" style="width:70px"><span class="text-muted">Absent</span></th>
                    <th class="text-center" style="width:70px"><span class="text-danger">Missing</span></th>
                    <th style="width:190px">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($readiness['assessments'] as $assessmentData)
                    <tr @class(['table-danger' => $assessmentData['readiness'] === 'not_ready', 'table-warning' => $assessmentData['readiness'] === 'ready_with_exceptions'])>
                        <td class="fw-semibold">{{ $assessmentData['name'] }}</td>
                        <td>{{ $assessmentData['type'] ?? '—' }}</td>
                        <td class="text-center">{{ $assessmentData['weight'] }}%</td>
                        <td class="text-center">{{ $assessmentData['subjects_included'] }}</td>
                        <td class="text-center">{{ $assessmentData['summary']['eligible_students'] }}</td>
                        <td class="text-center">{{ $assessmentData['summary']['complete'] }}</td>
                        <td class="text-center">{{ $assessmentData['summary']['incomplete'] }}</td>
                        <td class="text-center">{{ $assessmentData['summary']['absent'] }}</td>
                        <td class="text-center">{{ $assessmentData['summary']['missing'] }}</td>
                        <td>
                            <span class="badge {{ $readinessBadge[$assessmentData['readiness']] ?? 'text-bg-secondary' }}">
                                {{ $readinessLabel[$assessmentData['readiness']] ?? $assessmentData['readiness'] }}
                            </span>
                            @if ($assessmentData['reasons'])
                                <div class="small text-muted mt-1">
                                    @foreach ($assessmentData['reasons'] as $reason)
                                        <div>{{ $reason }}</div>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted py-3">
                            No required assessments are configured for this aggregation scheme.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card mb-3">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-exclamation-octagon"></i>
            <span class="fw-semibold">Student Exceptions</span>
        </div>
        <div class="toolbar-actions">
            <span class="text-muted small">Only students who need attention; legitimately absent students included.</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Student ID</th>
                    <th>Registration Number</th>
                    <th>Missing Assessment</th>
                    <th>Missing Subject</th>
                    <th>Incomplete Assessment</th>
                    <th>Absent Assessment</th>
                    <th>Readiness</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($readiness['exceptions'] as $row)
                    @php [$label, $badge] = $studentBadge[$row['status']] ?? [$row['status'], 'text-bg-light']; @endphp
                    <tr @class(['table-danger' => in_array($row['status'], ['no_assessment', 'missing'], true), 'table-warning' => $row['status'] === 'incomplete'])>
                        <td>
                            <div class="fw-semibold">{{ $row['student']?->full_name ?? ('Student #'.$row['placement']->student_id) }}</div>
                        </td>
                        <td>{{ $row['student']?->student_id ?? '—' }}</td>
                        <td>{{ $row['student']?->reg_no ?? '—' }}</td>
                        <td>{{ $row['missing_assessments'] ? implode(', ', $row['missing_assessments']) : '—' }}</td>
                        <td>{{ $row['missing_subjects'] ? implode(', ', $row['missing_subjects']) : '—' }}</td>
                        <td>{{ $row['incomplete_assessments'] ? implode(', ', $row['incomplete_assessments']) : '—' }}</td>
                        <td>{{ $row['absent_assessments'] ? implode(', ', $row['absent_assessments']) : '—' }}</td>
                        <td><span class="badge {{ $badge }}">{{ $label }}</span></td>
                        <td class="text-muted small">{{ $row['reason'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-3">
                            No student exceptions — every placed student is complete.
                        </td>
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
            <span class="fw-semibold">Student-level Coverage</span>
        </div>
        <div class="toolbar-actions">
            <span class="text-muted small">Per-student counts across the required assessments.</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:44px">#</th>
                    <th>Student</th>
                    <th class="text-center" style="width:80px">Required</th>
                    <th class="text-center" style="width:80px"><span class="text-success">Complete</span></th>
                    <th class="text-center" style="width:80px"><span class="text-warning">Incomplete</span></th>
                    <th class="text-center" style="width:70px"><span class="text-muted">Absent</span></th>
                    <th class="text-center" style="width:70px"><span class="text-danger">Missing</span></th>
                    <th class="text-center" style="width:90px"><span class="text-danger">No Record</span></th>
                    <th class="text-center" style="width:140px">Overall Readiness</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($readiness['students'] as $index => $row)
                    @php [$label, $badge] = $studentBadge[$row['status']] ?? [$row['status'], 'text-bg-light']; @endphp
                    <tr @class(['table-danger' => in_array($row['status'], ['no_assessment', 'missing'], true), 'table-warning' => $row['status'] === 'incomplete'])>
                        <td class="text-muted">{{ $index + 1 }}</td>
                        <td class="fw-semibold">{{ $row['student']?->full_name ?? ('Student #'.$row['placement']->student_id) }}</td>
                        <td class="text-center">{{ $row['required'] }}</td>
                        <td class="text-center">{{ $row['complete'] }}</td>
                        <td class="text-center">{{ $row['incomplete'] }}</td>
                        <td class="text-center">{{ $row['absent'] }}</td>
                        <td class="text-center">{{ $row['missing'] }}</td>
                        <td class="text-center">{{ $row['no_assessment'] }}</td>
                        <td class="text-center"><span class="badge {{ $badge }}">{{ $label }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-3">
                            No students are currently placed in this class/group for the selected academic year.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
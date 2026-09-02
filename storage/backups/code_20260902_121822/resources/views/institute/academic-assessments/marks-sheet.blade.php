@extends('layouts.standalone')

@section('title', 'Marks Sheet — '.$assessment->name.' — AccumenAI')
@section('page_title', 'Assessment Marks Sheet')

@push('styles')
<style>
    .sheet {
        margin: 24px auto;
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 10px;
        padding: 24px 28px;
        color: #212529;
    }
    .report-header {
        text-align: center;
        border-bottom: 3px double #212529;
        padding-bottom: 14px;
        margin-bottom: 18px;
    }
    .report-header .institute-name {
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: 0.02em;
    }
    .report-header .institute-address {
        font-size: 0.85rem;
        color: #6c757d;
    }
    .report-header .report-title {
        font-size: 0.95rem;
        text-transform: uppercase;
        letter-spacing: 0.2em;
        margin-top: 4px;
    }
    .meta-line {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 4px 18px;
        font-size: 0.85rem;
    }
    .meta-line .label {
        color: #6c757d;
    }
    .sheet-summary {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 8px;
        margin: 12px 0;
        font-size: 0.72rem;
    }
    .sheet-summary .chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 999px;
        background: #f1f3f5;
        color: #495057;
        font-weight: 600;
    }
    .sheet-summary .chip.pass { background: #d1e7dd; color: #0f5132; }
    .sheet-summary .chip.fail { background: #f8d7da; color: #842029; }
    .sheet-summary .chip.absent { background: #fff3cd; color: #664d03; }
    .sheet-summary .chip.incomplete { background: #cff4fc; color: #055160; }
    .sheet-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.75rem;
    }
    .sheet-table th,
    .sheet-table td {
        border: 1px solid #adb5bd;
        padding: 4px 6px;
        text-align: center;
        vertical-align: middle;
    }
    .sheet-table th {
        background: #f1f3f5;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .sheet-table td.student {
        text-align: left;
    }
    .student-name {
        font-weight: 600;
    }
    .student-id {
        color: #6c757d;
        font-size: 0.68rem;
    }
    .obtained {
        font-weight: 700;
    }
    .full {
        color: #6c757d;
        font-size: 0.68rem;
    }
    .badge-status {
        display: inline-block;
        padding: 1px 8px;
        border-radius: 999px;
        font-size: 0.62rem;
        font-weight: 600;
    }
    .badge-status.pass { background: #d1e7dd; color: #0f5132; }
    .badge-status.fail { background: #f8d7da; color: #842029; }
    .badge-status.absent { background: #fff3cd; color: #664d03; }
    .badge-status.neutral { background: #e9ecef; color: #495057; }
    .notes {
        font-size: 0.72rem;
        color: #495057;
        margin-top: 10px;
    }
    .signature-block {
        margin-top: 40px;
        display: flex;
        justify-content: space-between;
        gap: 24px;
    }
    .signature-item {
        text-align: center;
        width: 200px;
    }
    .signature-item .line {
        border-bottom: 1px solid #6c757d;
        margin-bottom: 6px;
        height: 46px;
    }
    .signature-item .label {
        font-size: 0.8rem;
        color: #6c757d;
    }

    @media print {
        @page {
            size: A4 landscape;
            margin: 10mm;
        }
        .sheet {
            margin: 0;
            border: none;
            border-radius: 0;
            padding: 0;
        }
        .no-print {
            display: none !important;
        }
        body {
            background: #fff !important;
        }
        .topbar,
        .standalone-page-title {
            display: none !important;
        }
    }
</style>
@endpush

@section('content')

<div class="no-print mb-3 d-flex justify-content-between align-items-center">
    <a href="{{ route('settings.academic.assessments.show', $assessment) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Assessment
    </a>
    <div class="d-flex gap-2">
        <a href="{{ route('settings.academic.assessments.marks-sheet.export', $assessment) }}" class="btn btn-sm btn-outline-success">
            <i class="bi bi-filetype-csv me-1"></i>Export CSV
        </a>
        <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print Marks Sheet
        </button>
    </div>
</div>

@php
    $statusLabel = [
        'pass' => 'Pass',
        'fail' => 'Fail',
        'absent' => 'Absent',
        'incomplete' => 'Incomplete',
        'not_entered' => 'Not entered',
        'not_eligible' => 'Not eligible',
    ];
@endphp

<div class="sheet">
    <div class="report-header">
        <div class="institute-name">{{ $institute?->name ?? 'AccumenAI' }}</div>
        @if ($institute?->address)
            <div class="institute-address">{{ $institute->address }}</div>
        @endif
        <div class="report-title">Assessment Marks Sheet</div>
    </div>

    <div class="meta-line mb-1">
        @if ($assessment->academicYear)
            <span><span class="label">Academic Year:</span> {{ $assessment->academicYear->name }}</span>
        @endif
        @if ($assessment->classGrade)
            <span><span class="label">Class / Grade:</span> {{ $assessment->classGrade->name }}</span>
        @endif
        @if ($assessment->academicGroup)
            <span><span class="label">Group / Stream:</span> {{ $assessment->academicGroup->name }}</span>
        @endif
        @if ($assessment->branch)
            <span><span class="label">Branch:</span> {{ $assessment->branch->name }}</span>
        @endif
        <span><span class="label">Assessment:</span> {{ $assessment->name }}</span>
        @if ($assessment->exam_date)
            <span><span class="label">Exam Date:</span> {{ $assessment->exam_date->format('M j, Y') }}</span>
        @endif
        <span><span class="label">Students:</span> {{ count($sheet['rows']) }}</span>
    </div>

    <div class="sheet-summary">
        <span class="chip">Total {{ count($sheet['rows']) }}</span>
        <span class="chip pass">Pass {{ $sheet['summary']['pass'] }}</span>
        <span class="chip fail">Fail {{ $sheet['summary']['fail'] }}</span>
        <span class="chip absent">Absent {{ $sheet['summary']['absent'] }}</span>
        <span class="chip incomplete">Incomplete {{ $sheet['summary']['incomplete'] }}</span>
        <span class="chip">Not entered {{ $sheet['summary']['not_entered'] }}</span>
    </div>

    <table class="sheet-table">
        <thead>
            <tr>
                <th style="width:32px">#</th>
                <th class="text-start">Student</th>
                @foreach ($sheet['subjects'] as $subjectConfig)
                    <th>
                        {{ $subjectConfig->subject?->name ?? ('Subject #'.$subjectConfig->subject_id) }}
                        <div class="text-muted fw-normal">/{{ rtrim(rtrim(number_format((float) $subjectConfig->components->sum('full_mark'), 2), '0'), '.') }}</div>
                    </th>
                @endforeach
                <th style="width:84px">Total</th>
                <th style="width:70px">%</th>
                <th style="width:96px">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sheet['rows'] as $index => $row)
                @php $student = $row['student']; @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="student">
                        <div class="student-name">{{ $student?->full_name ?? ('Student #'.$row['placement']->student_id) }}</div>
                        @if ($student && ($student->student_id || $student->reg_no))
                            <div class="student-id">{{ $student->student_id ?: '' }}{{ $student->student_id && $student->reg_no ? ' · ' : '' }}{{ $student->reg_no ?: '' }}</div>
                        @endif
                    </td>
                    @foreach ($sheet['subjects'] as $subjectConfig)
                        @php
                            $cell = $row['cells'][$subjectConfig->id] ?? null;
                        @endphp
                        <td>
                            @if ($cell === null)
                                <span class="text-muted">—</span>
                            @elseif ($cell['status'] === 'pass' || $cell['status'] === 'fail')
                                <div class="obtained">{{ rtrim(rtrim(number_format((float) $cell['total_obtained'], 2), '0'), '.') }}</div>
                                <span class="badge-status {{ $cell['status'] === 'pass' ? 'pass' : 'fail' }}">{{ ucfirst($cell['status']) }}</span>
                            @elseif ($cell['status'] === 'absent')
                                <span class="badge-status absent">Absent</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    @endforeach
                    <td>
                        <div class="obtained">{{ rtrim(rtrim(number_format((float) $row['totals']['total_obtained'], 2), '0'), '.') }}</div>
                        <div class="full">/{{ rtrim(rtrim(number_format((float) $row['totals']['total_full'], 2), '0'), '.') }}</div>
                    </td>
                    <td>
                        @if ($row['totals']['total_full'] > 0)
                            {{ rtrim(rtrim(number_format((float) $row['totals']['percentage'], 2), '0'), '.') }}%
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        <span class="badge-status {{ in_array($row['status'], ['pass', 'fail', 'absent'], true) ? $row['status'] : 'neutral' }}">
                            {{ $statusLabel[$row['status']] ?? $row['status'] }}
                        </span>
                        @if ($row['totals']['not_entered'] > 0)
                            <div class="full">{{ $row['totals']['not_entered'] }} subject(s) pending</div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $sheet['subjects']->count() + 4 }}" class="text-center text-muted py-4">
                        No students are currently placed in this class
                        @if ($assessment->academicGroup)/group @endif for the selected academic year.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="notes">
        Marks and results are derived live from the current entries. Per-student totals and percentages cover subjects with entered marks only;
        absent and not-yet-entered subjects are counted separately. Subject results follow each subject's configured pass rule.
    </div>

    <div class="signature-block">
        <div class="signature-item">
            <div class="line"></div>
            <div class="label">Class Teacher</div>
        </div>
        <div class="signature-item">
            <div class="line"></div>
            <div class="label">Head / Principal</div>
        </div>
        <div class="signature-item">
            <div class="line"></div>
            <div class="label">Institution Seal</div>
        </div>
    </div>

    <div class="text-center text-muted small mt-4">
        Generated {{ now()->format('F j, Y') }} · AccumenAI
    </div>
</div>

@endsection

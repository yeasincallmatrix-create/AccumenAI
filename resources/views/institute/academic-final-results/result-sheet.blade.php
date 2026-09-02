@extends('layouts.standalone')

@section('title', 'Result Sheet — '.$result->name.' — AccumenAI')
@section('page_title', 'Result Sheet')

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
    .grade {
        font-weight: 700;
    }
    .agg {
        color: #6c757d;
        font-size: 0.65rem;
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
    .badge-status.neutral { background: #e9ecef; color: #495057; }
    .verdict-chip {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 999px;
        font-size: 0.68rem;
        font-weight: 600;
    }
    .verdict-promoted { background: #d1e7dd; color: #0f5132; }
    .verdict-conditional { background: #fff3cd; color: #664d03; }
    .verdict-repeat { background: #f8d7da; color: #842029; }
    .verdict-not_promoted { background: #f8d7da; color: #842029; }
    .verdict-completed { background: #cff4fc; color: #055160; }
    .verdict-graduated { background: #cff4fc; color: #055160; }
    .verdict-pending { background: #e2e3e5; color: #41464b; }
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
    <a href="{{ route('settings.academic.final-results.show', $result) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Result
    </a>
    <div class="d-flex gap-2">
        <a href="{{ route('settings.academic.final-results.export', $result) }}" class="btn btn-sm btn-outline-success">
            <i class="bi bi-filetype-csv me-1"></i>Export CSV
        </a>
        <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print Result Sheet
        </button>
    </div>
</div>

@php
    $hasOptional = $rows->contains(fn ($row) => collect($row['cells'])->contains(fn ($cell) => $cell->optional));
    $hasExcluded = $rows->contains(fn ($row) => collect($row['cells'])->contains(fn ($cell) => $cell->grade !== null && ! $cell->gpa_included));
@endphp

<div class="sheet">
    <div class="report-header">
        <div class="institute-name">{{ $institute?->name ?? 'AccumenAI' }}</div>
        @if ($institute?->address)
            <div class="institute-address">{{ $institute->address }}</div>
        @endif
        <div class="report-title">Class / Group Result Sheet</div>
    </div>

    <div class="meta-line mb-3">
        @if ($result->scheme?->academicYear)
            <span><span class="label">Academic Year:</span> {{ $result->scheme->academicYear->name }}</span>
        @endif
        @if ($result->scheme?->classGrade)
            <span><span class="label">Class / Grade:</span> {{ $result->scheme->classGrade->name }}</span>
        @endif
        @if ($result->scheme?->academicGroup)
            <span><span class="label">Group / Stream:</span> {{ $result->scheme->academicGroup->name }}</span>
        @endif
        @if ($result->scheme?->branch)
            <span><span class="label">Branch:</span> {{ $result->scheme->branch->name }}</span>
        @endif
        <span><span class="label">Result:</span> {{ $result->name }}</span>
        @if ($result->published_at)
            <span><span class="label">Published:</span> {{ $result->published_at->format('M j, Y') }}</span>
        @endif
        <span><span class="label">Students:</span> {{ $rows->count() }}</span>
    </div>

    <table class="sheet-table">
        <thead>
            <tr>
                <th style="width:32px">#</th>
                <th class="text-start">Student</th>
                @foreach ($subjects as $subject)
                    <th>
                        {{ $subject->name }}
                        @if ($rows->contains(fn ($row) => isset($row['cells'][$subject->id]) && $row['cells'][$subject->id]->optional))
                            <span class="text-muted">(O)</span>
                        @endif
                    </th>
                @endforeach
                <th style="width:70px">GPA</th>
                <th style="width:44px">Pass/Fail</th>
                <th style="width:96px">Promotion</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                @php
                    $student = $row['student'];
                    $gpaNumeric = is_numeric($row['gpa']);
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="student">
                        <div class="student-name">{{ $student->full_name }}</div>
                        @if ($student->student_id || $student->reg_no)
                            <div class="student-id">{{ $student->student_id ?: '' }}{{ $student->student_id && $student->reg_no ? ' · ' : '' }}{{ $student->reg_no ?: '' }}</div>
                        @endif
                    </td>
                    @foreach ($subjects as $subject)
                        @php
                            $cell = $row['cells'][$subject->id] ?? null;
                        @endphp
                        <td>
                            @if ($cell !== null && $cell->grade !== null)
                                <div class="grade">{{ $cell->grade }}</div>
                                @if ($cell->aggregate !== null)
                                    <div class="agg">{{ rtrim(rtrim(number_format($cell->aggregate, 2), '0'), '.') }}%</div>
                                @endif
                                @if ($cell->subject_status === 'PASS')
                                    <span class="badge-status pass">Pass</span>
                                @elseif ($cell->subject_status === 'FAIL')
                                    <span class="badge-status fail">Fail</span>
                                @else
                                    <span class="badge-status neutral">—</span>
                                @endif
                            @elseif ($cell !== null)
                                @php
                                    $statusText = [
                                        'computed' => '—',
                                        'incomplete' => 'Incomplete',
                                        'absent_only' => 'Absent',
                                        'not_eligible' => '—',
                                        'no_grade_scale' => 'No scale',
                                        'no_band' => 'No band',
                                    ];
                                @endphp
                                <span class="text-muted">{{ $statusText[$cell->status] ?? '—' }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    @endforeach
                    <td class="fw-semibold">
                        @if ($gpaNumeric)
                            {{ number_format($row['gpa'], 2) }}
                        @elseif ($row['gpa_status'] === 'computed')
                            —
                        @else
                            <span class="text-muted" title="{{ $row['gpa_reason'] }}">Unavailable</span>
                        @endif
                    </td>
                    <td>
                        @if ($gpaNumeric)
                            @if ((int) $row['failed_count'] > 0)
                                <span class="badge-status fail">Fail</span>
                            @else
                                <span class="badge-status pass">Pass</span>
                            @endif
                        @else
                            <span class="badge-status neutral">—</span>
                        @endif
                    </td>
                    <td>
                        @if ($row['promotion'])
                            @php
                                $promotion = $row['promotion'];
                                $label = ucfirst(str_replace('_', ' ', $promotion->decision));
                                $target = $promotion->nextPlacement?->academicYear?->name
                                    ?: ($promotion->targetClassGrade?->name ?? null);
                            @endphp
                            <span class="verdict-chip verdict-{{ $promotion->decision }}">{{ $label }}</span>
                            @if ($target)
                                <div class="agg">→ {{ $target }}</div>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $subjects->count() + 5 }}" class="text-center text-muted py-4">
                        No students are part of the published result snapshot.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="notes">
        GPA, grades and pass/fail are read from the frozen published snapshot of this result and do not change with later configuration.
        @if ($hasOptional || $hasExcluded)
            <br>
            @if ($hasOptional)
                (O) = Optional
            @endif
            @if ($hasOptional && $hasExcluded)
                ·
            @endif
            @if ($hasExcluded)
                * = Not counted in GPA
            @endif
        @endif
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
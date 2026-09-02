@extends('layouts.standalone')

@section('title', 'Academic Transcript — '.$student->full_name.' — AccumenAI')
@section('page_title', 'Academic Transcript')

@push('styles')
<style>
    .transcript-sheet {
        max-width: 820px;
        margin: 24px auto;
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 10px;
        padding: 36px 40px;
        color: #212529;
    }
    .report-header {
        display: flex;
        align-items: center;
        gap: 16px;
        justify-content: center;
        text-align: center;
        border-bottom: 3px double #212529;
        padding-bottom: 16px;
        margin-bottom: 24px;
        flex-direction: column;
    }
    .report-header .institute-name {
        font-size: 1.55rem;
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
    .report-meta {
        font-size: 0.9rem;
    }
    .report-meta td {
        padding: 2px 0;
        vertical-align: top;
    }
    .report-meta td:first-child {
        color: #6c757d;
        width: 170px;
    }
    .identity {
        display: flex;
        gap: 20px;
        align-items: flex-start;
        margin-bottom: 24px;
    }
    .identity .photo {
        width: 84px;
        height: 104px;
        object-fit: cover;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        background: #f8f9fa;
        flex-shrink: 0;
    }
    .identity .photo-placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        color: #adb5bd;
        font-size: 2rem;
    }
    .identity h5 {
        margin-bottom: 4px;
    }
    .grade-cell {
        text-align: center;
    }
    .year-section {
        margin: 28px 0 0;
        padding-top: 18px;
        border-top: 2px solid #e9ecef;
    }
    .year-section:first-of-type {
        margin-top: 20px;
    }
    .year-title {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 8px;
        margin-bottom: 10px;
    }
    .year-title .year-name {
        font-size: 1.05rem;
        font-weight: 700;
    }
    .year-title .class-name {
        color: #6c757d;
    }
    .verdict-chip {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 999px;
        font-size: 0.82rem;
        font-weight: 600;
    }
    .verdict-promoted { background: #d1e7dd; color: #0f5132; }
    .verdict-conditional { background: #fff3cd; color: #664d03; }
    .verdict-repeat { background: #f8d7da; color: #842029; }
    .verdict-not_promoted { background: #f8d7da; color: #842029; }
    .verdict-completed { background: #cff4fc; color: #055160; }
    .verdict-graduated { background: #cff4fc; color: #055160; }
    .verdict-pending { background: #e2e3e5; color: #41464b; }
    .signature-block {
        margin-top: 44px;
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
        height: 48px;
    }
    .signature-item .label {
        font-size: 0.82rem;
        color: #6c757d;
    }

    @media print {
        .transcript-sheet {
            margin: 0;
            border: none;
            border-radius: 0;
            padding: 8px 0;
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
    <a href="{{ route('students.academic-history', $student) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Academic History
    </a>
    <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
        <i class="bi bi-printer me-1"></i>Print Transcript
    </button>
</div>

<div class="transcript-sheet">
    <div class="report-header">
        <div class="institute-name">{{ $institute?->name ?? 'AccumenAI' }}</div>
        @if ($institute?->address)
            <div class="institute-address">{{ $institute->address }}</div>
        @endif
        <div class="report-title">Official Academic Transcript</div>
    </div>

    <div class="identity">
        @if ($student->photo)
            <img src="{{ $student->photo_url }}" class="photo" alt="{{ $student->full_name }}">
        @else
            <div class="photo photo-placeholder"><i class="bi bi-person"></i></div>
        @endif
        <div class="flex-grow-1">
            <h5>{{ $student->full_name }}</h5>
            <div class="small text-muted mb-2">
                @if ($student->student_id)Student ID: <span class="fw-semibold text-body">{{ $student->student_id }}</span>@endif
                @if ($student->reg_no)@if ($student->student_id) · @endif Registration: <span class="fw-semibold text-body">{{ $student->reg_no }}</span>@endif
            </div>
            <table class="report-meta w-100">
                @if ($student->father_name)
                    <tr><td>Father's Name</td><td>{{ $student->father_name }}</td></tr>
                @endif
                @if ($student->mother_name)
                    <tr><td>Mother's Name</td><td>{{ $student->mother_name }}</td></tr>
                @endif
                @if ($student->dob)
                    <tr><td>Date of Birth</td><td>{{ $student->dob->format('F j, Y') }}</td></tr>
                @endif
                @if ($student->admission_date)
                    <tr><td>Admission Date</td><td>{{ $student->admission_date->format('F j, Y') }}</td></tr>
                @endif
            </table>
        </div>
    </div>

    @if (! empty($cgpa) && $cgpa['cumulative_gpa'] !== null)
        <div class="alert alert-success d-flex align-items-center gap-3 py-3 px-4 mb-4" style="border-left: 4px solid #198754;">
            <div>
                <div class="small text-success fw-semibold text-uppercase tracking-wide mb-1">Cumulative GPA (CGPA)</div>
                <div class="fs-4 fw-bold">{{ number_format($cgpa['cumulative_gpa'], 2) }}</div>
            </div>
            <div class="ms-auto text-end">
                <div class="small text-muted">{{ ucfirst(str_replace('_', ' ', $cgpa['mode'])) }} Mode</div>
                <div class="small text-muted">{{ $cgpa['periods_completed'] }} published {{ Str::plural('period', $cgpa['periods_completed']) }}</div>
            </div>
        </div>
    @endif

    @if ($timeline->isEmpty())
        <div class="text-center text-muted py-5">
            <i class="bi bi-calendar-x fs-4 d-block mb-2"></i>
            No academic years recorded for this student yet.
        </div>
    @else
        {{-- Summary of all years --}}
        <table class="table table-bordered table-sm align-middle mb-3">
            <thead>
                <tr class="table-light">
                    <th>Academic Year</th>
                    <th>Class / Grade</th>
                    <th>Group</th>
                    <th>Result</th>
                    <th class="grade-cell">Aggregate GPA</th>
                    <th class="grade-cell">Result</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($timeline as $entry)
                    @php
                        $placement = $entry['placement'];
                        $snapshot = $entry['snapshot'];
                        $promotion = $entry['promotion'];
                    @endphp
                    <tr>
                        <td class="fw-semibold">{{ $placement->academicYear?->name ?? 'Year #' . $placement->academic_year_id }}</td>
                        <td>{{ $placement->classGrade?->name ?? 'Class removed' }}</td>
                        <td>{{ $placement->academicGroup?->name ?? '' }}</td>
                        <td>{{ $snapshot && $entry['result'] ? $entry['result']->name : '—' }}</td>
                        <td class="grade-cell fw-semibold">
                            @if ($snapshot && is_numeric($snapshot->gpa))
                                {{ number_format($snapshot->gpa, 2) }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="grade-cell">
                            @if ($promotion)
                                @php
                                    $label = ucfirst(str_replace('_', ' ', $promotion->decision));
                                @endphp
                                <span class="verdict-chip verdict-{{ $promotion->decision }}">{{ $label }}</span>
                            @elseif ($snapshot)
                                <span class="badge text-bg-success">Passed</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Per-year detail --}}
        @foreach ($timeline as $entry)
            @php
                $placement = $entry['placement'];
                $snapshot = $entry['snapshot'];
                $rows = $entry['rows']->sortBy(fn ($row) => $row->subject?->name ?? 'ZZ' . $row->subject_id);
                $promotion = $entry['promotion'];
            @endphp
            <div class="year-section">
                <div class="year-title">
                    <span class="year-name">{{ $placement->academicYear?->name ?? 'Year #' . $placement->academic_year_id }}</span>
                    <span class="class-name">
                        {{ $placement->classGrade?->name ?? 'Class removed' }}
                        @if ($placement->academicGroup) · {{ $placement->academicGroup->name }} @endif
                    </span>
                    <span class="badge text-bg-light border ms-auto">{{ $snapshot ? 'Published' : 'No published result' }}</span>
                </div>

                @if (! $snapshot)
                    <div class="text-muted small mb-2">No final result was published for this academic year.</div>
                @else
                    <table class="table table-bordered align-middle mb-2">
                        <thead>
                            <tr class="table-light">
                                <th>Subject</th>
                                <th class="grade-cell">Aggregate</th>
                                <th class="grade-cell">Grade</th>
                                <th class="grade-cell">Points</th>
                                <th class="grade-cell">Credits</th>
                                <th class="grade-cell">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td class="subject-name">
                                        {{ $row->subject?->name ?? 'Subject #' . $row->subject_id }}
                                        @if ($row->optional)
                                            <span class="badge text-bg-light border ms-1">Optional</span>
                                        @endif
                                        @if ($row->grade !== null && ! $row->gpa_included)
                                            <div class="small text-muted">Not counted in GPA</div>
                                        @endif
                                    </td>
                                    <td class="grade-cell">
                                        @if ($row->aggregate !== null)
                                            {{ rtrim(rtrim(number_format($row->aggregate, 2), '0'), '.') }}%
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="grade-cell fw-semibold">{{ $row->grade ?? '—' }}</td>
                                    <td class="grade-cell">
                                        {{ $row->grade_point !== null ? number_format($row->grade_point, 2) : '—' }}
                                    </td>
                                    <td class="grade-cell">
                                        @if ($row->credits !== null)
                                            {{ rtrim(rtrim(number_format($row->credits, 2), '0'), '.') }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="grade-cell">
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

                    <div class="d-flex flex-wrap gap-4 small">
                        @if (is_numeric($snapshot->gpa))
                            <div>
                                <span class="text-muted">Aggregate GPA: </span>
                                <span class="fw-semibold">{{ number_format($snapshot->gpa, 2) }}</span>
                            </div>
                        @endif
                        <div>
                            <span class="text-muted">Subjects passed: </span>
                            <span class="fw-semibold">{{ $snapshot->passed_count }}</span>
                        </div>
                        @if ((int) $snapshot->failed_count > 0)
                            <div>
                                <span class="text-muted">Subjects failed: </span>
                                <span class="fw-semibold text-danger">{{ $snapshot->failed_count }}</span>
                            </div>
                        @endif
                        @if ($promotion)
                            <div>
                                <span class="text-muted">Promotion: </span>
                                <span class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $promotion->decision)) }}</span>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    @endif

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
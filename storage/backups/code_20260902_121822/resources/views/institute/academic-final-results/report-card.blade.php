@extends('layouts.standalone')

@section('title', 'Report Card — '.$placement->student->full_name.' — AccumenAI')
@section('page_title', 'Official Report Card')

@push('styles')
<style>
    .report-sheet {
        max-width: 820px;
        margin: 24px auto;
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 10px;
        padding: 36px 40px;
        color: #212529;
    }
    .report-header {
        text-align: center;
        border-bottom: 3px double #212529;
        padding-bottom: 16px;
        margin-bottom: 24px;
    }
    .report-header .institute-name {
        font-size: 1.55rem;
        font-weight: 700;
        letter-spacing: 0.02em;
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
    }
    .report-meta td:first-child {
        color: #6c757d;
        width: 160px;
    }
    .grade-cell {
        text-align: center;
    }
    .subject-name {
        font-weight: 600;
    }
    .gpa-block {
        border-top: 2px solid #212529;
        border-bottom: 2px solid #212529;
        padding: 12px 8px;
    }
    .gpa-value {
        font-size: 2.2rem;
        font-weight: 800;
        color: #0d6efd;
    }
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
    .verdict-chip {
        display: inline-block;
        padding: 4px 14px;
        border-radius: 999px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    .verdict-promoted { background: #d1e7dd; color: #0f5132; }
    .verdict-conditional { background: #fff3cd; color: #664d03; }
    .verdict-repeat { background: #f8d7da; color: #842029; }
    .verdict-not_promoted { background: #f8d7da; color: #842029; }
    .verdict-completed { background: #cff4fc; color: #055160; }
    .verdict-graduated { background: #cff4fc; color: #055160; }
    .verdict-pending { background: #e2e3e5; color: #41464b; }

    @media print {
        .report-sheet {
            margin: 0;
            border: none;
            border-radius: 0;
            padding: 8px 0;
            box-shadow: none;
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
    <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
        <i class="bi bi-printer me-1"></i>Print Report Card
    </button>
</div>

<div class="report-sheet">
    <div class="report-header">
        <div class="institute-name">{{ $institute->name }}</div>
        <div class="report-title">Official Report Card — {{ $result->name }}</div>
        <div class="small text-muted mt-2">
            @if ($result->scheme?->academicYear) {{ $result->scheme->academicYear->name }} @endif
            @if ($result->scheme?->classGrade) · {{ $result->scheme->classGrade->name }} @endif
            @if ($result->scheme?->academicGroup) · {{ $result->scheme->academicGroup->name }} @endif
            @if ($result->scheme?->branch) · Branch: {{ $result->scheme->branch->name }} @endif
        </div>
    </div>

    <table class="report-meta w-100 mb-4">
        <tr>
            <td>Student</td>
            <td class="fw-semibold">{{ $placement->student->full_name }}</td>
        </tr>
        @if ($placement->student->student_id)
            <tr>
                <td>Student ID</td>
                <td>{{ $placement->student->student_id }}</td>
            </tr>
        @endif
        <tr>
            <td>Academic Year</td>
            <td>{{ $placement->academicYear?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td>Class / Grade</td>
            <td>{{ $placement->classGrade?->name ?? '—' }}
                @if ($placement->academicGroup) · {{ $placement->academicGroup->name }} @endif
            </td>
        </tr>
        <tr>
            <td>Published</td>
            <td>{{ $result->published_at?->format('F j, Y') ?? '—' }}</td>
        </tr>
    </table>

    <table class="table table-bordered align-middle mb-3">
        <thead>
            <tr class="table-light">
                <th style="width:36px">#</th>
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
                    <td class="text-muted">{{ $loop->iteration }}</td>
                    <td class="subject-name">
                        {{ $row->subject?->name ?? 'Subject #'.$row->subject_id }}
                        @if ($row->optional) <span class="badge text-bg-light border ms-1">Optional</span> @endif
                        @if ($row->grade !== null && ! $row->gpa_included)
                            <div class="small text-muted fw-normal"><i class="bi bi-info-circle me-1"></i>Not counted in GPA</div>
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
                    <td class="grade-cell">{{ $row->grade_point !== null ? number_format($row->grade_point, 2) : '—' }}</td>
                    <td class="grade-cell">{{ $row->credits !== null ? rtrim(rtrim(number_format($row->credits, 2), '0'), '.') : '—' }}</td>
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
                    <td colspan="7" class="text-center text-muted py-4">No subject records in the published snapshot.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="gpa-block d-flex justify-content-between align-items-center">
        <div class="small text-uppercase text-muted fw-semibold">
            Grade Point Average (GPA)
            @if ($snapshot)
                <div class="text-body mt-1 text-capitalize small fw-normal">
                    {{ $snapshot->passed_count }} passed
                    @if ((int) $snapshot->failed_count > 0)· {{ $snapshot->failed_count }} failed @endif
                </div>
            @endif
        </div>
        @if (is_numeric($snapshot?->gpa))
            <div class="gpa-value">{{ number_format($snapshot->gpa, 2) }}</div>
        @else
            <div class="fs-5 text-muted">—</div>
        @endif
    </div>

    @if ($promotion)
        @php
            $verdicts = [
                'promoted' => ['Promoted', 'verdict-promoted'],
                'conditional' => ['Conditional Promotion', 'verdict-conditional'],
                'repeat' => ['Repeat', 'verdict-repeat'],
                'not_promoted' => ['Not Promoted', 'verdict-not_promoted'],
                'completed' => ['Completed', 'verdict-completed'],
                'graduated' => ['Graduated', 'verdict-graduated'],
                'pending' => ['Pending', 'verdict-pending'],
            ];
            $verdict = $verdicts[$promotion->decision] ?? [ucfirst($promotion->decision), 'verdict-pending'];
        @endphp
        <div class="d-flex align-items-center gap-2 mt-3">
            <span class="small text-uppercase text-muted fw-semibold">Promotion</span>
            <span class="verdict-chip {{ $verdict[1] }}">{{ $verdict[0] }}</span>
            @if ($promotion->nextPlacement?->academicYear?->name)
                <span class="small text-muted">→ {{ $promotion->nextPlacement->academicYear->name }}
                    @if ($promotion->targetClassGrade) {{ $promotion->targetClassGrade->name }} @endif
                    @if ($promotion->targetAcademicGroup) · {{ $promotion->targetAcademicGroup->name }} @endif
                </span>
            @endif
        </div>
    @endif

    <div class="small text-muted mt-3 text-center">
        This is a computer-generated document from the published final result
        "{{ $result->name }}". The figures above are a frozen snapshot and cannot be altered.
    </div>

    <div class="signature-block">
        <div class="signature-item">
            <div class="line"></div>
            <div class="label">Class Teacher</div>
        </div>
        <div class="signature-item">
            <div class="line"></div>
            <div class="label">Principal / Head of Institute</div>
        </div>
    </div>
</div>

@endsection
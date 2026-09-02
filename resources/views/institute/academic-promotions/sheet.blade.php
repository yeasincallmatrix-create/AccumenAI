@extends('layouts.standalone')

@section('title', 'Promotion Sheet - AccumenAI')
@section('page_title', 'Promotion Sheet')

@php
    $verdictChip = [
        'promoted'     => ['Promoted', 'verdict-promoted'],
        'conditional'  => ['Conditional', 'verdict-conditional'],
        'repeat'       => ['Repeat', 'verdict-repeat'],
        'not_promoted' => ['Not Promoted', 'verdict-not_promoted'],
        'completed'    => ['Completed', 'verdict-completed'],
        'graduated'    => ['Graduated', 'verdict-graduated'],
        'pending'      => ['Pending', 'verdict-pending'],
    ];
    $counts = $decision->items->groupBy(fn ($item) => $item->decision);
@endphp

@push('styles')
<style>
    .sheet {
        max-width: 1100px;
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
        width: 200px;
    }
    .grade-cell {
        text-align: center;
    }
    .verdict-chip {
        display: inline-block;
        padding: 4px 14px;
        border-radius: 999px;
        font-size: 0.82rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .verdict-promoted { background: #d1e7dd; color: #0f5132; }
    .verdict-conditional { background: #fff3cd; color: #664d03; }
    .verdict-repeat { background: #f8d7da; color: #842029; }
    .verdict-not_promoted { background: #f8d7da; color: #842029; }
    .verdict-completed { background: #cff4fc; color: #055160; }
    .verdict-graduated { background: #cff4fc; color: #055160; }
    .verdict-pending { background: #e2e3e5; color: #41464b; }
    .summary-chip {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 999px;
        font-size: 0.82rem;
        font-weight: 600;
        background: #e9ecef;
        color: #495057;
    }
    .signature-block {
        margin-top: 44px;
        display: flex;
        justify-content: space-between;
        gap: 24px;
    }
    .signature-item {
        text-align: center;
        width: 220px;
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
        .sheet {
            margin: 0;
            border: none;
            border-radius: 0;
            padding: 8px 0;
            box-shadow: none;
            max-width: none;
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
    <a href="{{ route('settings.academic.promotions.decisions.show', $decision) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Decision
    </a>
    <div class="d-flex gap-2">
        <a href="{{ route('settings.academic.promotions.decisions.export', $decision) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
        <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print Sheet
        </button>
    </div>
</div>

<div class="sheet">
    <div class="report-header">
        <div class="institute-name">{{ $institute?->name ?? 'AccumenAI' }}</div>
        @if ($institute?->address)
            <div class="institute-address">{{ $institute->address }}</div>
        @endif
        <div class="report-title">Promotion Sheet</div>
    </div>

    <table class="report-meta w-100 mb-3">
        <tbody>
            <tr>
                <td>Academic Year</td>
                <td>{{ $decision->policy?->academicYear?->name ?? '&mdash;' }}</td>
                <td>Class / Grade</td>
                <td>{{ $decision->policy?->classGrade?->name ?? '&mdash;' }}</td>
            </tr>
            <tr>
                <td>Group / Stream</td>
                <td>{{ $decision->policy?->academicGroup?->name ?? 'Whole class' }}</td>
                <td>Source Result</td>
                <td>{{ $decision->result?->name ?? ('Result #'.$decision->result_id) }}</td>
            </tr>
            <tr>
                <td>Status</td>
                <td>{{ ucfirst((string) $decision->status) }}</td>
                <td>Approved</td>
                <td>{{ $decision->approved_at?->format('F j, Y') ?? '&mdash;' }}</td>
            </tr>
        </tbody>
    </table>

    @if ($decision->items->isEmpty())
        <div class="text-center text-muted py-5">
            <i class="bi bi-person-x fs-4 d-block mb-2"></i>
            No students recorded in this decision.
        </div>
    @else
        <div class="mb-2">
            @foreach ($counts as $verdict => $group)
                <span class="summary-chip">{{ ucfirst(str_replace('_', ' ', $verdict)) }}: {{ $group->count() }}</span>
            @endforeach
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle mb-0">
                <thead>
                    <tr class="table-light">
                        <th class="text-muted">#</th>
                        <th>Student</th>
                        <th>Student ID</th>
                        <th>Reg. No.</th>
                        <th>Source Class</th>
                        <th class="grade-cell">GPA</th>
                        <th class="grade-cell">Failed</th>
                        <th>Verdict</th>
                        <th>Reasons</th>
                        <th>Target</th>
                        @if ($decision->status === 'approved')
                            <th>Next Placement</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($decision->items as $item)
                        @php
                            $metric = $metrics[$item->placement_id] ?? [];
                            $next = $item->nextPlacement;
                        @endphp
                        <tr>
                            <td class="text-muted">{{ $loop->iteration }}</td>
                            <td>
                                <span class="fw-semibold">{{ $item->student?->full_name ?? ('Student #'.$item->student_id) }}</span>
                                <div class="text-muted small">{{ $item->placement?->academicYear?->name ?? '' }}</div>
                            </td>
                            <td class="small">{{ $item->student?->student_id ?? '&mdash;' }}</td>
                            <td class="small">{{ $item->student?->reg_no ?? '&mdash;' }}</td>
                            <td class="small">
                                {{ $item->placement?->classGrade?->name ?? '&mdash;' }}
                                @if ($item->placement?->academicGroup)
                                    <div class="text-muted small">{{ $item->placement->academicGroup->name }}</div>
                                @endif
                            </td>
                            <td class="grade-cell fw-semibold">
                                @if (is_numeric($metric['gpa'] ?? null))
                                    {{ number_format((float) $metric['gpa'], 2) }}
                                @else
                                    @if (($metric['gpa_status'] ?? null) === 'unavailable')<span class="text-muted small">Unavailable</span>@else<span>&mdash;</span>@endif
                                @endif
                            </td>
                            <td class="grade-cell">{{ $metric['failed_count'] ?? 0 }}</td>
                            <td>
                                <span class="verdict-chip {{ $verdictChip[$item->decision][1] ?? 'verdict-pending' }}">{{ $verdictChip[$item->decision][0] ?? ucfirst((string) $item->decision) }}</span>
                            </td>
                            <td class="small text-muted">
                                @if ($item->reasons)
                                    <ul class="mb-0 ps-3">
                                        @foreach ($item->reasons as $reason)
                                            <li>{{ $reason }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="small">
                                {{ $item->targetClassGrade?->name ?? '&mdash;' }}
                                @if ($item->targetAcademicGroup)
                                    <div class="text-muted small">{{ $item->targetAcademicGroup->name }}</div>
                                @endif
                            </td>
                            @if ($decision->status === 'approved')
                                <td class="small">
                                    @if ($next)
                                        {{ $next->academicYear?->name ?? '' }} - {{ $item->targetClassGrade?->name ?? '' }}
                                    @else
                                        <span class="text-muted">&mdash;</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="small text-muted mb-0 mt-2">
            Only approved decisions produce next-year placements. Source placements are never modified by promotion.
        </p>
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
        Generated {{ now()->format('F j, Y') }} - AccumenAI
    </div>
</div>

@endsection
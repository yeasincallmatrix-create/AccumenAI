@include('academic-attendance.reports._sheet')

@extends('layouts.standalone')

@section('title', 'Student Attendance Report — '.$student->full_name.' — AccumenAI')
@section('page_title', 'Student Attendance Report')

@section('content')

<div class="no-print mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <a href="{{ route('students.academic-history', $student) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Academic History
    </a>
    <div class="d-flex gap-2">
        <a href="{{ route('academic-attendance.reports.export.student', ['student' => $student] + request()->query()) }}" class="btn btn-sm btn-outline-success">
            <i class="bi bi-filetype-csv me-1"></i>Export CSV
        </a>
        <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print Report
        </button>
    </div>
</div>

<div class="no-print filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('academic-attendance.reports.student', $student) }}">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span flex-shrink-0" style="min-width:180px">
                <label class="form-label mb-1">Academic Year</label>
                <select class="form-select form-select-sm" name="academic_year_id">
                    <option value="">All academic years</option>
                    @foreach ($years as $year)
                        <option value="{{ $year->id }}" @selected((string) $year->id === (string) $selectedYearId)>{{ $year->name ?: ($year->code ?: 'Year #'.$year->id) }}@if($year->is_current) (Current)@endif</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:170px">
                <label class="form-label mb-1">Start date</label>
                <input type="date" name="start_date" value="{{ $start?->format('Y-m-d') }}" class="form-control form-control-sm">
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:170px">
                <label class="form-label mb-1">End date</label>
                <input type="date" name="end_date" value="{{ $end?->format('Y-m-d') }}" class="form-control form-control-sm">
            </div>
            <div class="filter-span flex-shrink-0">
                <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-search"></i> Apply</button>
            </div>
            <div class="filter-span flex-shrink-0">
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('academic-attendance.reports.student', $student) }}"><i class="bi bi-x-lg"></i> Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="report-sheet">
    <div class="report-header">
        <div class="institute-name">{{ $institute?->name ?? 'AccumenAI' }}</div>
        @if ($institute?->address)
            <div class="institute-address">{{ $institute->address }}</div>
        @endif
        <div class="report-title">Student Attendance Report</div>
    </div>

    <div class="meta-line mb-3">
        <span><span class="label">Student:</span> {{ $student->full_name }}</span>
        @if ($student->student_id)
            <span><span class="label">ID:</span> {{ $student->student_id }}</span>
        @endif
        @if ($selectedYearId)
            <span><span class="label">Academic Year:</span>
                @php $selYear = $years->firstWhere('id', $selectedYearId); @endphp
                {{ $selYear?->name ?: $selYear?->code ?: 'Year #'.$selectedYearId }}
            </span>
        @endif
        <span><span class="label">From:</span> {{ $start?->format('M j, Y') }}</span>
        <span><span class="label">To:</span> {{ $end?->format('M j, Y') }}</span>
    </div>

    @if ($report['valid'])
        <div class="summary-cards">
            <div class="summary-card">
                <div class="label">Present</div>
                <div class="value text-success">{{ $report['totals']['present'] }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Absent</div>
                <div class="value text-danger">{{ $report['totals']['absent'] }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Late</div>
                <div class="value text-warning">{{ $report['totals']['late'] }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Leave</div>
                <div class="value">{{ $report['totals']['leave'] }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Recorded days</div>
                <div class="value">{{ $report['totals']['total'] }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Attendance</div>
                <div class="value text-primary">
                    {{ $report['totals']['present_percent'] !== null ? number_format($report['totals']['present_percent'], 1).'%' : '—' }}
                </div>
            </div>
        </div>

        @if ($report['contexts']->isNotEmpty())
            <table id="studentContextsTable" class="sheet-table mb-3">
                <thead>
                    <tr>
                        <th>Academic Year</th>
                        <th>Class / Grade</th>
                        <th>Group / Stream</th>
                        <th>Status</th>
                        <th>Window</th>
                        <th>Days</th>
                        <th>Present</th>
                        <th>Absent</th>
                        <th>Late</th>
                        <th>Leave</th>
                        <th>Attendance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['contexts'] as $context)
                        @php
                            $placement = $context['placement'];
                            $summary = $context['summary'];
                        @endphp
                        <tr>
                            <td>{{ $placement->academicYear?->name ?: ('Year #'.$placement->academic_year_id) }}</td>
                            <td class="student">{{ $placement->classGrade?->name ?? ('Class #'.$placement->class_grade_id) }}</td>
                            <td>{{ $placement->academicGroup?->name ?? '—' }}</td>
                            <td>{{ ucfirst($placement->status) }}</td>
                            <td>{{ $context['window']['start']->format('M j, Y') }} — {{ $context['window']['end']->format('M j, Y') }}</td>
                            <td>{{ $summary['total'] }}</td>
                            <td>{{ $summary['present'] }}</td>
                            <td>{{ $summary['absent'] }}</td>
                            <td>{{ $summary['late'] }}</td>
                            <td>{{ $summary['leave'] }}</td>
                            <td>{{ $summary['present_percent'] !== null ? number_format($summary['present_percent'], 1).'%' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($report['unclassified'] > 0)
            <div class="notes mb-2">
                <i class="bi bi-exclamation-triangle me-1"></i>
                {{ $report['unclassified'] }} recorded attendance {{ $report['unclassified'] === 1 ? 'day is' : 'days are' }} not covered by any academic placement in this range
                (records before/after the student's academic years or with an unset year window) and are included in the totals above but not in any placement row.
            </div>
        @endif

        <table id="studentReportTable" class="sheet-table">
            <thead>
                <tr>
                    <th style="width:110px">Date</th>
                    <th>Context</th>
                    <th>Batch</th>
                    <th>Status</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $placement = $record->academic_placement;
                        $badge = [
                            'present' => ['Present', 'present'],
                            'absent'  => ['Absent', 'absent'],
                            'late'    => ['Late', 'late'],
                            'leave'   => ['Leave', 'leave'],
                        ][$record->status] ?? [ucfirst($record->status), 'na'];
                    @endphp
                    <tr>
                        <td class="text-nowrap">
                            {{ $record->class_date?->format('M j, Y') ?? $record->class_date }}
                            <span class="text-muted small d-block">{{ $record->class_date?->format('l') }}</span>
                        </td>
                        <td class="student">
                            @if ($placement)
                                {{ $placement->academicYear?->name ?: ('Year #'.$placement->academic_year_id) }}
                                · {{ $placement->classGrade?->name ?? ('Class #'.$placement->class_grade_id) }}
                                @if ($placement->academicGroup)
                                    · {{ $placement->academicGroup->name }}
                                @endif
                            @else
                                <span class="text-muted">No active placement</span>
                            @endif
                        </td>
                        <td>{{ $record->batch?->name ?? '—' }}</td>
                        <td>
                            <span class="status-chip {{ $badge[1] }}">{{ $badge[0] }}</span>
                        </td>
                        <td class="text-muted">{{ $record->remarks ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            No attendance records {{ $report['contexts']->isEmpty() ? 'in this range' : '' }}.
                            Missing records are not counted as absent.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($records->hasPages())
            <div class="no-print mt-2">
                {{ $records->links() }}
            </div>
        @endif

        <div class="notes">
            Attendance = present ÷ recorded days × 100. A day with no recorded status is neither present nor absent;
            recorded statuses cover present, absent, late and leave. Figures are read live from the attendance ledger.
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
    @else
        <p class="text-muted mb-0 py-3 text-center">
            <i class="bi bi-info-circle me-1"></i>{{ $report['message'] }}
        </p>
    @endif
</div>

@endsection
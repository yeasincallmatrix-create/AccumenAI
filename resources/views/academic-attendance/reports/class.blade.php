@include('academic-attendance.reports._sheet')

@extends('layouts.standalone')

@section('title', 'Class / Group Attendance Report — AccumenAI')
@section('page_title', 'Class / Group Attendance Report')

@section('content')

<div class="no-print mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <a href="{{ route('academic-attendance.reports.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Reports
    </a>
    <div class="d-flex gap-2">
        <a href="{{ route('academic-attendance.reports.export.class', request()->query()) }}" class="btn btn-sm btn-outline-success">
            <i class="bi bi-filetype-csv me-1"></i>Export CSV
        </a>
        <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print Report
        </button>
    </div>
</div>

<div class="no-print filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('academic-attendance.reports.class') }}">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span flex-shrink-0" style="min-width:180px">
                <label class="form-label mb-1">Academic Year</label>
                <select class="form-select form-select-sm" name="academic_year_id" onchange="this.form.submit()">
                    <option value="">Select year</option>
                    @foreach ($years as $year)
                        <option value="{{ $year->id }}" @selected((string) $year->id === (string) $yearId)>{{ $year->name ?: ($year->code ?: 'Year #'.$year->id) }}@if($year->is_current) (Current)@endif</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:170px">
                <label class="form-label mb-1">Class / Grade</label>
                <select class="form-select form-select-sm" name="class_grade_id" onchange="this.form.submit()">
                    <option value="">Select class</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected((string) $class->id === (string) $classId)>{{ $class->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:160px">
                <label class="form-label mb-1">Group / Stream</label>
                <select class="form-select form-select-sm" name="academic_group_id" onchange="this.form.submit()">
                    <option value="">All groups</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}" @selected((string) $group->id === (string) $groupId)>{{ $group->name }}</option>
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
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('academic-attendance.reports.class') }}"><i class="bi bi-x-lg"></i> Reset</a>
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
        <div class="report-title">Class / Group Attendance Report</div>
    </div>

    @if (! $report['valid'])
        <p class="text-muted mb-0 py-3 text-center">
            <i class="bi bi-info-circle me-1"></i>{{ $report['message'] }}
        </p>
    @elseif ($report['window']['start']->gt($report['window']['end']))
        <p class="text-muted mb-0 py-3 text-center">
            <i class="bi bi-info-circle me-1"></i>No attendance days in scope: the selected date range does not overlap the academic year's date window.
        </p>
    @else
        <div class="meta-line mb-3">
            <span><span class="label">Academic Year:</span> {{ $report['year']->name ?: ($report['year']->code ?: 'Year #'.$report['year']->id) }}</span>
            <span><span class="label">Class / Grade:</span> {{ $classes->firstWhere('id', $classId)?->name ?? ('Class #'.$classId) }}</span>
            <span><span class="label">Group / Stream:</span> {{ $groupId ? ($groups->firstWhere('id', $groupId)?->name ?? '—') : 'All groups' }}</span>
            <span><span class="label">From:</span> {{ $report['window']['start']->format('M j, Y') }}</span>
            <span><span class="label">To:</span> {{ $report['window']['end']->format('M j, Y') }}</span>
            <span><span class="label">Students:</span> {{ $report['roster']->total() }}</span>
        </div>

        <table id="classReportTable" class="sheet-table">
            <thead>
                <tr>
                    <th style="width:32px">#</th>
                    <th class="text-left">Student</th>
                    <th>Group</th>
                    <th>Days</th>
                    <th>Present</th>
                    <th>Absent</th>
                    <th>Late</th>
                    <th>Leave</th>
                    <th>Attendance</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['roster'] as $index => $entry)
                    @php
                        $student = $entry->student;
                        $summary = $report['byStudent']->get((int) $student->id)
                            ?? ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0, 'present_percent' => null];
                    @endphp
                    <tr>
                        <td>{{ $report['roster']->firstItem() + $index }}</td>
                        <td class="student">
                            <div class="student-name">{{ $student->full_name }}</div>
                            <div class="student-id">
                                @if ($student->student_id){{ $student->student_id }}@endif
                                @if ($student->branch){{ $student->student_id ? ' · ' : '' }}{{ $student->branch->name }}@endif
                            </div>
                        </td>
                        <td>{{ $entry->academicGroup?->name ?? '—' }}</td>
                        <td>{{ $summary['total'] }}</td>
                        <td>{{ $summary['present'] }}</td>
                        <td>{{ $summary['absent'] }}</td>
                        <td>{{ $summary['late'] }}</td>
                        <td>{{ $summary['leave'] }}</td>
                        <td>{{ $summary['present_percent'] !== null ? number_format($summary['present_percent'], 1).'%' : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No students placed in this year/class for the selected group.</td>
                    </tr>
                @endforelse
                @if ($report['roster']->isNotEmpty())
                    <tr class="totals-row">
                        <td colspan="3" class="text-left">Totals ({{ $report['roster']->total() }} students)</td>
                        <td>{{ $report['totals']['total'] }}</td>
                        <td>{{ $report['totals']['present'] }}</td>
                        <td>{{ $report['totals']['absent'] }}</td>
                        <td>{{ $report['totals']['late'] }}</td>
                        <td>{{ $report['totals']['leave'] }}</td>
                        <td>{{ $report['totals']['present_percent'] !== null ? number_format($report['totals']['present_percent'], 1).'%' : '—' }}</td>
                    </tr>
                @endif
            </tbody>
        </table>

        @if ($report['roster']->hasPages())
            <div class="no-print mt-2">
                {{ $report['roster']->links() }}
            </div>
        @endif

        <div class="notes">
            Attendance = present ÷ recorded days × 100, per student over the reported window. A student with no recorded days
            shows 0 days and "—" — unrecorded days are never treated as absences. Totals cover every student in the roster
            (across all pages) within the window.
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
    @endif
</div>

@endsection
@include('academic-attendance.reports._sheet')

@extends('layouts.standalone')

@section('title', 'Daily Attendance Report — AccumenAI')
@section('page_title', 'Daily Attendance Report')

@section('content')

<div class="no-print mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <a href="{{ route('academic-attendance.reports.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Reports
    </a>
    <div class="d-flex gap-2">
        <a href="{{ route('academic-attendance.reports.export.daily', request()->query()) }}" class="btn btn-sm btn-outline-success">
            <i class="bi bi-filetype-csv me-1"></i>Export CSV
        </a>
        <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print Report
        </button>
    </div>
</div>

<div class="no-print filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('academic-attendance.reports.daily') }}">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span flex-shrink-0" style="min-width:180px">
                <label class="form-label mb-1">Date</label>
                <input type="date" name="attendance_date" value="{{ $date?->format('Y-m-d') }}" class="form-control form-control-sm">
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:170px">
                <label class="form-label mb-1">Class / Grade</label>
                <select class="form-select form-select-sm" name="class_grade_id" onchange="this.form.submit()">
                    <option value="">All classes</option>
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
            <div class="filter-span flex-shrink-0">
                <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-search"></i> Apply</button>
            </div>
            <div class="filter-span flex-shrink-0">
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('academic-attendance.reports.daily') }}"><i class="bi bi-x-lg"></i> Reset</a>
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
        <div class="report-title">Daily Attendance Report</div>
    </div>

    @if (! $report['valid'])
        <p class="text-muted mb-0 py-3 text-center">
            <i class="bi bi-info-circle me-1"></i>{{ $report['message'] }}
        </p>
    @else
        <div class="meta-line mb-3">
            <span><span class="label">Date:</span> {{ $report['date']->format('M j, Y (l)') }}</span>
            <span><span class="label">Academic Year:</span> {{ $report['year']->name ?: ($report['year']->code ?: 'Year #'.$report['year']->id) }}</span>
            <span><span class="label">Class / Grade:</span> {{ $classId ? ($classes->firstWhere('id', $classId)?->name ?? ('Class #'.$classId)) : 'All classes' }}</span>
            <span><span class="label">Group / Stream:</span> {{ $groupId ? ($groups->firstWhere('id', $groupId)?->name ?? '—') : 'All groups' }}</span>
            <span><span class="label">Students:</span> {{ $report['roster']->total() }}</span>
            <span><span class="label">Marked:</span> {{ $report['totals']['marked'] }} / {{ $report['totals']['marked'] + $report['totals']['unmarked'] }}</span>
        </div>

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
                <div class="label">Not recorded</div>
                <div class="value text-secondary">{{ $report['totals']['unmarked'] }}</div>
            </div>
        </div>

        <table id="dailyReportTable" class="sheet-table">
            <thead>
                <tr>
                    <th style="width:32px">#</th>
                    <th class="text-left">Student</th>
                    <th>Class / Grade</th>
                    <th>Group / Stream</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['roster'] as $index => $entry)
                    @php
                        $student = $entry->student;
                        $row = $report['statuses']->get((int) $student->id);
                        $badge = $row !== null
                            ? [
                                'present' => ['Present', 'present'],
                                'absent'  => ['Absent', 'absent'],
                                'late'    => ['Late', 'late'],
                                'leave'   => ['Leave', 'leave'],
                            ][$row->status] ?? [ucfirst($row->status), 'na']
                            : ['Not recorded', 'na'];
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
                        <td>{{ $entry->classGrade?->name ?? ('Class #'.$entry->class_grade_id) }}</td>
                        <td>{{ $entry->academicGroup?->name ?? '—' }}</td>
                        <td>
                            <span class="status-chip {{ $badge[1] }}">{{ $badge[0] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No students placed in this context for the academic year covering this date.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($report['roster']->hasPages())
            <div class="no-print mt-2">
                {{ $report['roster']->links() }}
            </div>
        @endif

        <div class="notes">
            A student with no recorded status on this date shows "Not recorded" and is never counted as absent.
            Marked/Not recorded totals cover every student in the roster (across all pages). Absence is only ever an explicit
            "absent" record.
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
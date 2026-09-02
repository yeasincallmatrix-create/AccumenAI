@extends('layouts.institute')

@section('title', 'Academic Attendance — ' . $student->full_name . ' — AccumenAI')

@section('content')
@php
    $statusBadge = [
        'present' => ['Present', 'text-bg-success'],
        'absent'  => ['Absent', 'text-bg-danger'],
        'late'    => ['Late', 'text-bg-warning'],
        'leave'   => ['Leave', 'text-bg-info'],
    ];
@endphp

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
        <a href="{{ route('students.show', $student) }}" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="bi bi-arrow-left me-1"></i>Back to Profile
        </a>
        <h4 class="page-header-title">
            <i class="bi bi-clipboard-check me-1 text-primary"></i>Academic Attendance — {{ $student->full_name }}
            @if ($student->student_id)
                <span class="text-muted small fw-normal ms-1">{{ $student->student_id }}</span>
            @endif
        </h4>
    </div>
    @if ($academicYears->isNotEmpty())
        <form method="GET" action="{{ route('students.academic-attendance', $student) }}" class="d-flex align-items-center gap-2">
            <select name="academic_year_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                @foreach ($academicYears as $academicYear)
                    <option value="{{ $academicYear->id }}" @selected((int) $selectedYearId === (int) $academicYear->id)>
                        {{ $academicYear->name ?: ($academicYear->code ?: 'Year #' . $academicYear->id) }}
                    </option>
                @endforeach
            </select>
        </form>
    @endif
</div>

@if ($academicYears->isEmpty())
    <div class="admin-card mt-2">
        <div class="p-4 text-center text-muted">
            <i class="bi bi-calendar-x fs-3 d-block mb-2"></i>
            No academic placements recorded for this student yet.
        </div>
    </div>
@else
    @if (! $year || $year->start_date === null || $year->end_date === null)
        <div class="admin-card mt-2">
            <div class="p-4 text-center text-muted">
                <i class="bi bi-calendar-range fs-3 d-block mb-2"></i>
                The selected academic year has no start/end dates configured, so a reliable attendance summary cannot be shown.
            </div>
        </div>
    @else
        <div class="admin-card mt-2 mb-3">
            <div class="p-3">
                <div class="text-muted small text-uppercase mb-2">
                    {{ $year->name ?: $year->code }}
                    <span class="text-muted mx-1">·</span>{{ $year->start_date->format('M j, Y') }} — {{ $year->end_date->format('M j, Y') }}
                </div>
                <div class="d-flex flex-wrap gap-3">
                    @if ($summary !== null)
                        <div class="me-3">
                            <div class="text-muted small text-uppercase">Attendance</div>
                            <div class="fs-3 fw-bold text-primary">
                                {{ $summary['present_percent'] !== null ? number_format($summary['present_percent'], 1) . '%' : '—' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-muted small text-uppercase">Present</div>
                            <div class="fw-semibold text-success">{{ $summary['present'] }}</div>
                        </div>
                        <div>
                            <div class="text-muted small text-uppercase">Absent</div>
                            <div class="fw-semibold text-danger">{{ $summary['absent'] }}</div>
                        </div>
                        <div>
                            <div class="text-muted small text-uppercase">Late</div>
                            <div class="fw-semibold text-warning">{{ $summary['late'] }}</div>
                        </div>
                        <div>
                            <div class="text-muted small text-uppercase">Leave</div>
                            <div class="fw-semibold">{{ $summary['leave'] }}</div>
                        </div>
                        <div>
                            <div class="text-muted small text-uppercase">Total days</div>
                            <div class="fw-semibold">{{ $summary['total'] }}</div>
                        </div>
                    @else
                        <div class="text-muted small py-2">
                            <i class="bi bi-info-circle me-1"></i>No attendance records within this academic year's date range.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="admin-card">
            <div class="table-toolbar">
                <div class="toolbar-info">
                    <i class="bi bi-journal-check"></i>
                    <span class="fw-semibold">Attendance records</span>
                    <span class="text-muted ms-1 small">{{ $year->name }}</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Batch</th>
                            <th class="text-center">Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $record)
                            <tr>
                                <td class="text-nowrap">
                                    {{ $record->class_date?->format('M j, Y') ?? $record->class_date }}
                                    <span class="text-muted small d-block">{{ $record->class_date?->format('l') }}</span>
                                </td>
                                <td>{{ $record->batch?->name ?? '—' }}</td>
                                <td class="text-center">
                                    @php $badge = $statusBadge[$record->status] ?? [ucfirst($record->status), 'text-bg-secondary']; @endphp
                                    <span class="badge {{ $badge[1] }}">{{ $badge[0] }}</span>
                                </td>
                                <td class="text-muted">{{ $record->remarks ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No attendance records in this academic year.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($records && $records->hasPages())
                <div class="p-2">
                    {{ $records->links() }}
                </div>
            @endif
        </div>
    @endif
@endif

@endsection
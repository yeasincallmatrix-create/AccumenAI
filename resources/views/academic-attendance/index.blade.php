@extends('layouts.standalone')

@section('title', 'Mark Attendance — AccumenAI')
@section('page_title', 'Mark Attendance')

@section('content')

<div class="standalone-heading d-flex flex-wrap justify-content-between align-items-start gap-2">
    <div>
        <h4>Mark Attendance</h4>
        <p>Mark daily attendance for students placed in the selected academic year, class/grade and group/stream. Records are stored in the institute's attendance ledger keyed to each student's batch enrollment.</p>
    </div>
    <a href="{{ route('academic-attendance.reports.index') }}" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-clipboard-data me-1"></i>Attendance Reports
    </a>
</div>

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('academic-attendance.mark.index') }}">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span flex-shrink-0" style="min-width:180px">
                <label class="form-label mb-1">Academic Year</label>
                <select class="form-select form-select-sm" name="academic_year_id" onchange="this.form.submit()">
                    <option value="">Select year</option>
                    @foreach ($years as $year)
                        <option value="{{ $year->id }}" @selected((string) $year->id === (string) $yearId)>{{ $year->name }}@if($year->is_current) (Current)@endif</option>
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
                <label class="form-label mb-1">Date</label>
                <input type="date" name="attendance_date" value="{{ $date?->format('Y-m-d') }}" class="form-control form-control-sm" onchange="this.form.submit()">
            </div>
            <div class="filter-span flex-shrink-0">
                <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-search"></i> Load roster</button>
            </div>
        </div>
    </form>
</div>

@if ($context['valid'])
    <form method="POST" action="{{ route('academic-attendance.mark.store') }}">
        @csrf
        <input type="hidden" name="academic_year_id" value="{{ $yearId }}">
        <input type="hidden" name="class_grade_id" value="{{ $classId }}">
        <input type="hidden" name="academic_group_id" value="{{ $groupId ?? '' }}">
        <input type="hidden" name="attendance_date" value="{{ $date->format('Y-m-d') }}">

        <div class="admin-card mb-3">
            <div class="table-toolbar">
                <div class="toolbar-info">
                    <i class="bi bi-calendar-check"></i>
                    <span class="fw-semibold">{{ $context['year']->name }} — {{ $date->format('d M Y') }}</span>
                    <span class="badge text-bg-secondary badge-soft ms-2">{{ $context['roster']->count() }} students</span>
                </div>
            </div>

            @if ($context['summary'])
                <div class="px-3 pb-3 d-flex flex-wrap gap-2 small">
                    <span class="badge text-bg-secondary">Total {{ $context['summary']['total'] }}</span>
                    <span class="badge text-bg-success">Present {{ $context['summary']['present'] }}</span>
                    <span class="badge text-bg-danger">Absent {{ $context['summary']['absent'] }}</span>
                    <span class="badge text-bg-warning text-dark">Late {{ $context['summary']['late'] }}</span>
                    <span class="badge text-bg-info text-white">Leave {{ $context['summary']['leave'] }}</span>
                    @if ($context['summary']['present_percent'] !== null)
                        <span class="ms-auto text-muted">Present rate: {{ $context['summary']['present_percent'] }}%</span>
                    @endif
                </div>
            @endif

            <div class="table-responsive border-top">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="text-muted">#</th>
                            <th>Student</th>
                            <th>Batch</th>
                            <th style="min-width:320px">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($context['roster'] as $entry)
                            @php $student = $entry['student']; @endphp
                            <tr>
                                <td class="text-muted">{{ $loop->iteration }}</td>
                                <td>
                                    <span class="fw-semibold">{{ $student->full_name }}</span>
                                    @if ($student->student_id)
                                        <div class="text-muted small">{{ $student->student_id }}</div>
                                    @endif
                                    @if ($student->branch)
                                        <div class="text-muted small">{{ $student->branch->name }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($entry['can_mark'] && $entry['existing'])
                                        <span class="text-muted small">#{{ $entry['batch_id'] }}</span>
                                    @elseif ($entry['can_mark'])
                                        <span class="text-muted small">#{{ $entry['batch_id'] }}</span>
                                    @else
                                        <span class="text-muted small"><i class="bi bi-exclamation-triangle"></i> {{ $entry['reason'] }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($entry['can_mark'])
                                        <div class="btn-group" role="group" aria-label="{{ $student->full_name }} attendance">
                                            @foreach (['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'leave' => 'Leave'] as $value => $label)
                                                <input type="radio" class="btn-check" name="statuses[{{ $student->id }}]"
                                                       id="status-{{ $student->id }}-{{ $value }}" value="{{ $value }}"
                                                       @checked($entry['existing'] && $entry['existing']->status === $value)>
                                                <label class="btn btn-sm btn-outline-secondary" for="status-{{ $student->id }}-{{ $value }}">{{ $label }}</label>
                                            @endforeach
                                        </div>
                                    @elseif ($entry['existing'])
                                        <span class="badge text-bg-secondary">{{ ucfirst($entry['existing']->status) }}</span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No students in this year/class for the selected group.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($context['roster']->isNotEmpty())
                <div class="p-3 border-top d-flex justify-content-end">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-check2-square me-1"></i>Save attendance</button>
                </div>
            @endif
        </div>
    </form>
@elseif ($context['message'])
    <div class="admin-card">
        <p class="text-muted mb-0 py-3 text-center">
            <i class="bi bi-info-circle me-1"></i>{{ $context['message'] }}
        </p>
    </div>
@endif

@endsection
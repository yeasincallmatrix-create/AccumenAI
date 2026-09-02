@extends('layouts.institute')

@section('title', 'Student Analytics')

@section('content')

<x-academic.analytics.header
    title="Student Analytics"
    subtitle="Searchable student list with placement, promotion, frozen result, attendance and certificate status."
    export="{{ route('academic.analytics.students.export', request()->query()) }}"
/>

<form method="GET" class="mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
            <label class="form-label small mb-1">Search</label>
            <input type="text" name="term" value="{{ $filters['term'] ?? '' }}" class="form-control form-control-sm" placeholder="Name, student ID, registration number, phone">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All</option>
                @foreach ($options['statuses'] as $status)
                    <option value="{{ $status }}" @selected((string) ($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Branch</label>
            <select name="branch_id" class="form-select form-select-sm">
                <option value="">All</option>
                @foreach ($options['branches'] as $branch)
                    <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Course</label>
            <select name="course_id" class="form-select form-select-sm">
                <option value="">All</option>
                @foreach ($options['courses'] as $course)
                    <option value="{{ $course->id }}" @selected((string) ($filters['course_id'] ?? '') === (string) $course->id)>{{ $course->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Batch</label>
            <select name="batch_id" class="form-select form-select-sm">
                <option value="">All</option>
                @foreach ($options['batches'] as $batch)
                    <option value="{{ $batch->id }}" @selected((string) ($filters['batch_id'] ?? '') === (string) $batch->id)>{{ $batch->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Academic Year</label>
            <select name="academic_year_id" class="form-select form-select-sm">
                <option value="">All</option>
                @foreach ($options['years'] as $year)
                    <option value="{{ $year->id }}" @selected((string) ($filters['academic_year_id'] ?? '') === (string) $year->id)>{{ $year->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Class / Grade</label>
            <select name="class_grade_id" class="form-select form-select-sm">
                <option value="">All</option>
                @foreach ($options['classes'] as $class)
                    <option value="{{ $class->id }}" @selected((string) ($filters['class_grade_id'] ?? '') === (string) $class->id)>{{ $class->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Group / Stream</label>
            <select name="academic_group_id" class="form-select form-select-sm">
                <option value="">All</option>
                @foreach ($options['groups'] as $group)
                    <option value="{{ $group->id }}" @selected((string) ($filters['academic_group_id'] ?? '') === (string) $group->id)>{{ $group->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Admitted From</label>
            <input type="date" name="admission_from" value="{{ $filters['admission_from'] ?? '' }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Admitted To</label>
            <input type="date" name="admission_to" value="{{ $filters['admission_to'] ?? '' }}" class="form-control form-control-sm">
        </div>
        <div class="col-auto">
            <button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
        </div>
    </div>
</form>

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-people-fill"></i> Students
            <span class="badge text-bg-light border ms-2">{{ $data['total'] }} matched</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Branch</th>
                    <th>Year / Class</th>
                    <th>Placement</th>
                    <th>Promotion</th>
                    <th class="text-end">Passed / Failed</th>
                    <th class="text-end">Attendance</th>
                    <th>Certificate</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($data['rows'] as $row)
                    @php
                        $student = $row['student'];
                        $placement = $row['placement'];
                        $attendance = $row['attendance'];
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ route('students.show', $student) }}" class="fw-semibold text-decoration-none">{{ $student->full_name }}</a>
                            <div class="text-muted small">{{ $student->student_id }}</div>
                        </td>
                        <td>{{ $student->branch?->name ?? '—' }}</td>
                        <td>
                            @if ($placement)
                                {{ $placement->academicYear?->name }}
                                <div class="text-muted small">{{ $placement->classGrade?->name }}{{ $placement->academicGroup ? ' / '.$placement->academicGroup->name : '' }}</div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($placement)
                                @php
                                    $placementLabels = ['active' => 'Active', 'completed' => 'Completed', 'transferred' => 'Transferred', 'dropped' => 'Dropped'];
                                    $label = $placementLabels[$placement->status] ?? ucfirst($placement->status);
                                @endphp
                                <span class="badge {{ $placement->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $label }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($row['promotion'])
                                @php
                                    $outcomeLabels = ['promoted' => 'Promoted', 'not_promoted' => 'Not Promoted', 'conditional' => 'Conditional', 'repeat' => 'Repeat', 'completed' => 'Completed', 'graduated' => 'Graduated', 'pending' => 'Pending'];
                                    $outcomeLabel = $outcomeLabels[$row['promotion']] ?? ucfirst(str_replace('_', ' ', $row['promotion']));
                                @endphp
                                <span class="badge text-bg-light border">{{ $outcomeLabel }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if (($row['passed'] + $row['failed']) > 0)
                                <span class="text-success">{{ $row['passed'] }}</span> / <span class="text-danger">{{ $row['failed'] }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if ($attendance !== null && $attendance['total'] > 0)
                                {{ number_format($attendance['present_percent'], 1) }}%
                                <div class="text-muted small">{{ $attendance['present'] }}/{{ $attendance['total'] }}</div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($row['certificate_status'] === 'active')
                                <span class="badge text-bg-success">Issued</span>
                            @elseif ($row['certificate_status'] === 'pending')
                                <span class="badge text-bg-warning">Pending</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No students match the current filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3 border-top">
        {{ $data['rows']->withQueryString()->links() }}
    </div>
</div>

@endsection
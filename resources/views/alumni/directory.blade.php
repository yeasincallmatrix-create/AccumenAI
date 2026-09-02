@extends('layouts.institute')

@section('title', mawa_e('alumni.directory') . ' — AccumenAI')

@php
    $statusBadge = [
        'active'   => 'text-bg-success',
        'inactive' => 'text-bg-secondary',
    ];
@endphp

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('alumni.directory') }}</h4>
        <p class="page-header-desc">{{ mawa_e('alumni.alumni_profiles', ['count' => $alumni->total()]) }}</p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a class="btn btn-outline-primary" href="{{ route('alumni.index') }}">
            <i class="bi bi-speedometer2 me-1"></i>{{ mawa_e('alumni.nav_dashboard') }}
        </a>
        <a class="btn btn-primary" href="{{ route('alumni.create') }}">
            <i class="bi bi-plus-lg me-1"></i>{{ mawa_e('alumni.add_alumni') }}
        </a>
    </div>
</div>

<div class="admin-card">
    <form class="d-flex flex-wrap gap-2 mb-3 align-items-end" method="GET" action="{{ route('alumni.directory') }}">
        <div style="flex:1 1 260px;min-width:220px">
            <input type="text" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}"
                   placeholder="{{ mawa_e('alumni.search_directory_placeholder') }}">
        </div>
        <div style="width:160px">
            <select name="status" class="form-select">
                <option value="">{{ mawa_e('alumni.filter_all_statuses') }}</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ mawa_e('alumni.stat_active') }}</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ mawa_e('alumni.stat_inactive') }}</option>
            </select>
        </div>
        <div style="width:200px">
            <select name="completion_academic_year_id" class="form-select">
                <option value="">{{ mawa_e('alumni.filter_all_years') }}</option>
                @foreach ($academicYears as $year)
                    <option value="{{ $year->id }}" @selected((string) ($filters['completion_academic_year_id'] ?? '') === (string) $year->id)>{{ $year->name }}</option>
                @endforeach
            </select>
        </div>
        <div style="width:220px">
            <select name="completed_course_id" class="form-select">
                <option value="">{{ mawa_e('alumni.filter_all_courses') }}</option>
                @foreach ($courses as $course)
                    <option value="{{ $course->id }}" @selected((string) ($filters['completed_course_id'] ?? '') === (string) $course->id)>{{ $course->name }}</option>
                @endforeach
            </select>
        </div>
        <div style="width:200px">
            <select name="completed_batch_id" class="form-select">
                <option value="">{{ mawa_e('alumni.filter_all_batches') }}</option>
                @foreach ($batches as $batch)
                    <option value="{{ $batch->id }}" @selected((string) ($filters['completed_batch_id'] ?? '') === (string) $batch->id)>{{ $batch->name }}</option>
                @endforeach
            </select>
        </div>
        <div style="width:180px">
            <input type="number" name="graduation_year" class="form-control" min="2000" max="{{ now()->year }}"
                   value="{{ $filters['graduation_year'] ?? '' }}" placeholder="{{ mawa_e('alumni.grad_year') }}">
        </div>
        <div style="width:200px">
            <input type="text" name="current_occupation" class="form-control" value="{{ $filters['current_occupation'] ?? '' }}" placeholder="{{ mawa_e('alumni.th_occupation') }}">
        </div>
        <div style="width:200px">
            <input type="text" name="employer" class="form-control" value="{{ $filters['employer'] ?? '' }}" placeholder="{{ mawa_e('alumni.field_employer') }}">
        </div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>{{ mawa_e('alumni.search') }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('alumni.directory') }}" title="{{ mawa_e('alumni.reset_filters') }}">
            <i class="bi bi-arrow-counterclockwise"></i>
        </a>
        <div class="ms-auto d-flex gap-2">
            <a class="btn btn-outline-primary" href="{{ route('alumni.directory.export', request()->query()) }}" title="{{ mawa_e('alumni.export_csv') }}">
                <i class="bi bi-download me-1"></i>{{ mawa_e('alumni.export') }}
            </a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ mawa_e('alumni.th_reference') }}</th>
                    <th>{{ mawa_e('alumni.th_student') }}</th>
                    <th>{{ mawa_e('alumni.th_course_batch') }}</th>
                    <th>{{ mawa_e('alumni.th_completion_year') }}</th>
                    <th>{{ mawa_e('alumni.th_graduation_date') }}</th>
                    <th>{{ mawa_e('alumni.th_occupation') }}</th>
                    <th>{{ mawa_e('alumni.th_status') }}</th>
                    <th class="text-end">{{ mawa_e('alumni.th_actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($alumni as $entry)
                    <tr>
                        <td class="text-muted">{{ $entry->alumni_reference_number ?: '—' }}</td>
                        <td>
                            <a class="fw-semibold text-decoration-none" href="{{ route('alumni.show', $entry) }}">
                                {{ $entry->student->full_name ?: trim($entry->student->first_name.' '.$entry->student->last_name) }}
                            </a>
                            @if ($entry->student->reg_no)
                                <span class="text-muted small d-block">{{ $entry->student->reg_no }}</span>
                            @endif
                        </td>
                        <td>
                            {{ $entry->completedCourse?->name ?? '—' }}
                            @if ($entry->completedBatch?->name)
                                <span class="text-muted small d-block">{{ $entry->completedBatch->name }}</span>
                            @endif
                        </td>
                        <td>{{ $entry->completionAcademicYear?->name ?? '—' }}</td>
                        <td>{{ $entry->graduation_date?->format('d M Y') ?? '—' }}</td>
                        <td>{{ $entry->current_occupation ?: '—' }}</td>
                        <td>
                            <span class="badge {{ $statusBadge[$entry->status] ?? 'text-bg-secondary' }}">{{ ucfirst($entry->status) }}</span>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('alumni.show', $entry) }}" title="{{ mawa_e('alumni.view_profile') }}">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">{{ mawa_e('alumni.no_matches') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <nav class="mt-4 pt-2 d-flex flex-column align-items-center gap-2">
        {{ $alumni->links('pagination::bootstrap-5') }}
        @if ($alumni->total() > 0)
            <span class="text-muted small">
                {{ mawa_e('alumni.showing', ['from' => $alumni->firstItem() ?? 0, 'to' => $alumni->lastItem() ?? 0, 'total' => $alumni->total()]) }}
            </span>
        @endif
    </nav>
</div>
@endsection

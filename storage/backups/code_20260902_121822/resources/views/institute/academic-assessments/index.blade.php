@extends('layouts.standalone')

@section('title', 'Academic Assessments — AccumenAI')
@section('page_title', 'Academic Assessments')

@php
    $statusBadge = [
        'draft'     => 'text-bg-secondary',
        'scheduled' => 'text-bg-info',
        'open'      => 'text-bg-success',
        'completed' => 'text-bg-primary',
        'cancelled' => 'text-bg-danger',
    ];
@endphp

@section('content')

@include('institute.academic._step-nav', ['currentStep'=>4,'currentLabel'=>'Assessments','prevRoute'=>'settings.academic.placements.index','prevLabel'=>'3 · Placements','nextRoute'=>'settings.academic.aggregations.index','nextLabel'=>'5 · Weight Schemes'])
@include('institute.academic._dependency-banner', ['context'=>'assessments'])

<div class="standalone-heading">
    <h4>4 · Assessments — Academic Assessments</h4>
    <p>Step 4 of 7 — Configure exams/assessments for an academic year, class/grade and group/stream. Requires <a href="{{ route('settings.academic.placements.index') }}">3 · Placements</a>. Each subject carries dynamic components with full and pass marks.</p>
    <a href="{{ route('settings.academic.assessments.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>New Assessment
    </a>
</div>

{{-- Sub-tab: Assessments | Marks Entry --}}
<div class="mb-3">
    <ul class="nav nav-pills nav-pills-sm">
        <li class="nav-item">
            <a class="nav-link {{ request()->query('view') !== 'marks' ? 'active' : '' }}" href="{{ route('settings.academic.assessments.index') }}">
                <i class="bi bi-clipboard-check me-1"></i>Assessments
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->query('view') === 'marks' ? 'active' : '' }}" href="{{ route('settings.academic.assessments.index', ['view'=>'marks']) }}">
                <i class="bi bi-pencil-square me-1"></i>Marks Entry
            </a>
        </li>
    </ul>
    @if(request()->query('view') === 'marks')
        <div class="alert alert-info py-2 small mt-2 mb-0">
            <i class="bi bi-info-circle me-1"></i> Select an assessment below to enter marks. Marks are stored per subject/component and feed into <a href="{{ route('settings.academic.aggregations.index') }}">5 · Weight Schemes</a>.
        </div>
    @endif
</div>

@if (empty($classes) || $academicYears->isEmpty())
    <div class="admin-card">
        <p class="text-muted mb-0 py-3 text-center">
            <i class="bi bi-info-circle"></i>
            @if (empty($classes))
                No classes have been configured in your country's academic structure yet. Please contact your platform administrator.
            @else
                Add at least one academic year before creating assessments.
            @endif
        </p>
    </div>
@endif

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('settings.academic.assessments.index') }}">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span flex-shrink-0" style="min-width:180px">
                <label class="form-label mb-1">Academic Year</label>
                <select class="form-select form-select-sm" name="academic_year_id" onchange="this.form.submit()">
                    <option value="">All years</option>
                    @foreach ($academicYears as $year)
                        <option value="{{ $year->id }}" @selected((string) $year->id === (string) $academicYearId)>{{ $year->name }} @if($year->is_current)(Current)@endif</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:170px">
                <label class="form-label mb-1">Class / Grade</label>
                <select class="form-select form-select-sm" name="class_grade_id" onchange="this.form.submit()">
                    <option value="">All classes</option>
                    @foreach ($classes as $entry)
                        <option value="{{ $entry['class_grade']->id }}" @selected((string) $entry['class_grade']->id === (string) $classGradeId)>{{ $entry['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:150px">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    @foreach (['draft' => 'Draft', 'scheduled' => 'Scheduled', 'open' => 'Open', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span flex-shrink-0">
                <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-search"></i> Filter</button>
            </div>
        </div>
    </form>
</div>

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-muted">#</th>
                    <th>Assessment</th>
                    <th>Academic Year</th>
                    <th>Class / Grade</th>
                    <th>Group</th>
                    <th>Status</th>
                    <th>Subjects</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($assessments as $assessment)
                    <tr>
                        <td class="text-muted">{{ $assessments->firstItem() + $loop->index }}</td>
                        <td>
                            <span class="fw-semibold">{{ $assessment->name }}</span>
                            @if ($assessment->assessmentType)
                                <div class="text-muted small">{{ $assessment->assessmentType->name }}</div>
                            @endif
                            @if ($assessment->branch)
                                <div class="text-muted small">{{ $assessment->branch->name }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $assessment->academicYear?->name ?? '—' }}
                            @if ($assessment->academicYear?->is_current)
                                <span class="badge text-bg-primary ms-1">Current</span>
                            @endif
                        </td>
                        <td>{{ $assessment->classGrade?->name ?? '—' }}</td>
                        <td>{{ $assessment->academicGroup?->name ?? '<span class="text-muted">—</span>' }}</td>
                        <td>
                            <span class="badge {{ $statusBadge[$assessment->status] ?? 'text-bg-secondary' }}">{{ ucfirst($assessment->status) }}</span>
                        </td>
                        <td>
                            {{ $assessment->subjects_count }}
                            <a href="{{ route('settings.academic.assessments.show', $assessment) }}" class="small text-muted ms-1">view</a>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('settings.academic.assessments.edit', $assessment) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <form method="POST" action="{{ route('settings.academic.assessments.destroy', $assessment) }}" class="d-inline" data-ajax-delete="1" data-confirm="Remove this assessment? Its subject/component configuration will be deleted.">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No academic assessments yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($assessments->hasPages())
        <div class="p-2 border-top">{{ $assessments->links() }}</div>
    @endif
</div>

@endsection
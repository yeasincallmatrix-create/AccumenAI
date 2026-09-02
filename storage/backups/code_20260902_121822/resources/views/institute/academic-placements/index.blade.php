@extends('layouts.standalone')

@section('title', 'Academic Placements — AccumenAI')
@section('page_title', 'Academic Placements')

@php
    $statusBadge = [
        'active'      => 'text-bg-success',
        'completed'   => 'text-bg-primary',
        'transferred' => 'text-bg-info',
        'dropped'     => 'text-bg-secondary',
    ];
@endphp

@section('content')

@include('institute.academic._step-nav', ['currentStep'=>3,'currentLabel'=>'Placements','prevRoute'=>'settings.academic.academic-years.index','prevLabel'=>'2 · Academic Years','nextRoute'=>'settings.academic.assessments.index','nextLabel'=>'4 · Assessments'])
@include('institute.academic._dependency-banner', ['context'=>'placements'])

<div class="standalone-heading">
    <h4>3 · Placements — Academic Placements</h4>
    <p>Step 3 of 7 — Assign students to an academic year, class/grade and group/stream, then let them select their subjects. Requires <a href="{{ route('settings.academic.index') }}">1 · Structure</a> and <a href="{{ route('settings.academic.academic-years.index') }}">2 · Academic Years</a>. This is separate from batch enrollment.</p>
    <a href="{{ route('settings.academic.placements.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>New Placement
    </a>
</div>

@if (empty($classes) || $academicYears->isEmpty())
    <div class="admin-card">
        <p class="text-muted mb-0 py-3 text-center">
            <i class="bi bi-info-circle"></i>
            @if (empty($classes))
                No classes have been configured in your country's academic structure yet. Please contact your platform administrator.
            @else
                Add at least one academic year below before creating placements.
            @endif
        </p>
    </div>
@endif

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('settings.academic.placements.index') }}">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span flex-fill" style="min-width:180px">
                <label class="form-label mb-1">Search</label>
                <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Name, student ID or registration number">
            </div>
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
            <div class="filter-span flex-shrink-0" style="min-width:160px">
                <label class="form-label mb-1">Group / Stream</label>
                <select class="form-select form-select-sm" name="academic_group_id" onchange="this.form.submit()">
                    <option value="">All groups</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}" @selected((string) $group->id === (string) $academicGroupId)>{{ $group->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:170px">
                <label class="form-label mb-1">Branch</label>
                <select class="form-select form-select-sm" name="branch_id" onchange="this.form.submit()">
                    <option value="">All branches</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) $branch->id === (string) $branchId)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:150px">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    @foreach (['active' => 'Active', 'completed' => 'Completed', 'transferred' => 'Transferred', 'dropped' => 'Dropped'] as $value => $label)
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

@error('placement')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-muted">#</th>
                    <th>Student</th>
                    <th>Academic Year</th>
                    <th>Class / Grade</th>
                    <th>Group / Stream</th>
                    <th>Status</th>
                    <th>Subjects</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($placements as $placement)
                    <tr>
                        <td class="text-muted">{{ $placements->firstItem() + $loop->index }}</td>
                        <td>
                            <span class="fw-semibold">{{ $placement->student?->full_name ?? '—' }}</span>
                            @if ($placement->student?->student_id)
                                <div class="text-muted small">{{ $placement->student->student_id }}</div>
                            @endif
                            @if ($placement->student?->branch)
                                <div class="text-muted small">{{ $placement->student->branch->name }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $placement->academicYear?->name ?? '—' }}
                            @if ($placement->academicYear?->is_current)
                                <span class="badge text-bg-primary ms-1">Current</span>
                            @endif
                        </td>
                        <td>{{ $placement->classGrade?->name ?? '—' }}</td>
                        <td>{{ $placement->academicGroup?->name ?? '<span class="text-muted">—</span>' }}</td>
                        <td>
                            <span class="badge {{ $statusBadge[$placement->status] ?? 'text-bg-secondary' }}">{{ ucfirst($placement->status) }}</span>
                        </td>
                        <td>
                            {{ $placement->selections_count }}
                            <a href="{{ route('settings.academic.placements.show', $placement) }}" class="small text-muted ms-1">view</a>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('settings.academic.placements.edit', $placement) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <form method="POST" action="{{ route('settings.academic.placements.destroy', $placement) }}" class="d-inline" data-ajax-delete="1" data-confirm="Remove this academic placement?">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No academic placements yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($placements->hasPages())
        <div class="p-2 border-top">{{ $placements->links() }}</div>
    @endif
</div>

<div id="academic-years" style="scroll-margin-top: 80px;" aria-hidden="true"></div>
{{-- Academic Years manager --}}
<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-calendar3"></i>
            <span class="fw-semibold">Academic Years</span>
            <span class="badge text-bg-secondary badge-soft ms-2">{{ $academicYears->count() }} years</span>
        </div>
    </div>
    <p class="text-muted small mb-3 px-3">
        Each placement belongs to one academic year so 2026 and 2027 placements stay separate and historical.
    </p>

    <form method="POST" action="{{ route('settings.academic.academic-years.store') }}" class="row g-2 align-items-end px-3 pb-3">
        @csrf
        <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Name</label>
            <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. Academic Year 2026" required maxlength="120">
        </div>
        <div class="col-md-2">
            <label class="form-label small text-muted mb-1">Code</label>
            <input type="text" name="code" class="form-control form-control-sm" placeholder="e.g. 2026" required maxlength="40">
        </div>
        <div class="col-md-2">
            <label class="form-label small text-muted mb-1">Start</label>
            <input type="date" name="start_date" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <label class="form-label small text-muted mb-1">End</label>
            <input type="date" name="end_date" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <div class="form-check form-switch mb-0 pb-1">
                <input class="form-check-input" type="checkbox" name="is_current" value="1" id="ay_add_current">
                <label class="form-check-label small text-muted" for="ay_add_current">Current year</label>
            </div>
        </div>
        <div class="col-md-1">
            <button class="btn btn-sm btn-primary w-100" type="submit"><i class="bi bi-plus-lg"></i></button>
        </div>
    </form>

    @error('academic_year')<div class="text-danger small px-3">{{ $message }}</div>@enderror

    @if ($academicYears->isNotEmpty())
        <div class="table-responsive border-top">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Start</th>
                        <th>End</th>
                        <th class="text-center">Current</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($academicYears as $year)
                        <tr>
                            <td colspan="7" class="p-0">
                                <form method="POST" action="{{ route('settings.academic.academic-years.update', $year) }}" class="row g-2 align-items-center p-2">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="name" value="{{ $year->name }}">
                                    <input type="hidden" name="code" value="{{ $year->code }}">
                                    <input type="hidden" name="start_date" value="{{ $year->start_date?->format('Y-m-d') ?? '' }}">
                                    <input type="hidden" name="end_date" value="{{ $year->end_date?->format('Y-m-d') ?? '' }}">
                                    <div class="col d-flex align-items-center gap-2 flex-wrap">
                                        <span class="fw-semibold">{{ $year->name }}</span>
                                        <span class="badge text-bg-light border">{{ $year->code }}</span>
                                        <small class="text-muted">{{ $year->start_date?->format('d M Y') ?? '—' }} → {{ $year->end_date?->format('d M Y') ?? '—' }}</small>
                                    </div>
                                    <div class="col-auto">
                                        <label class="form-check form-switch m-0">
                                            <input class="form-check-input" type="checkbox" name="is_current" value="1" @checked($year->is_current)>
                                            <span class="form-check-label small text-muted">Current</span>
                                        </label>
                                    </div>
                                    <div class="col-auto">
                                        <label class="form-check form-switch m-0">
                                            <input class="form-check-input" type="checkbox" name="status" value="1" @checked($year->status)>
                                            <span class="form-check-label small text-muted">Active</span>
                                        </label>
                                    </div>
                                    <div class="col-auto d-flex gap-1">
                                        <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-check-lg"></i> Save</button>
                                        <button class="btn btn-sm btn-outline-danger" type="submit" form="ay-delete-{{ $year->id }}"><i class="bi bi-trash"></i></button>
                                    </div>
                                </form>
                                <form id="ay-delete-{{ $year->id }}" method="POST" action="{{ route('settings.academic.academic-years.destroy', $year) }}" data-ajax-delete="1" data-confirm="Remove academic year {{ $year->name }}?">
                                    @csrf
                                    @method('DELETE')
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection
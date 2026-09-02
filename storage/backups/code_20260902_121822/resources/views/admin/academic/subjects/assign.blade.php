@extends('layouts.admin')

@section('title', 'Academic Assignments — AccumenAI')

@section('content')
@php
    $requirementLabels = [
        'mandatory' => 'Mandatory',
        'optional' => 'Optional',
        'elective' => 'Elective',
    ];
@endphp
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Academic Assignments</h4>
        <p class="page-header-desc">Attach subjects from the master to a class/grade (optionally a specific group/stream), set their mandatory/optional/elective requirement type, and group optional subjects into selection groups.</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.academic.subjects.index', ['industry' => 'education']) }}">
            <i class="bi bi-collection"></i> Subject Master
        </a>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="filter-card">
    <form class="filter-layout" method="GET" action="{{ route('admin.academic.subjects.assign') }}">

        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span flex-shrink-0" style="min-width:170px">
                <label class="form-label mb-1">Country</label>
                <select class="form-select form-select-sm" name="country_id" onchange="this.form.submit()">
                    <option value="">Select Country</option>
                    @foreach ($countries as $country)
                        <option value="{{ $country->id }}" @selected($selected['country_id'] === (int) $country->id)>{{ $country->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:170px">
                <label class="form-label mb-1">System</label>
                <select class="form-select form-select-sm" name="system_id" onchange="this.form.submit()" @disabled($systems->isEmpty())>
                    <option value="">Select System</option>
                    @foreach ($systems as $system)
                        <option value="{{ $system->id }}" @selected($selected['system_id'] === (int) $system->id)>{{ $system->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:170px">
                <label class="form-label mb-1">Level</label>
                <select class="form-select form-select-sm" name="level_id" onchange="this.form.submit()" @disabled($levels->isEmpty())>
                    <option value="">Select Level</option>
                    @foreach ($levels as $level)
                        <option value="{{ $level->id }}" @selected($selected['level_id'] === (int) $level->id)>{{ $level->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:170px">
                <label class="form-label mb-1">Class / Grade</label>
                <select class="form-select form-select-sm" name="class_id" onchange="this.form.submit()" @disabled($classes->isEmpty())>
                    <option value="">Select Class</option>
                    @foreach ($classes as $classGrade)
                        <option value="{{ $classGrade->id }}" @selected($selected['class_id'] === (int) $classGrade->id)>{{ $classGrade->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:170px">
                <label class="form-label mb-1">Group / Stream</label>
                <select class="form-select form-select-sm" name="group_id" onchange="this.form.submit()" @disabled($groups->isEmpty())>
                    <option value="">Whole class</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}" @selected($selected['group_id'] === (int) $group->id)>{{ $group->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-actions">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Load</button>
            </div>
        </div>

    </form>
</div>

@if ($classGrade === null)
    <div class="admin-card mt-3">
        <p class="text-muted mb-0 py-4 text-center">
            <i class="bi bi-info-circle"></i> Select a country and drill down to a class to manage its subjects.
        </p>
    </div>
@else
    <div class="admin-card mt-3">
        <div class="table-toolbar">
            <div class="toolbar-info">
                <i class="bi bi-book"></i> Class: <span class="fw-semibold">{{ $classGrade->name }}</span>
                @if ($academicGroup !== null)
                    <span class="badge text-bg-info ms-2">{{ $academicGroup->name }} group</span>
                @else
                    <span class="badge text-bg-light border ms-2">Whole class</span>
                @endif
                <span class="badge text-bg-secondary badge-soft ms-2">{{ count($nodes) }} subjects</span>
                <span class="badge text-bg-light border ms-2">{{ count($selectionGroups) }} selection groups</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="text-muted">#</th>
                        <th>Subject</th>
                        <th style="width:150px">Requirement</th>
                        <th style="width:170px">Selection Group</th>
                        <th style="width:100px">Display Order</th>
                        <th style="width:90px">Status</th>
                        <th class="text-end" style="width:190px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($nodes as $node)
                        @php $assignment = $node['assignment']; @endphp
                        <tr>
                            <td class="text-muted">{{ $loop->iteration }}</td>
                            <td>
                                <span class="fw-semibold">{{ $node['name'] }}</span>
                                <div class="text-muted small">{{ $assignment->subject?->subject_code }}</div>
                            </td>
                            <td colspan="4">
                                <form method="POST" action="{{ route('admin.academic.subjects.assignments.update', $assignment) }}" class="update-assignment-form row g-1 align-items-end">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="status" value="{{ $assignment->status }}">
                                    <div class="col-auto">
                                        <select name="requirement_type" class="form-select form-select-sm" style="width:150px">
                                            @foreach ($requirementLabels as $key => $label)
                                                <option value="{{ $key }}" @selected($node['requirement_type'] === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <select name="selection_group_id" class="form-select form-select-sm" style="width:170px">
                                            <option value="">— None —</option>
                                            @foreach ($selectionGroups as $entry)
                                                @php $group = $entry['group']; @endphp
                                                <option value="{{ $group->id }}" @selected((int) $assignment->selection_group_id === (int) $group->id)>
                                                    {{ $group->name }} ({{ $entry['member_count'] }} @if ($group->minimum_selection !== null) · min {{ $group->minimum_selection }} @endif @if ($group->maximum_selection !== null) · max {{ $group->maximum_selection }} @endif)
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <input type="number" name="display_order" class="form-control form-control-sm" style="width:90px" value="{{ $assignment->display_order }}" min="0">
                                    </div>
                                    <div class="col-auto">
                                        <button class="btn btn-sm btn-outline-primary" type="submit" title="Save"><i class="bi bi-check-lg"></i></button>
                                    </div>
                                </form>
                            </td>
                            <td>
                                @if ($assignment->status === 'active')
                                    <span class="badge text-bg-success">Active</span>
                                @else
                                    <span class="badge text-bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <form method="POST" action="{{ route('admin.academic.subjects.assignments.update', $assignment) }}" class="d-inline">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="display_order" value="{{ $assignment->display_order }}">
                                        <input type="hidden" name="requirement_type" value="{{ $assignment->requirement_type }}">
                                        <input type="hidden" name="selection_group_id" value="{{ $assignment->selection_group_id }}">
                                        <input type="hidden" name="status" value="{{ $assignment->status === 'active' ? 'inactive' : 'active' }}">
                                        <button type="submit" class="btn {{ $assignment->status === 'active' ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                            {{ $assignment->status === 'active' ? 'Disable' : 'Enable' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.academic.subjects.assignments.destroy', $assignment) }}" class="d-inline" onsubmit="return confirm('Remove this assignment?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No subjects assigned to this {{ $academicGroup !== null ? 'group' : 'class' }} yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($addableSubjects->isNotEmpty())
            <div class="p-3 border-top">
                <div class="admin-card shadow-none mb-2">
                    <div class="table-toolbar">
                        <div class="toolbar-info"><i class="bi bi-plus-circle"></i> Assign a subject to this {{ $academicGroup !== null ? 'group' : 'class' }}</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.academic.subjects.assignments.store') }}" class="row g-2 align-items-end">
                    @csrf
                    <input type="hidden" name="class_grade_id" value="{{ $classGrade->id }}">
                    <input type="hidden" name="academic_group_id" value="{{ $academicGroup?->id }}">
                    <div class="col-md-4 col-lg-3">
                        <label class="form-label">Subject</label>
                        <select class="form-select" name="subject_id" required>
                            @foreach ($addableSubjects as $subject)
                                <option value="{{ $subject->id }}">{{ $subject->name }} ({{ $subject->subject_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 col-lg-2">
                        <label class="form-label">Requirement</label>
                        <select class="form-select" name="requirement_type" required>
                            @foreach ($requirementLabels as $key => $label)
                                <option value="{{ $key }}" @selected($key === 'mandatory')>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 col-lg-2">
                        <label class="form-label">Selection Group</label>
                        <select class="form-select" name="selection_group_id">
                            <option value="">— None —</option>
                            @foreach ($selectionGroups as $entry)
                                <option value="{{ $entry['group']->id }}">{{ $entry['group']->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 col-lg-1">
                        <label class="form-label">Order</label>
                        <input type="number" name="display_order" class="form-control" value="{{ count($nodes) + 1 }}" min="0">
                    </div>
                    <div class="col-md-2 col-lg-2">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-link-45deg"></i> Assign</button>
                    </div>
                </form>
            </div>
        @endif
    </div>

    {{-- ------------------------------------------------- Selection Groups --}}
    <div class="admin-card mt-3">
        <div class="table-toolbar">
            <div class="toolbar-info">
                <i class="bi bi-diagram-3"></i> Selection Groups
                <span class="badge text-bg-secondary badge-soft ms-2">{{ count($selectionGroups) }}</span>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.academic.subjects.selection-groups.store') }}" class="p-3 border-bottom row g-2 align-items-end bg-light-subtle rounded mb-2">
            @csrf
            <input type="hidden" name="class_grade_id" value="{{ $classGrade->id }}">
            <input type="hidden" name="academic_group_id" value="{{ $academicGroup?->id }}">
            <div class="col-md-3 col-lg-2">
                <label class="form-label mb-1">Group Name</label>
                <input type="text" name="name" class="form-control form-control-sm" required maxlength="120" placeholder="e.g. Optional Core">
            </div>
            <div class="col-md-2 col-lg-1">
                <label class="form-label mb-1">Code</label>
                <input type="text" name="code" class="form-control form-control-sm" required maxlength="40" placeholder="opt_core">
            </div>
            <div class="col-md-2 col-lg-1">
                <label class="form-label mb-1">Type</label>
                <select name="selection_type" class="form-select form-select-sm">
                    <option value="optional">Optional</option>
                    <option value="elective">Elective</option>
                </select>
            </div>
            <div class="col-md-2 col-lg-1">
                <label class="form-label mb-1" title="Minimum subjects a student must pick">Min</label>
                <input type="number" name="minimum_selection" class="form-control form-control-sm" min="0" placeholder="1">
            </div>
            <div class="col-md-2 col-lg-1">
                <label class="form-label mb-1" title="Maximum subjects a student may pick">Max</label>
                <input type="number" name="maximum_selection" class="form-control form-control-sm" min="0" placeholder="(all)">
            </div>
            <div class="col-md-2 col-lg-1">
                <label class="form-label mb-1">Order</label>
                <input type="number" name="display_order" class="form-control form-control-sm" value="{{ count($selectionGroups) + 1 }}" min="0">
            </div>
            <div class="col-md-2 col-lg-auto">
                <button class="btn btn-primary btn-sm w-100" type="submit"><i class="bi bi-plus-lg"></i> Add Group</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Type</th>
                        <th style="width:70px">Min</th>
                        <th style="width:70px">Max</th>
                        <th style="width:70px">Subjects</th>
                        <th style="width:90px">Status</th>
                        <th class="text-end" style="width:150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($selectionGroups as $entry)
                        @if ($entry['group']->status === 'active')
                            <tr>
                                <td colspan="8" class="p-0">
                                    <form method="POST" action="{{ route('admin.academic.subjects.selection-groups.update', $entry['group']) }}" class="row g-1 align-items-center p-2">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="class_grade_id" value="{{ $classGrade->id }}">
                                        <input type="hidden" name="academic_group_id" value="{{ $academicGroup?->id }}">
                                        <div class="col-12 col-md-3 col-lg-2">
                                            <input type="text" name="name" value="{{ $entry['group']->name }}" class="form-control form-control-sm" required maxlength="120">
                                        </div>
                                        <div class="col-6 col-md-2 col-lg-1">
                                            <input type="text" name="code" value="{{ $entry['group']->code }}" class="form-control form-control-sm" required maxlength="40">
                                        </div>
                                        <div class="col-6 col-md-1 col-lg-1">
                                            <select name="selection_type" class="form-select form-select-sm">
                                                <option value="optional" @selected($entry['group']->selection_type === 'optional')>Optional</option>
                                                <option value="elective" @selected($entry['group']->selection_type === 'elective')>Elective</option>
                                            </select>
                                        </div>
                                        <div class="col-3 col-md-1 col-lg-1">
                                            <input type="number" name="minimum_selection" value="{{ $entry['group']->minimum_selection }}" class="form-control form-control-sm" min="0" placeholder="0">
                                        </div>
                                        <div class="col-3 col-md-1 col-lg-1">
                                            <input type="number" name="maximum_selection" value="{{ $entry['group']->maximum_selection }}" class="form-control form-control-sm" min="0" placeholder="all">
                                        </div>
                                        <div class="col-4 col-md-1 col-lg-1">
                                            <input type="number" name="display_order" value="{{ $entry['group']->display_order }}" class="form-control form-control-sm" min="0">
                                        </div>
                                        <div class="col-4 col-md-1 col-lg-1">
                                            <span class="badge text-bg-light border">{{ $entry['member_count'] }} subjects</span>
                                        </div>
                                        <div class="col-12 col-md-2 col-lg-2">
                                            <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-check-lg"></i> Save</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            <tr class="border-0">
                                <td colspan="8" class="pt-0 text-end">
                                    <div class="d-inline-flex gap-2">
                                        <form method="POST" action="{{ route('admin.academic.subjects.selection-groups.toggle', $entry['group']) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-pause-circle"></i> Deactivate</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.academic.subjects.selection-groups.destroy', $entry['group']) }}" class="d-inline" onsubmit="return confirm('Delete this selection group? Member assignments will be kept but ungrouped.');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @else
                            <tr class="text-muted bg-light">
                                <td>
                                    <span class="fw-semibold">{{ $entry['group']->name }}</span>
                                    <span class="badge text-bg-secondary ms-1">Inactive</span>
                                </td>
                                <td>{{ $entry['group']->code }}</td>
                                <td>{{ ucfirst($entry['group']->selection_type) }}</td>
                                <td>{{ $entry['group']->minimum_selection ?? '0' }}</td>
                                <td>{{ $entry['group']->maximum_selection ?? 'all' }}</td>
                                <td>{{ $entry['member_count'] }}</td>
                                <td><span class="badge text-bg-secondary">Inactive</span></td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('admin.academic.subjects.selection-groups.toggle', $entry['group']) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-success" type="submit"><i class="bi bi-play-circle"></i> Activate</button>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No selection groups for this {{ $academicGroup !== null ? 'group' : 'class' }} yet. Add one above, then point optional subjects at it.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
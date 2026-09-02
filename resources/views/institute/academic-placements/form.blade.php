@extends('layouts.standalone')

@section('title', ($placement ? 'Edit' : 'New').' Academic Placement — AccumenAI')
@section('page_title', $placement ? 'Edit Academic Placement' : 'New Academic Placement')

@php
    $students = $students->values();
@endphp

@section('content')

<div class="standalone-heading">
    <h4>{{ $placement ? 'Edit Academic Placement' : 'New Academic Placement' }}</h4>
    <p>Assign a student to an academic year and class/grade (plus an optional group/stream), then choose their subjects. Mandatory subjects are included automatically.</p>
</div>

@if ($errors->has('subjects'))
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill me-1"></i><strong>Subject selection:</strong>
        <ul class="mb-0 mt-1">
            @foreach ($errors->get('subjects') as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $placement ? route('settings.academic.placements.update', $placement) : route('settings.academic.placements.store') }}" id="placementForm">
    @csrf
    @if ($placement)@method('PUT')@endif

    <div class="admin-card mb-3">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="fp_student">Student *</label>
                <select id="fp_student" name="student_id" class="form-select" required @if($placement) disabled @endif>
                    <option value="">Select student</option>
                    @foreach ($students as $student)
                        <option value="{{ $student->id }}"
                                @selected(($preselectedStudent?->id ?? old('student_id')) == $student->id)>
                            {{ $student->full_name }} ({{ $student->student_id ?: 'no ID' }})
                        </option>
                    @endforeach
                </select>
                @if ($placement)
                    <input type="hidden" name="student_id" value="{{ $placement->student_id }}">
                @endif
                @error('student_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="fp_year">Academic Year *</label>
                <select id="fp_year" name="academic_year_id" class="form-select" required>
                    <option value="">Select year</option>
                    @foreach ($academicYears as $year)
                        <option value="{{ $year->id }}"
                                @selected($year->id == ($placement?->academic_year_id ?? old('academic_year_id')))>
                            {{ $year->name }}@if ($year->is_current) (Current)@endif
                        </option>
                    @endforeach
                </select>
                @error('academic_year_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="fp_class">Class / Grade *</label>
                <select id="fp_class" name="class_grade_id" class="form-select" required>
                    <option value="">Select class</option>
                    @foreach ($classes as $entry)
                        <option value="{{ $entry['class_grade']->id }}"
                                @selected($classGrade !== null && $classGrade->id === $entry['class_grade']->id)>
                            {{ $entry['name'] }}@if ($entry['level_name']) — {{ $entry['level_name'] }}@endif
                        </option>
                    @endforeach
                </select>
                @error('class_grade_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label" for="fp_group">Group / Stream</label>
                <select id="fp_group" name="academic_group_id" class="form-select">
                    <option value="">Whole class</option>
                    @if ($classGrade !== null)
                        @foreach ($classGrade->groups()->where('status', true)->get() as $group)
                            <option value="{{ $group->id }}"
                                    @selected($academicGroup !== null && $academicGroup->id === $group->id)>
                                {{ $group->name }}
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="fp_status">Status</label>
                <select id="fp_status" name="status" class="form-select">
                    <option value="active" @selected(old('status', $placement?->status ?? 'active') === 'active')>Active</option>
                    <option value="completed" @selected(old('status', $placement?->status ?? 'active') === 'completed')>Completed</option>
                    <option value="transferred" @selected(old('status', $placement?->status ?? 'active') === 'transferred')>Transferred</option>
                    <option value="dropped" @selected(old('status', $placement?->status ?? 'active') === 'dropped')>Dropped</option>
                </select>
            </div>
            <div class="col-md-9">
                <label class="form-label" for="fp_notes">Notes</label>
                <input type="text" id="fp_notes" name="notes" class="form-control" maxlength="500"
                       value="{{ old('notes', $placement?->notes) }}" placeholder="Optional note">
                @error('notes')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div id="subjectsContainer" data-subjects-url="{{ $placement ? route('settings.academic.placements.subjects', $placement) : '' }}">
        @if ($subjectPayload !== null)
            @include('institute.academic-placements._subjects', ['payload' => $subjectPayload, 'selectedSubjectIds' => $selectedSubjectIds])
        @else
            <div class="admin-card">
                <p class="text-muted mb-0 py-3 text-center">
                    <i class="bi bi-info-circle me-1"></i>Select a class to load its subjects.
                </p>
            </div>
        @endif
    </div>

    <div class="d-flex gap-2 mt-2">
        <button class="btn btn-primary" type="submit">
            <i class="bi bi-check-lg me-1"></i>{{ $placement ? 'Save Subject Selection' : 'Save Academic Placement' }}
        </button>
        <a href="{{ route('settings.academic.placements.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

@endsection

@push('scripts')
<script>
(function () {
    var classSel = document.getElementById('fp_class');
    var groupSel = document.getElementById('fp_group');
    var container = document.getElementById('subjectsContainer');
    var isAjax = window.Monetix && Monetix.request;
    if (!classSel || !container) { return; }

    var timer = null;

    function reloadSubjects() {
        if (timer) { clearTimeout(timer); }
        timer = setTimeout(function () {
            var classId = classSel.value;
            if (!classId) {
                container.innerHTML = '<div class="admin-card"><p class="text-muted mb-0 py-3 text-center"><i class="bi bi-info-circle me-1"></i>Select a class to load its subjects.</p></div>';
                return;
            }
            if (!isAjax) { return; }

            var checked = [];
            container.querySelectorAll('input[data-subject-pick]:checked').forEach(function (el) { checked.push(el.value); });

            var params = new URLSearchParams();
            params.set('class_grade_id', classId);
            if (groupSel && groupSel.value) { params.set('academic_group_id', groupSel.value); }
            checked.forEach(function (v) { params.append('selected_ids[]', v); });

            var url = container.getAttribute('data-subjects-url') + '?' + params.toString();
            Monetix.request(url, { method: 'GET', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': Monetix.csrfToken && Monetix.csrfToken() } })
                .then(function (res) {
                    if (res && res.html) { container.innerHTML = res.html; }
                });
        }, 220);
    }

    classSel.addEventListener('change', function () {
        if (groupSel) { groupSel.innerHTML = '<option value="">Whole class</option>'; }
        reloadSubjects();
    });
    if (groupSel) { groupSel.addEventListener('change', reloadSubjects); }

    // Enforce group max at the browser level; min is enforced server-side.
    document.addEventListener('change', function (e) {
        var el = e.target;
        if (!el || !el.matches('input[data-subject-pick]')) { return; }
        var group = el.closest('[data-selection-group]');
        if (!group) { return; }
        var max = parseInt(group.getAttribute('data-max') || '0', 10);
        if (max > 0 && group.querySelectorAll('input[data-subject-pick]:checked').length > max) {
            el.checked = false;
        }
        var picked = group.querySelectorAll('input[data-subject-pick]:checked').length;
        var label = group.querySelector('[data-group-count]');
        if (label) { label.textContent = 'Selected: ' + picked + ' / ' + max; }
    });
})();
</script>
@endpush
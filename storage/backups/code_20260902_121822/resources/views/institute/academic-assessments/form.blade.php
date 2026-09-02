@extends('layouts.standalone')

@section('title', ($assessment ? 'Edit' : 'New').' Academic Assessment — AccumenAI')
@section('page_title', $assessment ? 'Edit Academic Assessment' : 'New Academic Assessment')

@section('content')

<div class="standalone-heading">
    <h4>{{ $assessment ? 'Edit Academic Assessment' : 'New Academic Assessment' }}</h4>
    <p>Create an assessment for a year and class (plus an optional group/stream). Then add subjects from the class curriculum and configure each subject's components with full and pass marks.</p>
</div>

@php
    $statusList = ['draft' => 'Draft', 'scheduled' => 'Scheduled', 'open' => 'Open', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
@endphp

@if ($errors->count())
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill me-1"></i><strong>Please fix the following:</strong>
        <ul class="mb-0 mt-1">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $assessment ? route('settings.academic.assessments.update', $assessment) : route('settings.academic.assessments.store') }}" id="assessmentForm">
    @csrf
    @if ($assessment)@method('PUT')@endif

    <div class="admin-card mb-3">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="aa_name">Assessment Name *</label>
                <input type="text" id="aa_name" name="name" class="form-control" maxlength="120" required
                       value="{{ old('name', $assessment?->name) }}" placeholder="e.g. Mid Term 2026">
                @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="aa_type">Assessment Type</label>
                <select id="aa_type" name="assessment_type_id" class="form-select">
                    <option value="">— None —</option>
                    @foreach ($assessmentTypes as $type)
                        <option value="{{ $type->id }}" @selected((int) old('assessment_type_id', $assessment?->assessment_type_id) === (int) $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
                @error('assessment_type_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label" for="aa_exam_date">Exam Date</label>
                <input type="date" id="aa_exam_date" name="exam_date" class="form-control"
                       value="{{ old('exam_date', $assessment?->exam_date?->format('Y-m-d')) }}">
                @error('exam_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="aa_year">Academic Year *</label>
                <select id="aa_year" name="academic_year_id" class="form-select" required>
                    <option value="">Select year</option>
                    @foreach ($academicYears as $year)
                        <option value="{{ $year->id }}"
                                @selected((int) old('academic_year_id', $assessment?->academic_year_id) === (int) $year->id)>
                            {{ $year->name }}@if ($year->is_current) (Current)@endif
                        </option>
                    @endforeach
                </select>
                @error('academic_year_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="aa_class">Class / Grade *</label>
                <select id="aa_class" name="class_grade_id" class="form-select" required>
                    <option value="">Select class</option>
                    @foreach ($classes as $entry)
                        <option value="{{ $entry['class_grade']->id }}"
                                @selected($classGrade !== null && (int) $classGrade->id === (int) $entry['class_grade']->id)>
                            {{ $entry['name'] }}@if ($entry['level_name']) — {{ $entry['level_name'] }}@endif
                        </option>
                    @endforeach
                </select>
                @error('class_grade_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label" for="aa_group">Group / Stream</label>
                <select id="aa_group" name="academic_group_id" class="form-select">
                    <option value="">Whole class</option>
                    @if ($classGrade !== null)
                        @foreach ($classGrade->groups()->where('status', true)->get() as $group)
                            <option value="{{ $group->id }}" @selected($academicGroup !== null && (int) $academicGroup->id === (int) $group->id)>{{ $group->name }}</option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="aa_status">Status</label>
                <select id="aa_status" name="status" class="form-select">
                    @foreach ($statusList as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $assessment?->status ?? 'draft') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="aa_order">Order</label>
                <input type="number" id="aa_order" name="display_order" class="form-control" min="1"
                       value="{{ old('display_order', $assessment?->display_order) }}" placeholder="auto">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="aa_notes">Notes</label>
                <input type="text" id="aa_notes" name="notes" class="form-control" maxlength="500"
                       value="{{ old('notes', $assessment?->notes) }}" placeholder="Optional note">
                @error('notes')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    {{-- Subjects + components editor --}}
    <div class="admin-card mb-3" id="subjectsCard" data-subjects-url="{{ $assessment ? route('settings.academic.assessments.subjects', $assessment) : '' }}">
        <div class="table-toolbar">
            <div class="toolbar-info">
                <i class="bi bi-journal-text"></i>
                <span class="fw-semibold">Subjects &amp; Components</span>
                <span class="text-muted small ms-2">Total full marks are calculated automatically.</span>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="addSubjectBtn" disabled data-id="">
                    <i class="bi bi-plus-lg me-1"></i>Add Subject
                </button>
            </div>
        </div>
        <div class="p-3">
            <div id="subjectRows" data-components-json="{{ json_encode($components->map(fn ($c) => ['id' => (int) $c->id, 'name' => $c->name])) }}">
                @if ($assessment && filled($selectedSubjects))
                    @foreach ($selectedSubjects as $row)
                        <div class="academic-subject-row border rounded p-2 mb-3" data-subject-id="{{ $row['subject_id'] }}">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="fw-semibold" data-subject-name>{{ $row['name'] ?? ('Subject #'.$row['subject_id']) }}</span>
                                <input type="hidden" name="subjects[{{ $loop->index }}][subject_id]" value="{{ $row['subject_id'] }}">
                                <label class="form-label small mb-0 me-1 fw-semibold">Pass Rule</label>
                                <select class="form-select form-select-sm" style="width:230px" name="subjects[{{ $loop->index }}][pass_rule]">
                                    @foreach (\App\Models\AssessmentSubject::PASS_RULE_LABELS as $rule => $label)
                                        <option value="{{ $rule }}" @selected(($row['pass_rule'] ?? 'total_only') === $rule)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" onclick="window.AcademicAssessment && AcademicAssessment.removeSubject(this)">Remove</button>
                            </div>
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:26%">Component</th>
                                        <th style="width:16%">Full Mark</th>
                                        <th style="width:16%">Pass Mark</th>
                                        <th style="width:22%">Must Pass</th>
                                        <th class="text-end" style="width:20%"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($row['components'] as $ci => $component)
                                        <tr>
                                            <td>
                                                <select class="form-select form-select-sm" name="subjects[{{ $loop->parent->index }}][components][{{ $ci }}][component_id]" required>
                                                    @foreach ($components as $comp)
                                                        <option value="{{ $comp->id }}" @selected((int) $component['component_id'] === (int) $comp->id)>{{ $comp->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td><input type="number" step="any" min="1" class="form-control form-control-sm" name="subjects[{{ $loop->parent->index }}][components][{{ $ci }}][full_mark]" value="{{ $component['full_mark'] }}" required></td>
                                            <td><input type="number" step="any" min="0" class="form-control form-control-sm" name="subjects[{{ $loop->parent->index }}][components][{{ $ci }}][pass_mark]" value="{{ $component['pass_mark'] }}" required></td>
                                            <td>
                                                <div class="form-check form-switch mt-1">
                                                    <input class="form-check-input" type="checkbox" name="subjects[{{ $loop->parent->index }}][components][{{ $ci }}][mandatory_pass]" value="1" @checked($component['mandatory_pass'])>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="window.AcademicAssessment && AcademicAssessment.removeComponent(this)"><i class="bi bi-x-lg"></i></button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="text-muted small">Total: <strong data-subject-total>{{ $row['total'] ?? '' }}</strong></span>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.AcademicAssessment && AcademicAssessment.addComponent(this)"><i class="bi bi-plus-lg me-1"></i>Component</button>
                            </div>
                        </div>
                    @endforeach
                @else
                    <p class="text-muted mb-0 py-3 text-center">
                        <i class="bi bi-info-circle me-1"></i>Select a class to load its curriculum, then add subjects.
                    </p>
                @endif
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-2">
        <button class="btn btn-primary" type="submit">
            <i class="bi bi-check-lg me-1"></i>{{ $assessment ? 'Save Changes' : 'Save Assessment' }}
        </button>
        <a href="{{ route('settings.academic.assessments.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

@endsection

@push('scripts')
<script>
(function () {
    var classSel = document.getElementById('aa_class');
    var groupSel = document.getElementById('aa_group');
    var card = document.getElementById('subjectsCard');
    var rows = document.getElementById('subjectRows');
    var addBtn = document.getElementById('addSubjectBtn');
    var isAjax = window.Monetix && Monetix.request;
    if (!classSel || !rows || !addBtn) { return; }

    // Global component definitions passed from the view.
    var components = [];
    try { components = JSON.parse(rows.getAttribute('data-components-json') || '[]'); } catch (e) { components = []; }

    var subjectPool = [];      // selectable subjects for current class/group
    var timer = null;

    function loadSubjects() {
        if (timer) { clearTimeout(timer); }
        timer = setTimeout(function () {
            var classId = classSel.value;
            if (!classId) {
                subjectPool = [];
                updateSubjectState();
                return;
            }
            if (!isAjax) { return; }

            var params = new URLSearchParams();
            params.set('class_grade_id', classId);
            if (groupSel && groupSel.value) { params.set('academic_group_id', groupSel.value); }

            var url = card.getAttribute('data-subjects-url') + '?' + params.toString();
            Monetix.request(url, { method: 'GET', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': Monetix.csrfToken && Monetix.csrfToken() } })
                .then(function (res) {
                    subjectPool = (res && res.subjects) || [];
                    updateSubjectState();
                });
        }, 220);
    }

    function chosenSubjectIds() {
        var ids = [];
        rows.querySelectorAll('.academic-subject-row').forEach(function (row) { ids.push(row.getAttribute('data-subject-id')); });
        return ids;
    }

    function updateSubjectState() {
        var chosen = chosenSubjectIds();
        var leaves = subjectPool.filter(function (s) { return chosen.indexOf(String(s.id)) === -1; });
        addBtn.disabled = leaves.length === 0;
        addBtn.setAttribute('data-id', leaves.length ? leaves[0].id : '');
    }

    function indexOf(node) {
        return Array.prototype.indexOf.call(rows.children, node);
    }

    function addSubject() {
        var id = addBtn.getAttribute('data-id');
        if (!id || !isAjax) { return; }
        var subject = subjectPool.find(function (s) { return String(s.id) === id; });
        if (!subject) { return; }

        var idx = rows.children.length;
        var div = document.createElement('div');
        div.className = 'academic-subject-row border rounded p-2 mb-3';
        div.setAttribute('data-subject-id', subject.id);

        var names = components.map(function (c) { return '<option value="' + c.id + '">' + c.name + '</option>'; }).join('');

        div.innerHTML =
            '<div class="d-flex align-items-center gap-2 mb-2">' +
                '<span class="fw-semibold" data-subject-name>' + subject.name + '</span>' +
                '<input type="hidden" name="subjects[' + idx + '][subject_id]" value="' + subject.id + '">' +
                '<label class="form-label small mb-0 me-1 fw-semibold">Pass Rule</label>' +
                '<select class="form-select form-select-sm" style="width:230px" name="subjects[' + idx + '][pass_rule]">' +
                    '<option value="total_only">Total Marks Only</option>' +
                    '<option value="mandatory_components">Mandatory Components</option>' +
                    '<option value="both">Total + Mandatory Components</option>' +
                '</select>' +
                '<button type="button" class="btn btn-sm btn-outline-secondary ms-auto" onclick="window.AcademicAssessment && AcademicAssessment.removeSubject(this)">Remove</button>' +
            '</div>' +
            '<table class="table table-sm align-middle mb-0"><thead><tr>' +
                '<th style="width:26%">Component</th><th style="width:16%">Full Mark</th><th style="width:16%">Pass Mark</th><th style="width:22%">Must Pass</th><th class="text-end" style="width:20%"></th>' +
            '</tr></thead><tbody></tbody></table>' +
            '<div class="d-flex justify-content-between align-items-center mt-2">' +
                '<span class="text-muted small">Total: <strong data-subject-total>0</strong></span>' +
                '<button type="button" class="btn btn-sm btn-outline-primary" onclick="window.AcademicAssessment && AcademicAssessment.addComponent(this)"><i class="bi bi-plus-lg me-1"></i>Component</button>' +
            '</div>';

        rows.appendChild(div);
        updateTotals(div);
        updateSubjectState();
    }

    function removeSubject(btn) {
        var row = btn.closest('.academic-subject-row');
        if (row) { row.parentNode.removeChild(row); }
        updateSubjectState();
    }

    function addComponent(btn) {
        var row = btn.closest('.academic-subject-row');
        if (!row || !components.length) { return; }
        var tbody = row.querySelector('tbody');
        var idx = indexOf(row);
        var ci = tbody.children.length;
        var compOptions = components.map(function (c) { return '<option value="' + c.id + '">' + c.name + '</option>'; }).join('');
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td><select class="form-select form-select-sm" name="subjects[' + idx + '][components][' + ci + '][component_id]" required>' + compOptions + '</select></td>' +
            '<td><input type="number" step="any" min="1" class="form-control form-control-sm" name="subjects[' + idx + '][components][' + ci + '][full_mark]" placeholder="0" required></td>' +
            '<td><input type="number" step="any" min="0" class="form-control form-control-sm" name="subjects[' + idx + '][components][' + ci + '][pass_mark]" placeholder="0" required></td>' +
            '<td><div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" name="subjects[' + idx + '][components][' + ci + '][mandatory_pass]" value="1"></div></td>' +
            '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" onclick="window.AcademicAssessment && AcademicAssessment.removeComponent(this)"><i class="bi bi-x-lg"></i></button></td>';
        tbody.appendChild(tr);
        updateTotals(row);
    }

    function removeComponent(btn) {
        var tr = btn.closest('tr');
        var row = btn.closest('.academic-subject-row');
        if (tr) { tr.parentNode.removeChild(tr); }
        if (row) { updateTotals(row); }
    }

    // live derived-total feedback per subject row
    function updateTotals(row) {
        var total = 0;
        row.querySelectorAll('input[name$="[full_mark]"]').forEach(function (input) {
            var v = parseFloat(input.value);
            if (!isNaN(v)) { total += v; }
        });
        var el = row.querySelector('[data-subject-total]');
        if (el) { el.textContent = total; }
    }

    document.addEventListener('input', function (e) {
        if (e.target && e.target.matches('input[name$="[full_mark]"]')) {
            var row = e.target.closest('.academic-subject-row');
            if (row) { updateTotals(row); }
        }
    });

    classSel.addEventListener('change', function () {
        if (groupSel) { groupSel.innerHTML = '<option value="">Whole class</option>'; }
        // remove server-rendered subject rows when the class changes
        rows.querySelectorAll('.academic-subject-row').forEach(function (r) { r.parentNode.removeChild(r); });
        loadSubjects();
    });
    if (groupSel) { groupSel.addEventListener('change', loadSubjects); }
    if (addBtn) { addBtn.addEventListener('click', addSubject); }

    window.AcademicAssessment = { addComponent: addComponent, removeComponent: removeComponent, removeSubject: removeSubject };

    // On edit pages, already-rendered rows keep working; seed the subject pool in case a rebuild happens.
    if (classSel.value && isAjax) { loadSubjects(); }
})();
</script>
@endpush
@extends('layouts.standalone')

@section('title', ($scheme ? 'Edit' : 'New').' Aggregation Scheme — AccumenAI')
@section('page_title', $scheme ? 'Edit Aggregation Scheme' : 'New Aggregation Scheme')

@section('content')

<div class="standalone-heading">
    <h4>{{ $scheme ? 'Edit Aggregation Scheme' : 'New Aggregation Scheme' }}</h4>
    <p>Select the assessments that participate in the aggregated result and enter each one's weight manually. Weights must total 100% before the aggregate can be calculated.</p>
</div>

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

<form method="POST" action="{{ $scheme ? route('settings.academic.aggregations.update', $scheme) : route('settings.academic.aggregations.store') }}" id="schemeForm">
    @csrf
    @if ($scheme)@method('PUT')@endif

    <div class="admin-card mb-3">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="ag_name">Scheme Name *</label>
                <input type="text" id="ag_name" name="name" class="form-control" maxlength="120" required
                       value="{{ old('name', $scheme?->name) }}" placeholder="e.g. Annual Final Result 2026">
                @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="ag_year">Academic Year *</label>
                <select id="ag_year" name="academic_year_id" class="form-select" required>
                    <option value="">Select year</option>
                    @foreach ($academicYears as $year)
                        <option value="{{ $year->id }}"
                                @selected((int) old('academic_year_id', $scheme?->academic_year_id) === (int) $year->id)>
                            {{ $year->name }}@if ($year->is_current) (Current)@endif
                        </option>
                    @endforeach
                </select>
                @error('academic_year_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="ag_class">Class / Grade *</label>
                <select id="ag_class" name="class_grade_id" class="form-select" required>
                    <option value="">Select class</option>
                    @foreach ($classes as $entry)
                        <option value="{{ $entry['class_grade']->id }}"
                                @selected($scheme !== null && (int) $scheme->class_grade_id === (int) $entry['class_grade']->id)>
                            {{ $entry['name'] }}@if ($entry['level_name']) — {{ $entry['level_name'] }}@endif
                        </option>
                    @endforeach
                </select>
                @error('class_grade_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label" for="ag_group">Group / Stream</label>
                <select id="ag_group" name="academic_group_id" class="form-select">
                    <option value="">Whole class</option>
                    @if ($scheme !== null && $scheme->classGrade !== null)
                        @foreach ($scheme->classGrade->groups()->where('status', true)->get() as $group)
                            <option value="{{ $group->id }}" @selected($scheme->academic_group_id === $group->id)>{{ $group->name }}</option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="ag_status">Status</label>
                <select id="ag_status" name="status" class="form-select">
                    @foreach (['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $scheme?->status ?? 'draft') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Assessments + weights editor --}}
    <div class="admin-card mb-3" id="assessmentsCard" data-assessments-url="{{ $scheme ? route('settings.academic.aggregations.assessments', $scheme) : '' }}">
        <div class="table-toolbar">
            <div class="toolbar-info">
                <i class="bi bi-pie-chart"></i>
                <span class="fw-semibold">Participating Assessments &amp; Weights</span>
                <span class="text-muted small ms-2">Manual weightage — enter values, no defaults applied.</span>
            </div>
        </div>
        <div class="p-3">
            <p class="text-muted small mb-3">
                Select an academic year and class. Assessments configured for that context will appear below.
            </p>

            @if ($scheme && isset($preselectedItems))
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th style="width:40px">Use</th>
                            <th>Assessment</th>
                            <th style="width:160px">Weight %</th>
                        </tr>
                    </thead>
                    <tbody data-scheme-items>
                        @foreach ($preselectedItems as $index => $item)
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input ms-0 aggregation-active" checked>
                                </td>
                                <td>
                                    {{ $item['name'] }}
                                    <input type="hidden" name="items[{{ $index }}][assessment_id]" value="{{ $item['assessment_id'] }}">
                                    <input type="hidden" name="items[{{ $index }}][active]" value="1">
                                </td>
                                <td>
                                    <input type="number" step="any" min="0" max="100" class="form-control form-control-sm aggregation-weight"
                                           name="items[{{ $index }}][weight]" value="{{ $item['weight'] }}" required>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th style="width:40px">Use</th>
                            <th>Assessment</th>
                            <th style="width:160px">Weight %</th>
                        </tr>
                    </thead>
                    <tbody data-scheme-items>
                        <tr>
                            <td colspan="3" class="text-center text-muted py-3">
                                <i class="bi bi-info-circle me-1"></i>Choose a year and class to load available assessments.
                            </td>
                        </tr>
                    </tbody>
                </table>
            @endif

            <div class="d-flex align-items-center gap-2 mt-2">
                <span class="text-muted small">Total weight:</span>
                <span class="fw-bold aggregation-total">0%</span>
                <span class="aggregation-warning text-danger small d-none">
                    <i class="bi bi-exclamation-triangle me-1"></i>Weight must total 100%
                </span>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-2">
        <button class="btn btn-primary" type="submit">
            <i class="bi bi-check-lg me-1"></i>{{ $scheme ? 'Save Changes' : 'Create Scheme' }}
        </button>
        <a href="{{ route('settings.academic.aggregations.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

@endsection

@push('scripts')
<script>
(function () {
    var yearSel = document.getElementById('ag_year');
    var classSel = document.getElementById('ag_class');
    var groupSel = document.getElementById('ag_group');
    var card = document.getElementById('assessmentsCard');
    var tbody = card.querySelector('[data-scheme-items]');
    var loaded = (function () {
        var hasRows = tbody.querySelectorAll('input[name$="[assessment_id]"]').length > 0;
        return hasRows;
    })();
    var isAjax = window.Monetix && Monetix.request;

    function totalWeight() {
        var total = 0;
        tbody.querySelectorAll('.aggregation-weight').forEach(function (input) {
            if (input.closest('tr').classList.contains('aggregation-off')) { return; }
            var v = parseFloat(input.value);
            if (!isNaN(v)) { total += v; }
        });
        return Math.round(total * 100) / 100;
    }

    function updateTotals() {
        var total = totalWeight();
        var el = card.querySelector('.aggregation-total');
        var warn = card.querySelector('.aggregation-warning');
        if (el) { el.textContent = total + '%'; }
        if (warn) { warn.classList.toggle('d-none', Math.abs(total - 100) < 0.005); }
    }

    function bindWeightEvents() {
        tbody.querySelectorAll('.aggregation-weight').forEach(function (input) {
            if (input.dataset.bound) { return; }
            input.dataset.bound = '1';
            input.addEventListener('input', updateTotals);
        });
        tbody.querySelectorAll('.aggregation-active').forEach(function (cb) {
            if (cb.dataset.bound) { return; }
            cb.dataset.bound = '1';
            cb.addEventListener('change', function () {
                var tr = cb.closest('tr');
                var hidden = tr.querySelector('input[name$="[active]"]');
                var weight = tr.querySelector('.aggregation-weight');
                tr.classList.toggle('aggregation-off', !cb.checked);
                if (hidden) { hidden.value = cb.checked ? '1' : '0'; }
                if (weight) { weight.disabled = !cb.checked; }
                updateTotals();
            });
        });
    }

    function loadAssessments() {
        if (!isAjax) { return; }
        var yearId = yearSel.value;
        var classId = classSel.value;
        if (!yearId || !classId) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Choose a year and class to load available assessments.</td></tr>';
            updateTotals();
            return;
        }

        var params = new URLSearchParams();
        params.set('academic_year_id', yearId);
        params.set('class_grade_id', classId);
        if (groupSel && groupSel.value) { params.set('academic_group_id', groupSel.value); }

        var url = card.getAttribute('data-assessments-url') + '?' + params.toString();
        Monetix.request(url, { method: 'GET', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': Monetix.csrfToken && Monetix.csrfToken() } })
            .then(function (res) {
                var pool = (res && res.assessments) || [];
                if (!pool.length) {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">No assessments are configured for this context yet.</td></tr>';
                    updateTotals();
                    return;
                }
                tbody.innerHTML = '';
                pool.forEach(function (a, i) {
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td><input type="checkbox" class="form-check-input ms-0 aggregation-active" checked></td>' +
                        '<td>' + a.name + '<input type="hidden" name="items[' + i + '][assessment_id]" value="' + a.id + '"><input type="hidden" name="items[' + i + '][active]" value="1"></td>' +
                        '<td><input type="number" step="any" min="0" max="100" class="form-control form-control-sm aggregation-weight" name="items[' + i + '][weight]" value="0" required></td>';
                    tbody.appendChild(tr);
                });
                bindWeightEvents();
                updateTotals();
            });
    }

    bindWeightEvents();

    function resetItems() {
        if (loaded) { loaded = false; return; }
        tbody.querySelectorAll('tr').forEach(function (tr) { tr.parentNode.removeChild(tr); });
        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Choose a year and class to load available assessments.</td></tr>';
        updateTotals();
    }

    [yearSel, classSel].forEach(function (sel) {
        if (sel) { sel.addEventListener('change', function () { resetItems(); loadAssessments(); }); }
    });
    if (groupSel) { groupSel.addEventListener('change', function () { resetItems(); loadAssessments(); }); }
})();
</script>
@endpush
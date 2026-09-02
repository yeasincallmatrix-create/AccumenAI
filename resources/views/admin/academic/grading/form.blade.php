@extends('layouts.admin')

@section('title', ($scale ? 'Edit' : 'New').' Grade Scale — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $scale ? 'Edit Grade Scale' : 'New Grade Scale' }}</h4>
        <p class="page-header-desc">Choose the ladder rung this default applies to. Bands are closed ranges (min ≤ score ≤ max) and must not overlap; a score resolves deterministically to one band.</p>
    </div>
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

@php
    $oldRows = old('rows');
    $bands = $oldRows !== null ? $oldRows : $scale?->rows ?? [];
@endphp

<form method="POST" action="{{ $scale ? route('admin.academic.grading.update', $scale) : route('admin.academic.grading.store') }}">
    @csrf
    @if ($scale)@method('PUT')@endif

    <div class="admin-card mb-3">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label" for="gs_name">Scale Name *</label>
                <input type="text" id="gs_name" name="name" class="form-control" maxlength="120" required
                       value="{{ old('name', $scale?->name) }}" placeholder="e.g. Bangladesh A+ .. F">
                @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="gs_country">Country</label>
                <select id="gs_country" name="country_id" class="form-select">
                    <option value="">Global Default</option>
                    @foreach ($options['countries'] as $country)
                        <option value="{{ $country->id }}" @selected((int) old('country_id', $scope['country_id']) === (int) $country->id)>{{ $country->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="gs_system">Education System</label>
                <select id="gs_system" name="education_system_id" class="form-select">
                    <option value="">None (country or global level)</option>
                    @foreach ($options['systems'] as $system)
                        <option value="{{ $system->id }}" @selected((int) old('education_system_id', $scope['system_id']) === (int) $system->id)>{{ $system->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="gs_level">Academic Level</label>
                <select id="gs_level" name="academic_level_id" class="form-select">
                    <option value="">None (system or country level)</option>
                    @foreach ($levels as $level)
                        <option value="{{ $level->id }}" @selected((int) old('academic_level_id', $scope['level_id']) === (int) $level->id)>{{ $level->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="gs_gpa">GPA Mode</label>
                <select id="gs_gpa" name="gpa_mode" class="form-select">
                    @foreach (['equal_weight' => 'Equal Weight', 'credit_weighted' => 'Credit Weighted'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('gpa_mode', $scale?->gpa_mode ?? 'equal_weight') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="gs_optional">Optional Subjects in GPA</label>
                <select id="gs_optional" name="optional_subject_gpa" class="form-select">
                    @foreach (['included' => 'Included', 'excluded' => 'Excluded'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('optional_subject_gpa', $scale?->optional_subject_gpa ?? 'included') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="gs_order">Display Order</label>
                <input type="number" id="gs_order" name="display_order" class="form-control" min="0"
                       value="{{ old('display_order', $scale?->display_order ?? 0) }}">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="gs_status">Active</label>
                <select id="gs_status" name="status" class="form-select">
                    <option value="1" @selected((bool) old('status', $scale?->status ?? true))>Yes</option>
                    <option value="0" @selected(! (bool) old('status', $scale?->status ?? true))>No</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Grade bands editor --}}
    <div class="admin-card mb-3">
        <div class="table-toolbar">
            <div class="toolbar-info"><i class="bi bi-table"></i> <span class="fw-semibold">Grade Bands</span></div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="gs-add-row"><i class="bi bi-plus-lg me-1"></i>Add Band</button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" id="gs-bands-table">
                <thead>
                    <tr>
                        <th style="width:70px">Active</th>
                        <th>Grade</th>
                        <th style="width:120px">Min %</th>
                        <th style="width:120px">Max %</th>
                        <th style="width:110px">Grade Point</th>
                        <th style="width:80px">Pass</th>
                        <th style="width:110px">GPA Incl.</th>
                        <th style="width:50px"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bands as $index => $band)
                        @php $band = is_array($band) ? $band : $band->toArray(); @endphp
                        <tr>
                            <td>
                                <select class="form-select form-select-sm" name="rows[{{ $index }}][active]">
                                    <option value="1" @selected((bool) ($band['active'] ?? $band['status'] ?? true))>Yes</option>
                                    <option value="0" @selected(! (bool) ($band['active'] ?? $band['status'] ?? true))>No</option>
                                </select>
                            </td>
                            <td><input type="text" class="form-control form-control-sm" name="rows[{{ $index }}][grade]" value="{{ $band['grade'] ?? '' }}" required></td>
                            <td><input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm" name="rows[{{ $index }}][min_score]" value="{{ $band['min_score'] ?? 0 }}" required></td>
                            <td><input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm" name="rows[{{ $index }}][max_score]" value="{{ $band['max_score'] ?? 0 }}" required></td>
                            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="rows[{{ $index }}][grade_point]" value="{{ $band['grade_point'] ?? 0 }}" required></td>
                            <td>
                                <select class="form-select form-select-sm" name="rows[{{ $index }}][is_pass]">
                                    <option value="1" @selected((bool) ($band['is_pass'] ?? true))>PASS</option>
                                    <option value="0" @selected(! (bool) ($band['is_pass'] ?? true))>FAIL</option>
                                </select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm" name="rows[{{ $index }}][gpa_included]">
                                    <option value="1" @selected((bool) ($band['gpa_included'] ?? true))>Yes</option>
                                    <option value="0" @selected(! (bool) ($band['gpa_included'] ?? true))>No</option>
                                </select>
                            </td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger gs-remove-row"><i class="bi bi-x-lg"></i></button></td>
                        </tr>
                    @empty
                        <tr class="gs-empty-row">
                            <td colspan="8" class="text-center text-muted py-3">No bands yet — click "Add Band".</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex gap-2 mt-2">
        <button class="btn btn-primary" type="submit">
            <i class="bi bi-check-lg me-1"></i>{{ $scale ? 'Save Changes' : 'Create Scale' }}
        </button>
        <a href="{{ route('admin.academic.grading.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

@endsection

@push('scripts')
<script>
(function () {
    var table = document.getElementById('gs-bands-table');
    var tbody = table.querySelector('tbody');

    function reindex() {
        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        rows.forEach(function (tr, i) {
            tr.querySelectorAll('input, select').forEach(function (el) {
                el.name = el.name.replace(/rows\[\d+\]/, 'rows[' + i + ']');
            });
        });
    }

    function removeEmptyRow() {
        var empty = tbody.querySelector('.gs-empty-row');
        if (empty) { empty.remove(); }
    }

    function addRow() {
        removeEmptyRow();
        var i = tbody.querySelectorAll('tr').length;
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td><select class="form-select form-select-sm" name="rows[' + i + '][active]"><option value="1">Yes</option><option value="0">No</option></select></td>' +
            '<td><input type="text" class="form-control form-control-sm" name="rows[' + i + '][grade]" required></td>' +
            '<td><input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm" name="rows[' + i + '][min_score]" value="0" required></td>' +
            '<td><input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm" name="rows[' + i + '][max_score]" value="0" required></td>' +
            '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="rows[' + i + '][grade_point]" value="0" required></td>' +
            '<td><select class="form-select form-select-sm" name="rows[' + i + '][is_pass]"><option value="1">PASS</option><option value="0">FAIL</option></select></td>' +
            '<td><select class="form-select form-select-sm" name="rows[' + i + '][gpa_included]"><option value="1">Yes</option><option value="0">No</option></select></td>' +
            '<td><button type="button" class="btn btn-sm btn-outline-danger gs-remove-row"><i class="bi bi-x-lg"></i></button></td>';
        tbody.appendChild(tr);
        bindRemove(tr.querySelector('.gs-remove-row'));
    }

    function bindRemove(btn) {
        btn.addEventListener('click', function () {
            var tr = btn.closest('tr');
            tr.remove();
            if (!tbody.querySelectorAll('tr').length) {
                tbody.innerHTML = '<tr class="gs-empty-row"><td colspan="8" class="text-center text-muted py-3">No bands yet — click "Add Band".</td></tr>';
            }
            reindex();
        });
    }

    document.getElementById('gs-add-row').addEventListener('click', addRow);
    tbody.querySelectorAll('.gs-remove-row').forEach(bindRemove);
})();
</script>
@endpush

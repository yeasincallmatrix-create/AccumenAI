@extends('layouts.admin')

@section('title', 'Countries — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Countries</h4>
        <p class="page-header-desc">Select countries and perform bulk operations. All associated configurations (grade scales, academic structures, module defaults) are handled consistently.</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.geo.index') }}">
            <i class="bi bi-globe"></i> Geo Manager
        </a>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.academic.index') }}">
            <i class="bi bi-diagram-3"></i> Academic Structure
        </a>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

{{-- Batch actions bar --}}
<div class="admin-card mb-3" id="country-batch-bar">
    <div class="d-flex flex-wrap gap-2 align-items-end">
        <div>
            <label for="batch-action" class="form-label form-label-sm mb-1">Batch action</label>
            <select id="batch-action" class="form-select form-select-sm" style="min-width:220px;">
                <option value="">— Select action —</option>
                <option value="enable">Enable (status = active)</option>
                <option value="disable">Disable (status = inactive)</option>
                <option value="assign_grade_scale">Assign Grade Scale</option>
                <option value="assign_academic_structure">Assign Academic Structure</option>
                <option value="assign_default_modules">Assign Default Modules</option>
                <option value="sync_all">Sync All (run all sync operations)</option>
            </select>
        </div>
        <div class="flex-grow-1">
            <button type="button" id="batch-apply" class="btn btn-primary btn-sm">
                <i class="bi bi-lightning"></i> Apply
            </button>
            <span id="batch-feedback" class="ms-2 small text-muted"></span>
        </div>
        <div class="small text-muted">
            <span id="selected-count">0</span> selected
        </div>
    </div>
    <div id="batch-result" class="mt-3 d-none">
        <div class="alert mb-0" id="batch-result-alert"></div>
        <pre id="batch-result-details" class="mt-2 p-2 bg-light border rounded small" style="max-height:300px; overflow:auto;"></pre>
    </div>
</div>

<div class="filter-card">
    <form class="filter-layout" method="GET" action="{{ route('admin.countries.index') }}">
        <div class="filter-search-row align-items-end">
            <div class="filter-search" style="flex:1 1 0; min-width:220px;">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control form-control-sm" name="q"
                       placeholder="Search by country name or ISO2..." value="{{ $q ?? '' }}">
            </div>
            <div class="filter-span flex-shrink-0">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Search</button>
            </div>
        </div>
    </form>
</div>

<div class="admin-card mt-3">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th style="width:40px;">
                        <input type="checkbox" id="select-all" class="form-check-input">
                    </th>
                    <th>Country</th>
                    <th>ISO2</th>
                    <th>Academic Unit</th>
                    <th>Systems</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($countries as $country)
                    <tr>
                        <td>
                            <input type="checkbox" class="form-check-input country-checkbox" value="{{ $country->id }}">
                        </td>
                        <td>
                            <span class="fw-semibold">{{ $country->name }}</span>
                            <div class="text-muted small">{{ $country->iso3 ?? '—' }}</div>
                        </td>
                        <td><span class="badge text-bg-light border">{{ $country->iso2 }}</span></td>
                        <td>
                            <span class="badge text-bg-light border">{{ $country->academicUnitLabel() }}</span>
                        </td>
                        <td>
                            <span class="badge text-bg-primary">{{ $country->education_systems_count ?? $country->educationSystems()->count() }}</span>
                        </td>
                        <td>
                            @if ($country->status)
                                <span class="badge text-bg-success">Active</span>
                            @else
                                <span class="badge text-bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.geo.edit', $country) }}">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No countries found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var selectAll = document.getElementById('select-all');
    var checkboxes = document.querySelectorAll('.country-checkbox');
    var countEl = document.getElementById('selected-count');
    var actionSel = document.getElementById('batch-action');
    var applyBtn = document.getElementById('batch-apply');
    var feedback = document.getElementById('batch-feedback');
    var resultBox = document.getElementById('batch-result');
    var resultAlert = document.getElementById('batch-result-alert');
    var resultDetails = document.getElementById('batch-result-details');

    function getSelectedIds() {
        var ids = [];
        document.querySelectorAll('.country-checkbox:checked').forEach(function (cb) {
            ids.push(parseInt(cb.value, 10));
        });
        return ids;
    }

    function updateCount() {
        countEl.textContent = getSelectedIds().length;
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            var checked = selectAll.checked;
            document.querySelectorAll('.country-checkbox').forEach(function (cb) { cb.checked = checked; });
            updateCount();
        });
    }

    document.querySelectorAll('.country-checkbox').forEach(function (cb) {
        cb.addEventListener('change', updateCount);
    });

    if (applyBtn) {
        applyBtn.addEventListener('click', function () {
            var ids = getSelectedIds();
            var action = actionSel.value;

            if (!action) {
                feedback.textContent = 'Select an action.';
                feedback.className = 'ms-2 small text-danger';
                return;
            }
            if (ids.length === 0) {
                feedback.textContent = 'Select at least one country.';
                feedback.className = 'ms-2 small text-danger';
                return;
            }

            feedback.textContent = 'Processing...';
            feedback.className = 'ms-2 small text-muted';
            applyBtn.disabled = true;
            resultBox.classList.add('d-none');

            fetch("{{ route('admin.countries.batch') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ country_ids: ids, action: action })
            })
            .then(function (res) {
                return res.json().then(function (data) { return { ok: res.ok, status: res.status, data: data }; });
            })
            .then(function (result) {
                applyBtn.disabled = false;
                if (!result.ok) {
                    feedback.textContent = result.data.message || 'Validation failed.';
                    feedback.className = 'ms-2 small text-danger';
                    resultBox.classList.remove('d-none');
                    resultAlert.className = 'alert alert-danger mb-0';
                    resultAlert.textContent = result.data.message || 'Request failed (' + result.status + ')';
                    if (result.data.errors) {
                        resultDetails.textContent = JSON.stringify(result.data.errors, null, 2);
                    } else {
                        resultDetails.textContent = JSON.stringify(result.data, null, 2);
                    }
                    return;
                }

                var s = result.data.summary;
                feedback.textContent = 'Done: ' + (s.success || 0) + ' success, ' + (s.skipped || 0) + ' skipped, ' + (s.failed || 0) + ' failed.';
                feedback.className = 'ms-2 small text-success';
                resultBox.classList.remove('d-none');
                resultAlert.className = 'alert alert-success mb-0';
                resultAlert.textContent = result.data.message + ' (' + action + ') — ' + (s.success||0) + ' success / ' + (s.skipped||0) + ' skipped / ' + (s.failed||0) + ' failed (total ' + (s.total||0) + ')';
                resultDetails.textContent = JSON.stringify(s, null, 2);

                // Optionally reload to reflect status changes
                if (action === 'enable' || action === 'disable') {
                    setTimeout(function () { window.location.reload(); }, 900);
                }
            })
            .catch(function (err) {
                applyBtn.disabled = false;
                feedback.textContent = 'Network error.';
                feedback.className = 'ms-2 small text-danger';
                resultBox.classList.remove('d-none');
                resultAlert.className = 'alert alert-danger mb-0';
                resultAlert.textContent = 'Network error: ' + err.message;
                resultDetails.textContent = String(err);
            });
        });
    }
})();
</script>
@endpush

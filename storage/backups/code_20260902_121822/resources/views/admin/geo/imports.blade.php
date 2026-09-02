@extends('layouts.admin')

@section('title', 'Import Geography Package — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('admin_geo.import_title') }}</h4>
        <p class="page-header-desc">{{ mawa_e('admin_geo.import_subtitle') }}</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="{{ route('admin.geo.index') }}">
            <i class="bi bi-arrow-left"></i> {{ mawa_e('admin_geo.title') }}
        </a>
    </div>
</div>

<div class="admin-card" style="max-width:760px;">
    <form id="geoImportForm">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="country_id">{{ mawa_e('admin_geo.import_select_country') }}</label>
                <select id="country_id" name="country_id" class="form-select" required>
                    <option value="">Select a country</option>
                    @foreach ($countries as $c)
                        <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->iso2 }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="mode">{{ mawa_e('admin_geo.import_mode') }}</label>
                <select id="mode" name="mode" class="form-select">
                    <option value="upsert">Upsert (insert new, update existing)</option>
                    <option value="add">Add only (existing codes are skipped)</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="file">{{ mawa_e('admin_geo.import_file') }}</label>
                <input type="file" id="file" name="file" class="form-control"
                       accept=".jsonl,.ndjson,.json,.csv" required>
                <div class="form-text">
                    .jsonl / .ndjson (one JSON object per line) &middot; .json (array of objects) &middot; .csv (header row)
                </div>
            </div>
        </div>

        <div id="importError" class="alert alert-danger mt-3 d-none"></div>

        <div id="progressCard" class="mt-3 d-none">
            <div class="table-toolbar">
                <div class="toolbar-info" id="progressLabel"><i class="bi bi-arrow-repeat"></i> Processing…</div>
            </div>
            <div class="progress mt-2" style="height:20px;">
                <div id="progressBar" class="progress-bar" role="progressbar" style="width:0%"></div>
            </div>
            <div class="small text-muted mt-2" id="progressStats"></div>
            <pre id="progressErrors" class="bg-light border rounded small p-2 mt-2 d-none" style="white-space:pre-wrap;"></pre>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" id="btnValidate" class="btn btn-outline-primary">
                <i class="bi bi-check2-circle"></i> {{ mawa_e('admin_geo.import_validate') }}
            </button>
            <button type="button" id="btnRun" class="btn btn-primary">
                <i class="bi bi-upload"></i> {{ mawa_e('admin_geo.import_run') }}
            </button>
            <button type="button" id="btnStop" class="btn btn-outline-danger d-none">
                <i class="bi bi-stop-fill"></i> Stop
            </button>
        </div>
    </form>
</div>

<div class="admin-card mt-4">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-clock-history"></i> {{ mawa_e('admin_geo.import_history') }}</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>{{ mawa_e('admin_geo.country') }}</th>
                    <th>{{ mawa_e('admin_geo.import_file') }}</th>
                    <th>{{ mawa_e('admin_geo.import_mode') }}</th>
                    <th>{{ mawa_e('admin_geo.import_status') }}</th>
                    <th>{{ mawa_e('admin_geo.import_records') }}</th>
                    <th class="text-end">{{ mawa_e('admin_geo.import_added') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($imports as $import)
                    <tr>
                        <td class="fw-semibold">{{ $import->country?->name ?? '—' }}</td>
                        <td>{{ $import->filename }} <span class="text-muted small">({{ strtoupper($import->format) }})</span></td>
                        <td>{{ $import->mode }}</td>
                        <td>
                            @if ($import->status === 'completed')
                                <span class="badge text-bg-success">Completed</span>
                            @elseif ($import->status === 'failed')
                                <span class="badge text-bg-danger">Failed</span>
                            @elseif ($import->status === 'importing')
                                <span class="badge text-bg-primary">Importing</span>
                            @elseif ($import->status === 'validated')
                                <span class="badge text-bg-info">Validated</span>
                            @elseif ($import->status === 'validating')
                                <span class="badge text-bg-warning">Validating</span>
                            @else
                                <span class="badge text-bg-secondary">{{ $import->status }}</span>
                            @endif
                        </td>
                        <td>{{ number_format($import->total_records) }}</td>
                        <td class="text-end">{{ number_format($import->inserted_records) }} ▲ {{ number_format($import->updated_records) }} ✎</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ mawa_e('admin_geo.import_no_history') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var form = document.getElementById('geoImportForm');
    var fileInput = document.getElementById('file');
    var countryInput = document.getElementById('country_id');
    var btnRun = document.getElementById('btnRun');
    var btnValidate = document.getElementById('btnValidate');
    var btnStop = document.getElementById('btnStop');
    var errorBox = document.getElementById('importError');
    var progressCard = document.getElementById('progressCard');
    var progressBar = document.getElementById('progressBar');
    var progressLabel = document.getElementById('progressLabel');
    var progressStats = document.getElementById('progressStats');
    var progressErrors = document.getElementById('progressErrors');

    function showError(msg) {
        errorBox.textContent = msg || 'Operation failed.';
        errorBox.classList.remove('d-none');
    }
    function hideError() { errorBox.classList.add('d-none'); }

    function post(url, fd) {
        return fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            body: fd === undefined ? new FormData(form) : fd
        }).then(function (resp) {
            return resp.json().then(function (data) {
                if (!data.success) { throw new Error(data.message || 'Request failed.'); }
                return data;
            }).catch(function () {
                if (resp.ok) { throw new Error('Invalid server response.'); }
                var msg = resp.status === 422 ? 'Validation failed. Check the form.' : 'Server error.';
                throw new Error(msg);
            });
        });
    }

    function postEmpty(url) {
        return post(url, new FormData());
    }

    function gotAnImport(data) {
        return data && data.data && data.data.import;
    }

    function renderProgress(p) {
        var total = parseInt(p.total_records, 10) || 0;
        var inserted = parseInt(p.inserted_records, 10) || 0;
        var updated = parseInt(p.updated_records, 10) || 0;
        var skipped = parseInt(p.skipped_records, 10) || 0;
        var dups = parseInt(p.duplicate_count, 10) || 0;
        var errors = parseInt(p.error_count, 10) || 0;
        var finished = ['completed', 'failed', 'validated'].indexOf(p.status) !== -1;

        progressStats.textContent = total + ' records · ' + inserted + ' added · ' + updated + ' updated · '
            + skipped + ' skipped · ' + dups + ' duplicates · ' + errors + ' errors';

        if (finished) {
            progressLabel.textContent = p.status === 'failed' ? 'Finished with errors.' : 'Finished.';
            progressBar.style.width = '100%';
            btnRun.classList.add('d-none');
            btnValidate.classList.add('d-none');
            btnStop.classList.add('d-none');
        } else {
            progressBar.style.width = Math.min(95, (total % 50) + 40) + '%';
        }
        if (p.error_summary) {
            progressErrors.textContent = p.error_summary;
            progressErrors.classList.remove('d-none');
        }
    }

    function safeId(v) {
        // Guard: prevent "[object HTMLInputElement]" if an element was passed instead of its value
        if (v === null || v === undefined) return '';
        var s = String(v).trim();
        if (!s || s.indexOf('[object') !== -1 || s.indexOf('%5Bobject') !== -1) return '';
        // Only numeric IDs expected
        if (!/^\d+$/.test(s)) return '';
        return s;
    }
    function startPoll(importId) {
        importId = safeId(importId);
        if (!importId) { showError('Invalid import ID.'); return; }
        progressCard.classList.remove('d-none');
        var stopped = false;
        btnStop.classList.remove('d-none');
        btnStop.onclick = function () { stopped = true; btnStop.classList.add('d-none'); };

        function tick() {
            fetch('{{ route("admin.geo.imports.status", ["import" => "ID"]) }}'.replace('ID', encodeURIComponent(importId)), {
                headers: { 'Accept': 'application/json' }
            }).then(function (resp) {
                return resp.json();
            }).then(function (data) {
                if (!data.success) { throw new Error(data.message || 'Server error.'); }
                var p = data.data.import;
                renderProgress(p);
                if (['completed', 'failed', 'validated'].indexOf(p.status) !== -1) { return; }
                return postEmpty('{{ route("admin.geo.imports.run", ["import" => "ID"]) }}'.replace('ID', encodeURIComponent(importId))
                    .then(function (rd) {
                        var np = rd.data.import;
                        renderProgress(np);
                        if (stopped) { return; }
                        setTimeout(tick, 600);
                    });
            }).catch(function (err) {
                showError(err.message);
                btnStop.classList.add('d-none');
            });
        }
        tick();
    }

    function uploadOnly() {
        hideError();
        btnRun.disabled = true; btnValidate.disabled = true;
        return post('{{ route("admin.geo.imports.store") }}', new FormData(form)).then(function (data) {
            btnRun.disabled = false; btnValidate.disabled = false;
            return data;
        }).catch(function (err) {
            btnRun.disabled = false; btnValidate.disabled = false;
            showError(err.message);
            throw err;
        });
    }

    btnValidate.addEventListener('click', function () {
        uploadOnly().then(function (data) {
            var vid = safeId(data.data.import.id);
            if (!vid) { throw new Error('Invalid import ID returned.'); }
            progressCard.classList.remove('d-none');
            progressLabel.textContent = 'Validating…';
            return postEmpty('{{ route("admin.geo.imports.validate", ["import" => "ID"]) }}'.replace('ID', encodeURIComponent(vid)));
        }).then(function (data) {
            renderProgress(data.data.import);
            location.reload();
        }).catch(function () {});
    });

    btnRun.addEventListener('click', function () {
        uploadOnly().then(function (data) {
            var rid = safeId(data.data.import.id);
            if (!rid) { throw new Error('Invalid import ID returned.'); }
            startPoll(rid);
        }).catch(function () {});
    });
})();
</script>
@endsection
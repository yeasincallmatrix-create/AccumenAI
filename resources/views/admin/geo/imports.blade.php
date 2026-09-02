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
                <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0%"></div>
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

        {{-- Global waiting overlay while uploading / validating / importing --}}
        <div id="geoLoadingOverlay" class="d-none" style="position:fixed;inset:0;background:rgba(255,255,255,.78);backdrop-filter:blur(2px);z-index:1055;display:flex;align-items:center;justify-content:center;flex-direction:column;">
            <div class="spinner-border text-primary" role="status" style="width:3rem;height:3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div id="geoLoadingText" class="mt-3 fw-semibold text-dark">Uploading… please wait</div>
            <div class="small text-muted mt-1">Do not close or reload the page</div>
        </div>
        <style>
            #geoLoadingOverlay.d-flex{display:flex !important;}
            #geoLoadingOverlay.d-none{display:none !important;}
        </style>
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
    var tokenEl = document.querySelector('meta[name="csrf-token"]');
    var token = tokenEl ? tokenEl.getAttribute('content') : '';
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
    var overlay = document.getElementById('geoLoadingOverlay');
    var overlayText = document.getElementById('geoLoadingText');

    function showError(msg) {
        errorBox.textContent = msg || 'Operation failed.';
        errorBox.classList.remove('d-none');
        // auto-scroll to error
        try { errorBox.scrollIntoView({behavior:'smooth', block:'center'}); } catch(e){}
    }
    function hideError() { errorBox.classList.add('d-none'); errorBox.textContent=''; }

    function showLoading(text){
        if(overlay){
            overlayText.textContent = text || 'Processing… please wait';
            overlay.classList.remove('d-none');
            overlay.classList.add('d-flex');
        }
        btnRun.disabled = true; btnValidate.disabled = true;
        // add spinner to buttons visually
        btnRun.style.opacity = '.7'; btnValidate.style.opacity = '.7';
    }
    function hideLoading(){
        if(overlay){
            overlay.classList.add('d-none');
            overlay.classList.remove('d-flex');
        }
        btnRun.disabled = false; btnValidate.disabled = false;
        btnRun.style.opacity = ''; btnValidate.style.opacity = '';
    }

    function parseJsonSafe(text){
        try { return JSON.parse(text); } catch(e){ return null; }
    }

    function post(url, fd) {
        return fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            body: fd === undefined ? new FormData(form) : fd
        }).then(function (resp) {
            return resp.text().then(function (text) {
                var data = parseJsonSafe(text);
                if (data && typeof data.success !== 'undefined') {
                    if (!data.success) {
                        // Surface Laravel validation errors nicely
                        var msg = data.message || 'Request failed.';
                        if (data.errors) {
                            var first = Object.values(data.errors)[0];
                            if (Array.isArray(first)) msg = first[0];
                        }
                        if (data.data && data.data.error_summary) msg = data.data.error_summary;
                        throw new Error(msg);
                    }
                    return data;
                }
                // Non-JSON or unexpected shape
                if (!resp.ok) {
                    var fallback = data && data.message ? data.message : text.substring(0,300);
                    throw new Error('Server error ('+resp.status+'): ' + fallback);
                }
                throw new Error('Invalid server response.');
            });
        });
    }

    function postEmpty(url) {
        return post(url, new FormData());
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
            progressLabel.textContent = p.status === 'failed' ? 'Finished with errors.' : (p.status === 'validated' ? 'Validation finished.' : 'Import completed.');
            progressBar.style.width = '100%';
            progressBar.classList.remove('progress-bar-animated');
            btnStop.classList.add('d-none');
            hideLoading();
            // re-enable Configure buttons visually
            btnRun.classList.remove('d-none');
            btnValidate.classList.remove('d-none');
            if (p.status !== 'failed' && errors === 0) {
                setTimeout(function(){ location.reload(); }, 1200);
            }
        } else {
            // indeterminate while importing
            var pct = total > 0 ? Math.min(95, Math.round((inserted+updated)/Math.max(1,total)*100)) : 45;
            if (pct < 10) pct = 45;
            progressBar.style.width = pct + '%';
            progressBar.classList.add('progress-bar-animated');
        }
        if (p.error_summary) {
            progressErrors.textContent = p.error_summary;
            progressErrors.classList.remove('d-none');
        } else {
            progressErrors.classList.add('d-none');
            progressErrors.textContent = '';
        }
    }

    function safeId(v) {
        if (v === null || v === undefined) return '';
        var s = String(v).trim();
        if (!s || s.indexOf('[object') !== -1 || s.indexOf('%5Bobject') !== -1) return '';
        if (!/^\d+$/.test(s)) return '';
        return s;
    }

    function validateForm(){
        hideError();
        if (!countryInput.value) { showError('Please select a country.'); countryInput.focus(); return false; }
        if (!fileInput.files || !fileInput.files.length) { showError('Please choose a file (.jsonl, .json, .csv).'); fileInput.focus(); return false; }
        var f = fileInput.files[0];
        var ext = (f.name.split('.').pop()||'').toLowerCase();
        if (['jsonl','ndjson','json','csv'].indexOf(ext)===-1) { showError('Unsupported file type. Allowed: jsonl, ndjson, json, csv.'); return false; }
        if (f.size > 102400*1024) { showError('File exceeds 100 MB limit.'); return false; }
        return true;
    }

    function uploadOnly() {
        if (!validateForm()) return Promise.reject(new Error('Fix form errors.'));
        hideError();
        showLoading('Uploading file… please wait');
        progressCard.classList.remove('d-none');
        progressLabel.textContent = 'Uploading…';
        progressBar.style.width = '30%';
        return post('{{ route("admin.geo.imports.store") }}', new FormData(form)).then(function (data) {
            progressBar.style.width = '55%';
            return data;
        }).catch(function (err) {
            hideLoading();
            progressCard.classList.add('d-none');
            showError(err.message);
            throw err;
        });
    }

    function startPoll(importId) {
        importId = safeId(importId);
        if (!importId) { hideLoading(); showError('Invalid import ID.'); return; }
        progressCard.classList.remove('d-none');
        showLoading('Importing… please wait');
        progressLabel.textContent = 'Importing…';
        var stopped = false;
        btnStop.classList.remove('d-none');
        btnStop.onclick = function () { stopped = true; btnStop.classList.add('d-none'); hideLoading(); showError('Import stopped by user.'); };

        function tick() {
            fetch('{{ route("admin.geo.imports.status", ["import" => "ID"]) }}'.replace('ID', encodeURIComponent(importId)), {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token }
            }).then(function (resp) { return resp.text().then(function(t){ var d=parseJsonSafe(t); if(!d||!d.success) throw new Error((d&&d.message)||'Status check failed ('+resp.status+')'); return d; }); }).then(function (data) {
                var p = data.data.import;
                renderProgress(p);
                if (['completed', 'failed', 'validated'].indexOf(p.status) !== -1) { hideLoading(); return; }
                // trigger next batch
                return postEmpty('{{ route("admin.geo.imports.run", ["import" => "ID"]) }}'.replace('ID', encodeURIComponent(importId))).then(function (rd) {
                    var np = rd.data.import;
                    renderProgress(np);
                    if (['completed', 'failed', 'validated'].indexOf(np.status) !== -1) { hideLoading(); return; }
                    if (stopped) { hideLoading(); return; }
                    overlayText.textContent = 'Importing… ' + (np.inserted_records + np.updated_records) + ' / ' + np.total_records + ' processed';
                    setTimeout(tick, 600);
                });
            }).catch(function (err) {
                hideLoading();
                showError(err.message);
                btnStop.classList.add('d-none');
            });
        }
        tick();
    }

    btnValidate.addEventListener('click', function () {
        uploadOnly().then(function (data) {
            var vid = safeId(data.data.import.id);
            if (!vid) { throw new Error('Invalid import ID returned.'); }
            progressCard.classList.remove('d-none');
            progressLabel.textContent = 'Validating…';
            showLoading('Validating package… please wait');
            progressBar.style.width = '70%';
            return postEmpty('{{ route("admin.geo.imports.validate", ["import" => "ID"]) }}'.replace('ID', encodeURIComponent(vid)));
        }).then(function (data) {
            renderProgress(data.data.import);
            hideLoading();
        }).catch(function (err) {
            hideLoading();
            if (err.message !== 'Fix form errors.') showError(err.message);
        });
    });

    btnRun.addEventListener('click', function () {
        uploadOnly().then(function (data) {
            var rid = safeId(data.data.import.id);
            if (!rid) { throw new Error('Invalid import ID returned.'); }
            hideLoading(); // upload done, tick will show loading again
            startPoll(rid);
        }).catch(function (err) {
            hideLoading();
            if (err.message !== 'Fix form errors.') {/* already shown */}
        });
    });

    // UX: clear error on change
    countryInput.addEventListener('change', hideError);
    fileInput.addEventListener('change', function(){ hideError(); if(fileInput.files.length) progressLabel.textContent = fileInput.files[0].name + ' ready'; });
})();
</script>
@endsection
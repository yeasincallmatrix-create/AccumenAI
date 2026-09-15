@extends('layouts.admin')

@section('title', 'Import Geography — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Geography Import</h4>
        <p class="page-header-desc">Import country administrative hierarchy data (division → district → upazila).</p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('admin.geo.index') }}">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

<div class="row g-3 align-items-start">

{{-- ─── PRIMARY: Load from Source ─── --}}
<div class="col-lg-6">
<div class="admin-card h-100">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-folder2-open"></i> Import from Source Files</div>
    </div>
    <p class="text-muted small mb-3">Select a country, then click a file to clear old data and import fresh.</p>

    <div class="row g-3 mb-3">
        <div class="col-12">
            <label class="form-label fw-semibold">Country</label>
            <select id="source_country" class="form-select">
                <option value="">Select a country</option>
                @foreach ($countries as $c)
                    <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->iso2 }})</option>
                @endforeach
            </select>
        </div>
    </div>

    <label class="form-label fw-semibold">Available Files</label>
    <div id="sourceFilesList" class="list-group mb-2">
        <div class="text-muted small">Select a country first</div>
    </div>
    <div id="sourceMsg" class="mt-2 d-none"></div>
</div>
</div>

{{-- ─── SECONDARY: Manual Upload + Danger Zone ─── --}}
<div class="col-lg-6">

{{-- Manual Upload --}}
<div class="admin-card mb-3">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-upload"></i> Upload Custom File</div>
    </div>
    <p class="text-muted small mb-3">For country data not in <code>database/geo/</code> yet.</p>
    <form id="geoImportForm">
        @csrf
        <input type="hidden" name="mode" value="upsert">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Country</label>
                <select id="upload_country" name="country_id" class="form-select" required>
                    <option value="">Select a country</option>
                    @foreach ($countries as $c)
                        <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->iso2 }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">File</label>
                <input type="file" id="file" name="file" class="form-control"
                       accept=".jsonl,.ndjson,.json,.csv" required>
                <div class="form-text">.jsonl / .json / .csv</div>
                <div id="formatBadge" class="mt-2 d-none"></div>
            </div>
        </div>

        <div id="importError" class="alert alert-danger mt-3 d-none"></div>

        <div id="progressCard" class="mt-3 d-none">
            <div class="progress" style="height:20px;">
                <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0%"></div>
            </div>
            <div class="small text-muted mt-2" id="progressStats"></div>
            <pre id="progressErrors" class="bg-light border rounded small p-2 mt-2 d-none" style="white-space:pre-wrap;"></pre>
        </div>

        <div id="previewCard" class="mt-3 d-none">
            <div class="small mt-2" id="previewBody"></div>
            <div id="previewConfirmWrap" class="form-check mt-2 d-none">
                <input class="form-check-input" type="checkbox" id="previewConfirm">
                <label class="form-check-label fw-semibold" for="previewConfirm" id="previewConfirmLabel">I understand — allow Run.</label>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <button type="button" id="btnValidate" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-check2-circle"></i> Validate
            </button>
            <button type="button" id="btnRun" class="btn btn-primary btn-sm">
                <i class="bi bi-upload"></i> Run Import
            </button>
        </div>
    </form>
</div>

{{-- Danger Zone --}}
<div class="admin-card border-danger">
    <div class="table-toolbar">
        <div class="toolbar-info text-danger"><i class="bi bi-exclamation-triangle"></i> Danger Zone</div>
    </div>
    <p class="text-muted small">Delete all geo data for a country. Type the country name to confirm.</p>
    <form id="geoClearForm">
        @csrf
        <div class="row g-3">
            <div class="col-12">
                <select id="clear_country_id" name="country_id" class="form-select">
                    <option value="">Select a country</option>
                    @foreach ($countries as $c)
                        <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->iso2 }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <input type="text" id="clear_confirm" class="form-control" placeholder="Type country name" autocomplete="off">
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="button" id="btnClearPreview" class="btn btn-outline-secondary btn-sm">Preview</button>
                <button type="button" id="btnClear" class="btn btn-danger btn-sm" disabled>Delete All</button>
            </div>
        </div>
        <div id="clearMsg" class="mt-3 d-none"></div>
    </form>
</div>

</div>
</div>

{{-- ─── Import History ─── --}}
<div class="admin-card mt-4">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-clock-history"></i> Import History</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Country</th>
                    <th>File</th>
                    <th>Status</th>
                    <th>Records</th>
                    <th>Result</th>
                    <th class="text-end">Rollback</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($imports as $import)
                    <tr>
                        <td class="fw-semibold">{{ $import->country?->name ?? '—' }}</td>
                        <td>{{ $import->filename }}</td>
                        <td>
                            @if ($import->status === 'completed')
                                <span class="badge text-bg-success">Completed</span>
                            @elseif ($import->status === 'failed')
                                <span class="badge text-bg-danger">Failed</span>
                            @elseif ($import->status === 'importing')
                                <span class="badge text-bg-primary">Importing</span>
                            @elseif ($import->status === 'rolled_back')
                                <span class="badge text-bg-secondary">Rolled back</span>
                            @else
                                <span class="badge text-bg-secondary">{{ $import->status }}</span>
                            @endif
                        </td>
                        <td>{{ number_format($import->total_records) }}</td>
                        <td>{{ number_format($import->inserted_records) }} ins / {{ number_format($import->updated_records) }} upd</td>
                        <td class="text-end">
                            @if (in_array($import->status, ['completed', 'failed'], true))
                                <button type="button" class="btn btn-sm btn-outline-danger rollback-btn"
                                        data-id="{{ $import->id }}" data-file="{{ $import->filename }}">
                                    <i class="bi bi-arrow-counterclockwise"></i> Rollback
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No import history.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Loading Overlay --}}
<div id="geoLoadingOverlay" class="d-none" style="position:fixed;inset:0;background:rgba(255,255,255,.78);backdrop-filter:blur(2px);z-index:1055;display:flex;align-items:center;justify-content:center;flex-direction:column;">
    <div class="spinner-border text-primary" role="status" style="width:3rem;height:3rem;"></div>
    <div id="geoLoadingText" class="mt-3 fw-semibold text-dark">Processing…</div>
    <div class="small text-muted mt-1">Do not close or reload the page</div>
</div>
<style>
    #geoLoadingOverlay.d-flex{display:flex !important;}
    #geoLoadingOverlay.d-none{display:none !important;}
</style>

@endsection

@section('scripts')
<script>
(function () {
    var tokenEl = document.querySelector('meta[name="csrf-token"]');
    var token = tokenEl ? tokenEl.getAttribute('content') : '';

    // ─── Helper functions ───
    function showError(msg) {
        var el = document.getElementById('importError');
        el.textContent = msg || 'Operation failed.';
        el.classList.remove('d-none');
    }
    function hideError() {
        var el = document.getElementById('importError');
        el.classList.add('d-none');
        el.textContent = '';
    }
    function showLoading(text) {
        var o = document.getElementById('geoLoadingOverlay');
        var t = document.getElementById('geoLoadingText');
        if (t) t.textContent = text || 'Processing…';
        if (o) { o.classList.remove('d-none'); o.classList.add('d-flex'); }
    }
    function hideLoading() {
        var o = document.getElementById('geoLoadingOverlay');
        if (o) { o.classList.add('d-none'); o.classList.remove('d-flex'); }
    }
    function setMsg(id, kind, text) {
        var el = document.getElementById(id);
        if (!el) return;
        el.className = 'mt-2 alert alert-' + (kind === 'ok' ? 'success' : kind === 'warn' ? 'warning' : 'danger');
        el.textContent = text;
    }

    // ═══════════════════════════════════════════════════════════
    //  LOAD FROM SOURCE (primary flow)
    // ═══════════════════════════════════════════════════════════
    var sourceCountry = document.getElementById('source_country');
    var sourceList = document.getElementById('sourceFilesList');
    var sourceMsg = document.getElementById('sourceMsg');

    function loadSourceFiles() {
        if (!sourceList) return;
        var cid = sourceCountry ? sourceCountry.value : '';
        if (!cid) {
            sourceList.innerHTML = '<div class="text-muted small">Select a country first</div>';
            return;
        }
        // Find the selected country's name from the option
        var opt = sourceCountry.options[sourceCountry.selectedIndex];
        var countryLabel = opt ? opt.textContent.trim() : '';
        // Extract name before the parentheses, e.g. "Bangladesh (BD)" → "Bangladesh"
        var nameMatch = /^(.+?)\s*\(/.exec(countryLabel);
        var searchName = nameMatch ? nameMatch[1].trim() : countryLabel;

        fetch('{{ route("admin.geo.imports.source-files") }}?country=' + encodeURIComponent(searchName))
            .then(function(r) { return r.json(); })
            .then(function(res) {
                var files = (res.data && res.data.files) || [];
                if (!files.length) {
                    sourceList.innerHTML = '<div class="text-muted small">No files found for this country in database/geo/</div>';
                    return;
                }
                var html = '';
                files.forEach(function(f) {
                    var kb = (f.size / 1024).toFixed(1);
                    html += '<div class="list-group-item d-flex justify-content-between align-items-center">'
                        + '<span class="flex-grow-1"><i class="bi bi-file-earmark-code me-1"></i>' + f.name + ' <span class="badge text-bg-secondary">' + kb + ' KB</span></span>'
                        + '<button type="button" class="btn btn-sm btn-outline-success me-2 source-import-btn" data-filename="' + f.name + '" title="Clear & Import">'
                        + '<i class="bi bi-arrow-down-circle"></i> Import</button>'
                        + '<button type="button" class="btn btn-sm btn-outline-danger source-delete-btn" data-filename="' + f.name + '" title="Delete file">'
                        + '<i class="bi bi-trash"></i></button>'
                        + '</div>';
                });
                sourceList.innerHTML = html;

                // Import buttons
                sourceList.querySelectorAll('.source-import-btn').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var filename = this.getAttribute('data-filename');
                        if (!confirm('Clear existing geo data and import ' + filename + '?')) return;
                        setMsg('sourceMsg', 'warn', 'Clearing and importing ' + filename + '…');
                        showLoading('Importing ' + filename + '…');
                        fetch('{{ route("admin.geo.imports.load-from-source") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                            body: JSON.stringify({ country_id: cid, filename: filename, mode: 'upsert' })
                        })
                        .then(function(r) { return r.json(); })
                        .then(function(d) {
                            hideLoading();
                            if (d.success && d.data && d.data.report) {
                                var r = d.data.report;
                                setMsg('sourceMsg', 'ok', 'Done! ' + r.inserted + ' inserted, ' + r.updated + ' updated, ' + r.errors + ' errors.');
                            } else {
                                setMsg('sourceMsg', 'err', d.message || 'Import failed.');
                            }
                        })
                        .catch(function(e) { hideLoading(); setMsg('sourceMsg', 'err', e.message); });
                    });
                });

                // Delete buttons
                sourceList.querySelectorAll('.source-delete-btn').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var filename = this.getAttribute('data-filename');
                        if (!confirm('Delete ' + filename + '? This cannot be undone.')) return;
                        fetch('{{ route("admin.geo.imports.delete-source-file") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                            body: JSON.stringify({ filename: filename })
                        })
                        .then(function(r) { return r.json(); })
                        .then(function(d) {
                            if (d.success) {
                                setMsg('sourceMsg', 'ok', d.message);
                                loadSourceFiles();
                            } else {
                                setMsg('sourceMsg', 'err', d.message || 'Delete failed.');
                            }
                        })
                        .catch(function(e) { setMsg('sourceMsg', 'err', e.message); });
                    });
                });
            })
            .catch(function() {
                sourceList.innerHTML = '<div class="text-muted small">Could not load source files.</div>';
            });
    }

    if (sourceCountry) {
        sourceCountry.addEventListener('change', function() {
            setMsg('sourceMsg', 'ok', '');
            sourceMsg.classList.add('d-none');
            loadSourceFiles();
        });
    }

    // ═══════════════════════════════════════════════════════════
    //  MANUAL UPLOAD (secondary flow)
    // ═══════════════════════════════════════════════════════════
    var form = document.getElementById('geoImportForm');
    var fileInput = document.getElementById('file');
    var btnRun = document.getElementById('btnRun');
    var btnValidate = document.getElementById('btnValidate');
    var progressCard = document.getElementById('progressCard');
    var progressBar = document.getElementById('progressBar');
    var progressStats = document.getElementById('progressStats');
    var progressErrors = document.getElementById('progressErrors');
    var previewCard = document.getElementById('previewCard');
    var previewBody = document.getElementById('previewBody');
    var previewConfirmWrap = document.getElementById('previewConfirmWrap');
    var previewConfirm = document.getElementById('previewConfirm');
    var lastPreview = { file: null, requires_confirm: false };

    function parseJsonSafe(t) { try { return JSON.parse(t); } catch(e) { return null; } }
    function safeId(v) {
        if (v === null || v === undefined) return '';
        var s = String(v).trim();
        if (!s || /^\[object/.test(s) || /%5Bobject/.test(s) || !/^\d+$/.test(s)) return '';
        return s;
    }
    function post(url, fd) {
        return fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            body: fd === undefined ? new FormData(form) : fd
        }).then(function(resp) {
            return resp.text().then(function(text) {
                var data = parseJsonSafe(text);
                if (data && data.success) return data;
                var msg = data && data.message ? data.message : text.substring(0, 300);
                if (data && data.errors) { var f = Object.values(data.errors)[0]; if (Array.isArray(f)) msg = f[0]; }
                throw new Error(msg);
            });
        });
    }
    function postEmpty(url) { return post(url, new FormData()); }
    function renderProgress(p) {
        var total = parseInt(p.total_records, 10) || 0;
        var ins = parseInt(p.inserted_records, 10) || 0;
        var upd = parseInt(p.updated_records, 10) || 0;
        var err = parseInt(p.error_count, 10) || 0;
        var finished = ['completed', 'failed', 'validated'].indexOf(p.status) !== -1;
        progressStats.textContent = total + ' records · ' + ins + ' added · ' + upd + ' updated · ' + err + ' errors';
        if (finished) {
            progressBar.style.width = '100%';
            progressBar.classList.remove('progress-bar-animated');
            hideLoading();
            if (p.status !== 'failed' && err === 0) setTimeout(function(){ location.reload(); }, 1200);
        } else {
            progressBar.style.width = Math.min(95, Math.round((ins+upd)/Math.max(1,total)*100)) + '%';
            progressBar.classList.add('progress-bar-animated');
        }
        if (p.error_summary) { progressErrors.textContent = p.error_summary; progressErrors.classList.remove('d-none'); }
        else { progressErrors.classList.add('d-none'); progressErrors.textContent = ''; }
    }
    function renderPreview(pv, fileName) {
        if (!pv || !previewCard) return;
        lastPreview = { file: fileName || null, requires_confirm: !!pv.requires_confirm };
        var html = '<div class="alert alert-info py-2 mb-2">Live rows: <strong>' + esc(pv.db_existing) + '</strong></div>';
        previewBody.innerHTML = html;
        previewCard.classList.remove('d-none');
        if (pv.requires_confirm) {
            previewConfirmWrap.classList.remove('d-none');
            previewConfirm.checked = false;
            previewConfirmLabel.textContent = 'I understand — allow Run for "' + (fileName || 'this file') + '".';
            btnRun.disabled = true;
        } else {
            previewConfirmWrap.classList.add('d-none');
            btnRun.disabled = false;
        }
    }
    function esc(s) { return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function resetPreview() { lastPreview = { file: null, requires_confirm: false }; previewCard.classList.add('d-none'); previewConfirm.checked = false; btnRun.disabled = false; }
    function validateForm() {
        hideError();
        if (!document.getElementById('upload_country').value) { showError('Select a country.'); return false; }
        if (!fileInput.files || !fileInput.files.length) { showError('Choose a file.'); return false; }
        return true;
    }
    function uploadOnly() {
        if (!validateForm()) return Promise.reject(new Error('Fix errors.'));
        hideError();
        showLoading('Uploading…');
        progressCard.classList.remove('d-none');
        progressBar.style.width = '30%';
        return post('{{ route("admin.geo.imports.store") }}', new FormData(form)).then(function(d) {
            progressBar.style.width = '55%';
            return d;
        }).catch(function(e) { hideLoading(); progressCard.classList.add('d-none'); showError(e.message); throw e; });
    }
    function startPoll(importId) {
        importId = safeId(importId);
        if (!importId) { hideLoading(); showError('Invalid import ID.'); return; }
        progressCard.classList.remove('d-none');
        showLoading('Importing…');
        function tick() {
            fetch('{{ route("admin.geo.imports.status", ["import" => "ID"]) }}'.replace('ID', importId), {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token }
            }).then(function(r) { return r.text().then(function(t) { var d = parseJsonSafe(t); if (!d || !d.success) throw new Error('Status failed'); return d; }); })
            .then(function(data) {
                renderProgress(data.data.import);
                if (['completed', 'failed', 'validated'].indexOf(data.data.import.status) !== -1) { hideLoading(); return; }
                return postEmpty('{{ route("admin.geo.imports.run", ["import" => "ID"]) }}'.replace('ID', importId)).then(function(rd) {
                    renderProgress(rd.data.import);
                    if (['completed', 'failed', 'validated'].indexOf(rd.data.import.status) !== -1) { hideLoading(); return; }
                    setTimeout(tick, 600);
                });
            }).catch(function(e) { hideLoading(); showError(e.message); });
        }
        tick();
    }

    if (btnValidate) {
        btnValidate.addEventListener('click', function() {
            resetPreview();
            uploadOnly().then(function(data) {
                var vid = safeId(data.data.import.id);
                showLoading('Validating…');
                return postEmpty('{{ route("admin.geo.imports.validate", ["import" => "ID"]) }}'.replace('ID', vid));
            }).then(function(data) {
                renderProgress(data.data.import);
                hideLoading();
                renderPreview(data.data.preview, fileInput.files[0] ? fileInput.files[0].name : null);
            }).catch(function(e) { hideLoading(); if (e.message !== 'Fix errors.') showError(e.message); });
        });
    }

    if (btnRun) {
        btnRun.addEventListener('click', function() {
            if (lastPreview.requires_confirm && (!previewConfirm || !previewConfirm.checked)) {
                showError('Tick the confirmation checkbox first.'); return;
            }
            resetPreview();
            uploadOnly().then(function(data) { hideLoading(); startPoll(data.data.import.id); })
            .catch(function(e) { hideLoading(); });
        });
    }

    if (previewConfirm) previewConfirm.addEventListener('change', function() { btnRun.disabled = !this.checked; });

    // ═══════════════════════════════════════════════════════════
    //  DANGER ZONE: Clear
    // ═══════════════════════════════════════════════════════════
    var clearCountry = document.getElementById('clear_country_id');
    var clearConfirm = document.getElementById('clear_confirm');
    var btnClearPreview = document.getElementById('btnClearPreview');
    var btnClear = document.getElementById('btnClear');
    var clearMsg = document.getElementById('clearMsg');

    function setClearMsg(k, t) {
        if (!clearMsg) return;
        clearMsg.className = 'mt-3 alert alert-' + (k === 'ok' ? 'success' : k === 'warn' ? 'warning' : 'danger');
        clearMsg.textContent = t;
    }

    if (btnClearPreview) {
        btnClearPreview.addEventListener('click', function() {
            if (!clearCountry.value) { setClearMsg('err', 'Select a country.'); return; }
            btnClearPreview.disabled = true;
            fetch('{{ route("admin.geo.clear.preview") }}?country_id=' + clearCountry.value, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token }
            }).then(function(r) { return r.json(); }).then(function(d) {
                btnClearPreview.disabled = false;
                if (!d || !d.success) throw new Error(d.message || 'Preview failed.');
                var p = d.data.preview;
                setClearMsg('warn', p.country + ': ' + p.total + ' rows to delete. Type country name to confirm.');
                btnClearPreview.dataset.armed = '1';
                btnClear.disabled = false;
            }).catch(function(e) { btnClearPreview.disabled = false; setClearMsg('err', e.message); });
        });
    }

    if (btnClear) {
        btnClear.addEventListener('click', function() {
            if (!clearConfirm.value.trim() || !(btnClearPreview.dataset.armed === '1')) {
                setClearMsg('err', 'Preview first, then type country name.'); return;
            }
            if (!confirm('DELETE ALL geo data for this country?')) return;
            var fd = new FormData();
            fd.append('country_id', clearCountry.value);
            fd.append('confirm', clearConfirm.value.trim());
            btnClear.disabled = true;
            showLoading('Deleting…');
            fetch('{{ route("admin.geo.clear") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                body: fd
            }).then(function(r) { return r.text().then(function(t) { var d = parseJsonSafe(t); if (!d || !d.success) throw new Error(d.message || t.substring(0,300)); return d; }); })
            .then(function(d) { hideLoading(); setClearMsg('ok', d.message || 'Deleted.'); setTimeout(function(){ location.reload(); }, 1500); })
            .catch(function(e) { hideLoading(); btnClear.disabled = false; setClearMsg('err', e.message); });
        });
    }

    // ═══════════════════════════════════════════════════════════
    //  ROLLBACK
    // ═══════════════════════════════════════════════════════════
    document.querySelectorAll('.rollback-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var fname = btn.dataset.file || ('import #' + btn.dataset.id);
            if (!confirm('Roll back "' + fname + '"?')) return;
            btn.disabled = true;
            showLoading('Rolling back…');
            fetch('{{ route("admin.geo.imports.rollback", ["import" => "ID"]) }}'.replace('ID', btn.dataset.id), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }
            }).then(function(r) { return r.text().then(function(t) { var d = parseJsonSafe(t); if (!d || !d.success) throw new Error(d.message || t.substring(0,300)); return d; }); })
            .then(function(d) { var r = (d.data && d.data.rollback) || {}; alert('Rolled back: ' + (r.deleted||0) + ' deleted, ' + (r.restored||0) + ' restored.'); location.reload(); })
            .catch(function(e) { hideLoading(); btn.disabled = false; showError(e.message); });
        });
    });
})();
</script>
@endsection

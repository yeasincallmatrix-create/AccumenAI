@extends('layouts.admin')

@section('title', 'Import Geography Package — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('admin_geo.import_title') }}</h4>
        <p class="page-header-desc">{{ mawa_e('admin_geo.import_subtitle') }}</p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a class="btn btn-outline-primary" href="{{ route('admin.geo.imports.template') }}">
            <i class="bi bi-file-earmark-arrow-down"></i> {{ mawa_e('admin_geo.import_template') }}
        </a>
        <a class="btn btn-outline-warning" href="{{ route('admin.geo.duplicates') }}">
            <i class="bi bi-copy"></i> Duplicates
        </a>
        <a class="btn btn-outline-secondary" href="{{ route('admin.geo.index') }}">
            <i class="bi bi-arrow-left"></i> {{ mawa_e('admin_geo.title') }}
        </a>
    </div>
</div>

<div class="row g-3 align-items-start">
<div class="col-lg-4">
<div class="admin-card h-100">
    <form id="geoImportForm">
        @csrf
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="country_id">{{ mawa_e('admin_geo.import_select_country') }}</label>
                <select id="country_id" name="country_id" class="form-select" required>
                    <option value="">Select a country</option>
                    @foreach ($countries as $c)
                        <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->iso2 }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
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
                <div id="formatBadge" class="mt-2 d-none"></div>
                <div class="form-text text-muted">{{ mawa_e('admin_geo.import_format_hint') }}</div>
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

        <div id="previewCard" class="mt-3 d-none">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-shield-check"></i> Impact Preview (dry-run vs live data)</div>
            </div>
            <div id="previewBody" class="small mt-2"></div>
            <div id="previewConfirmWrap" class="form-check mt-2 d-none">
                <input class="form-check-input" type="checkbox" id="previewConfirm">
                <label class="form-check-label fw-semibold" for="previewConfirm" id="previewConfirmLabel">
                    I understand the impact above — allow Run.
                </label>
            </div>
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
</div>
<div class="col-lg-4">
<div class="admin-card h-100">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-arrow-repeat"></i> {{ mawa_e('admin_geo.import_convert_title') }}</div>
    </div>
    <p class="text-muted small">{{ mawa_e('admin_geo.import_convert_desc') }}</p>
    <form id="geoConvertForm">
        @csrf
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="convert_country_id">{{ mawa_e('admin_geo.import_select_country') }}</label>
                <select id="convert_country_id" name="country_id" class="form-select" required>
                    <option value="">Select a country</option>
                    @foreach ($countries as $c)
                        <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->iso2 }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="convert_file">Legacy File (.json)</label>
                <input type="file" id="convert_file" name="file" class="form-control" accept=".json" required>
            </div>
        </div>
        <div id="convertMsg" class="mt-3 d-none"></div>
        <div class="d-flex justify-content-end mt-3">
            <button type="button" id="btnConvert" class="btn btn-outline-primary">
                <i class="bi bi-arrow-repeat"></i> {{ mawa_e('admin_geo.import_convert_btn') }}
            </button>
        </div>
    </form>
</div>
</div>
<div class="col-lg-4">
<div class="admin-card h-100 border-danger">
    <div class="table-toolbar">
        <div class="toolbar-info text-danger"><i class="bi bi-exclamation-triangle"></i> Danger Zone — Clear Country Geography</div>
    </div>
    <p class="text-muted small">Deletes <strong>all</strong> administrative units of a country (upazilas → districts → divisions). Referencing patient/institute address fields are nulled by FK rules. This cannot be undone — type the exact country name to confirm.</p>
    <form id="geoClearForm">
        @csrf
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="clear_country_id">{{ mawa_e('admin_geo.import_select_country') }}</label>
                <select id="clear_country_id" name="country_id" class="form-select">
                    <option value="">Select a country</option>
                    @foreach ($countries as $c)
                        <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->iso2 }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="clear_confirm">Type the country name</label>
                <input type="text" id="clear_confirm" class="form-control" placeholder="e.g. Bangladesh" autocomplete="off">
            </div>
            <div class="col-12 d-flex align-items-end gap-2">
                <button type="button" id="btnClearPreview" class="btn btn-outline-secondary">Preview</button>
                <button type="button" id="btnClear" class="btn btn-danger" disabled>Delete All</button>
            </div>
        </div>
        <div id="clearMsg" class="mt-3 d-none"></div>
    </form>
</div>
</div>
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
                    <th class="text-end">Rollback</th>
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
                        <td class="text-end text-nowrap">
                            @if (in_array($import->status, ['completed', 'failed'], true))
                                <button type="button" class="btn btn-sm btn-outline-danger rollback-btn"
                                        data-id="{{ $import->id }}" data-file="{{ $import->filename }}"
                                        title="Undo this import (delete inserted rows, restore updated rows)">
                                    <i class="bi bi-arrow-counterclockwise"></i> Rollback
                                </button>
                            @elseif ($import->status === 'rolled_back')
                                <span class="badge text-bg-secondary">Rolled back</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ mawa_e('admin_geo.import_no_history') }}</td></tr>
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

    // Phase 1: client-side package format badge (reads first 8 KB only).
    var formatBadge = document.getElementById('formatBadge');
    function setBadge(kind, text) {
        if (!formatBadge) return;
        var cls = kind === 'ok' ? 'alert alert-success py-2 mb-0'
            : kind === 'warn' ? 'alert alert-warning py-2 mb-0'
            : 'alert alert-danger py-2 mb-0';
        formatBadge.className = 'mt-2 ' + cls;
        formatBadge.textContent = text;
    }
    if (fileInput) {
        fileInput.addEventListener('change', function () {
            if (!formatBadge) return;
            formatBadge.className = 'mt-2 d-none';
            formatBadge.textContent = '';
            var f = fileInput.files && fileInput.files[0];
            if (!f) return;
            var ext = (f.name.split('.').pop() || '').toLowerCase();
            var slice = f.slice(0, 8192);
            var reader = new FileReader();
            reader.onload = function (e) {
                var head = String(e.target.result || '').trim();
                if (!head) { setBadge('err', 'File looks empty.'); return; }
                var first = head.charAt(0);
                function tryParse(t) { try { return JSON.parse(t); } catch (err) { return undefined; } }
                if (first === '[') {
                    var arr = tryParse(head);
                    var looks = arr !== undefined || head.indexOf('{') !== -1;
                    setBadge(looks ? 'ok' : 'err', looks
                        ? 'JSON array detected — parseable (' + f.name + ').'
                        : 'JSON array detected but the head is not valid JSON.');
                    return;
                }
                if (first === '{') {
                    var whole = tryParse(head);
                    if (whole && typeof whole === 'object' && !Array.isArray(whole)) {
                        if (('level_1' in whole) && !('level' in whole)) {
                            setBadge('warn', 'Legacy flat shape detected (level_1/level_2/level_3) — convert it below before importing, or it will import 0 records.');
                        } else {
                            setBadge('ok', 'Single JSON object detected — parseable.');
                        }
                        return;
                    }
                    // fall through to line-based check (JSONL starting with {)
                }
                var lines = head.split(/\r?\n/).filter(function (l) { return l.trim() !== ''; });
                var okLines = lines.filter(function (l) { return tryParse(l) !== undefined; });
                if (okLines.length > 0 && okLines.length >= Math.min(2, lines.length)) {
                    var rec = tryParse(okLines[0]);
                    if (rec && (('level_1' in rec) && !('level' in rec))) {
                        setBadge('warn', 'Legacy flat shape detected (level_1/level_2/level_3) — convert it below before importing, or it will import 0 records.');
                    } else {
                        setBadge('ok', 'JSONL detected (' + okLines.length + '+ parseable lines sampled) — parseable.');
                    }
                    return;
                }
                if (ext === 'csv') {
                    setBadge(/code/i.test(lines[0] || '') ? 'ok' : 'warn',
                        /code/i.test(lines[0] || '') ? 'CSV detected with header — parseable.' : 'CSV detected — make sure the first row is a header containing code/name columns.');
                    return;
                }
                setBadge('err', 'Unrecognized format — use JSONL, JSON array, or CSV with headers.');
            };
            reader.readAsText(slice);
        });
    }

    // Phase 1: legacy → JSONL converter (download only, no DB writes).
    var convertForm = document.getElementById('geoConvertForm');
    var btnConvert = document.getElementById('btnConvert');
    var convertMsg = document.getElementById('convertMsg');
    function setConvertMsg(kind, text) {
        if (!convertMsg) return;
        convertMsg.className = 'mt-3 alert alert-' + (kind === 'ok' ? 'success' : 'danger');
        convertMsg.textContent = text;
    }
    if (btnConvert) {
        btnConvert.addEventListener('click', function () {
            var cid = document.getElementById('convert_country_id');
            var cfile = document.getElementById('convert_file');
            if (!cid.value) { setConvertMsg('err', 'Select a country first.'); return; }
            if (!cfile.files || !cfile.files[0]) { setConvertMsg('err', 'Choose a legacy .json file first.'); return; }
            var fd = new FormData();
            fd.append('country_id', cid.value);
            fd.append('file', cfile.files[0]);
            btnConvert.disabled = true;
            if (convertMsg) convertMsg.className = 'mt-3 d-none';
            fetch('{{ route('admin.geo.imports.convert') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                body: fd
            }).then(function (resp) {
                if (!resp.ok) {
                    return resp.text().then(function (t) {
                        var d = null;
                        try { d = JSON.parse(t); } catch (e) {}
                        var msg = (d && (d.message || (d.errors && Object.values(d.errors)[0][0]))) || t.substring(0, 300);
                        throw new Error(msg);
                    });
                }
                var total = resp.headers.get('X-Convert-Total') || '?';
                var skipped = resp.headers.get('X-Convert-Skipped') || '0';
                var disp = resp.headers.get('Content-Disposition') || '';
                var m = /filename="([^"]+)"/.exec(disp);
                var fname = m ? m[1] : 'converted.jsonl';
                return resp.blob().then(function (blob) {
                    var a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = fname;
                    document.body.appendChild(a);
                    a.click();
                    setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
                    setConvertMsg('ok', 'Converted: ' + total + ' records, ' + skipped + ' skipped. Now upload "' + fname + '" above to import.');
                });
            }).catch(function (err) {
                setConvertMsg('err', err.message || 'Conversion failed.');
            }).finally(function () {
                btnConvert.disabled = false;
            });
        });
    }

    // Phase 3: per-import rollback (snapshot trail).
    // Phase 3: per-import rollback (snapshot trail).
    document.querySelectorAll('.rollback-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var fname = btn.dataset.file || ('import #' + btn.dataset.id);
            if (!confirm('Roll back "' + fname + '"?\n\nRows it inserted will be deleted; rows it updated will be restored to their pre-import values.')) return;
            btn.disabled = true;
            showLoading('Rolling back… please wait');
            fetch('{{ route("admin.geo.imports.rollback", ["import" => "ID"]) }}'.replace('ID', encodeURIComponent(btn.dataset.id)), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }
            }).then(function (resp) {
                return resp.text().then(function (t) {
                    var d = null;
                    try { d = JSON.parse(t); } catch (e) {}
                    if (!d || !d.success) throw new Error((d && d.message) || t.substring(0, 300));
                    return d;
                });
            }).then(function (d) {
                var r = (d.data && d.data.rollback) || {};
                alert('Rolled back: ' + (r.deleted || 0) + ' deleted, ' + (r.restored || 0) + ' restored.');
                location.reload();
            }).catch(function (err) {
                hideLoading();
                btn.disabled = false;
                showError(err.message || 'Rollback failed.');
            });
        });
    });

    // Phase 3: danger-zone clear with preview + type-to-confirm.
    var clearCountry = document.getElementById('clear_country_id');
    var clearConfirm = document.getElementById('clear_confirm');
    var btnClearPreview = document.getElementById('btnClearPreview');
    var btnClear = document.getElementById('btnClear');
    var clearMsg = document.getElementById('clearMsg');
    function setClearMsg(kind, text) {
        if (!clearMsg) return;
        clearMsg.className = 'mt-3 alert alert-' + (kind === 'ok' ? 'success' : kind === 'warn' ? 'warning' : 'danger');
        clearMsg.textContent = text;
    }
    function clearArmed() {
        return clearConfirm && clearConfirm.value.trim() !== '' && btnClearPreview.dataset.armed === '1';
    }
    if (btnClearPreview) {
        btnClearPreview.addEventListener('click', function () {
            if (!clearCountry.value) { setClearMsg('err', 'Select a country first.'); return; }
            btnClearPreview.disabled = true;
            fetch('{{ route("admin.geo.clear.preview") }}?country_id=' + encodeURIComponent(clearCountry.value), {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token }
            }).then(function (resp) { return resp.json(); }).then(function (d) {
                btnClearPreview.disabled = false;
                if (!d || !d.success) throw new Error((d && d.message) || 'Preview failed.');
                var p = d.data.preview;
                var refTotal = Object.values(p.refs || {}).reduce(function (a, b) { return a + (parseInt(b, 10) || 0); }, 0);
                setClearMsg('warn', p.country + ': ' + p.total + ' geo rows would be deleted. '
                    + refTotal + ' patient/institute address reference(s) would be nulled. '
                    + 'Type the exact country name to arm the Delete button.');
                btnClearPreview.dataset.armed = '1';
                btnClear.disabled = false;
            }).catch(function (err) {
                btnClearPreview.disabled = false;
                setClearMsg('err', err.message || 'Preview failed.');
            });
        });
    }
    if (clearCountry) {
        clearCountry.addEventListener('change', function () {
            if (btnClearPreview) btnClearPreview.dataset.armed = '';
            if (btnClear) btnClear.disabled = true;
            if (clearMsg) clearMsg.className = 'mt-3 d-none';
        });
    }
    if (btnClear) {
        btnClear.addEventListener('click', function () {
            if (!clearArmed()) { setClearMsg('err', 'Preview first, then type the exact country name.'); return; }
            if (!confirm('DELETE ALL geography rows for this country? This cannot be undone.')) return;
            var fd = new FormData();
            fd.append('country_id', clearCountry.value);
            fd.append('confirm', clearConfirm.value.trim());
            btnClear.disabled = true;
            showLoading('Deleting… please wait');
            fetch('{{ route("admin.geo.clear") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                body: fd
            }).then(function (resp) {
                return resp.text().then(function (t) {
                    var d = null;
                    try { d = JSON.parse(t); } catch (e) {}
                    if (!d || !d.success) throw new Error((d && d.message) || t.substring(0, 300));
                    return d;
                });
            }).then(function (d) {
                hideLoading();
                setClearMsg('ok', d.message || 'Deleted.');
                setTimeout(function () { location.reload(); }, 1500);
            }).catch(function (err) {
                hideLoading();
                btnClear.disabled = false;
                setClearMsg('err', err.message || 'Delete failed.');
            });
        });
    }
    // Phase 2: impact preview rendering + Run gating.
    var previewCard = document.getElementById('previewCard');
    var previewBody = document.getElementById('previewBody');
    var previewConfirmWrap = document.getElementById('previewConfirmWrap');
    var previewConfirm = document.getElementById('previewConfirm');
    var previewConfirmLabel = document.getElementById('previewConfirmLabel');
    var lastPreview = { file: null, requires_confirm: false };

    function esc(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    function resetPreview() {
        lastPreview = { file: null, requires_confirm: false };
        if (previewCard) previewCard.classList.add('d-none');
        if (previewConfirm) previewConfirm.checked = false;
        btnRun.disabled = false;
    }
    function renderPreview(pv, fileName) {
        if (!pv || !previewCard) return;
        lastPreview = { file: fileName || null, requires_confirm: !!pv.requires_confirm };
        var html = '<div class="alert alert-info py-2 mb-2">Live rows for this country: <strong>'
            + esc(pv.db_existing) + '</strong></div>';
        var warns = [];
        if (pv.zero_update_warning) warns.push(['warning',
            'Nothing in this file matches existing rows — a Run would INSERT everything as new. If that is not intended, fix the codes first.']);
        if (pv.mass_insert_warning) warns.push(['warning',
            'This import will INSERT new rows into a country that already has data. Confirm this is a new dataset, not a re-upload with different codes.']);
        function listBlock(title, items, fmt) {
            if (!items || !items.length) return '';
            var lis = items.slice(0, 8).map(function (it) { return '<li>' + fmt(it) + '</li>'; }).join('');
            return '<div class="alert alert-danger py-2 mb-2"><strong>' + esc(title) + '</strong><ul class="mb-0">' + lis + '</ul></div>';
        }
        var blocks = '';
        blocks += listBlock('Same code, different name (' + (pv.code_name_conflict_count || 0) + ') — update would rename live rows:',
            pv.code_name_conflicts, function (it) {
                return '<code>' + esc(it.code) + '</code>: file “' + esc(it.file_name) + '” vs live “' + esc(it.db_name) + '”';
            });
        blocks += listBlock('Same name, different code (' + (pv.name_code_conflict_count || 0) + ') — insert would duplicate names:',
            pv.name_code_conflicts, function (it) {
                return 'L' + esc(it.level) + ' “' + esc(it.name) + '” file [' + (it.file_codes || []).map(esc).join(', ')
                    + '] vs live [' + (it.db_codes || []).map(esc).join(', ') + ']';
            });
        blocks += listBlock('Hierarchy conflicts (' + (pv.hierarchy_conflict_count || 0) + '):',
            pv.hierarchy_conflicts, function (it) {
                if (it.type === 'district_multi_division_in_file') return 'District “' + esc(it.district) + '” under 2+ divisions in file: ' + (it.divisions || []).map(esc).join(', ');
                if (it.type === 'district_division_mismatch') return 'District “' + esc(it.district) + '”: file says ' + esc(it.file_division) + ', live says ' + esc(it.db_division);
                return 'Upazila <code>' + esc(it.upazila_code) + '</code>: file district ' + esc(it.file_district) + ' vs live ' + esc(it.db_district);
            });
        previewBody.innerHTML = html + warns.map(function (w) {
            return '<div class="alert alert-' + w[0] + ' py-2 mb-2">' + esc(w[1]) + '</div>';
        }).join('') + blocks;
        previewCard.classList.remove('d-none');
        if (pv.requires_confirm) {
            previewConfirmWrap.classList.remove('d-none');
            previewConfirm.checked = false;
            previewConfirmLabel.textContent = 'I understand the impact above — allow Run for "' + (fileName || 'this file') + '".';
            btnRun.disabled = true;
        } else {
            previewConfirmWrap.classList.add('d-none');
            btnRun.disabled = false;
        }
    }
    if (previewConfirm) {
        previewConfirm.addEventListener('change', function () {
            btnRun.disabled = !previewConfirm.checked;
        });
    }

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
        resetPreview();
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
            var fname = (fileInput.files && fileInput.files[0]) ? fileInput.files[0].name : null;
            renderPreview(data.data.preview, fname);
        }).catch(function (err) {
            hideLoading();
            if (err.message !== 'Fix form errors.') showError(err.message);
        });
    });

    btnRun.addEventListener('click', function () {
        var fname = (fileInput.files && fileInput.files[0]) ? fileInput.files[0].name : null;
        if (lastPreview.requires_confirm && lastPreview.file && lastPreview.file === fname) {
            if (!previewConfirm || !previewConfirm.checked) {
                showError('This file needs confirmation: tick the impact-preview checkbox first.');
                return;
            }
        }
        resetPreview();
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
    fileInput.addEventListener('change', function(){ hideError(); resetPreview(); if(fileInput.files.length) progressLabel.textContent = fileInput.files[0].name + ' ready'; });
})();
</script>
@endsection
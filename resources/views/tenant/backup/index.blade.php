@extends('layouts.standalone')
@section('content')
<div class="container py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Settings</a></li>
            <li class="breadcrumb-item active">Backup</li>
        </ol>
    </nav>

    <h4>Backups</h4>
    <p class="text-muted">
        🔐 All backups are encrypted with AES-256. Only the platform can decrypt (server-side keys).
    </p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    {{-- Drive Connection — resolved server-side (F8): no JS fetch for visibility --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6><i class="bi bi-google"></i> Google Drive</h6>
                    @if($driveConn)
                        <small class="text-muted">
                            Connected: <strong>{{ $driveConn->google_user_email }}</strong>
                            @if($driveConn->last_sync_at)
                                · Last sync: {{ $driveConn->last_sync_at->format('Y-m-d H:i') }}
                            @endif
                        </small>
                    @else
                        <small class="text-muted">Not connected — connect to enable backups</small>
                    @endif
                </div>
                <div>
                    @if($driveConn)
                        <form method="POST" action="{{ route('tenant.backup.drive.disconnect') }}">
                            @csrf
                            <button class="btn btn-outline-danger btn-sm">Disconnect</button>
                        </form>
                    @else
                        <a href="{{ route('tenant.backup.drive.connect') }}"
                           class="btn btn-outline-primary btn-sm">
                            Connect Google Drive
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <p class="text-muted">
        <i class="bi bi-info-circle"></i>
        Backups upload to your Google Drive. Connect Drive below to enable.
    </p>

    @if(!$driveConn)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            Google Drive is not connected. Connect it above to enable backups.
        </div>
    @endif

    <form method="POST" action="{{ route('tenant.backup.store') }}" class="mb-3" id="backupForm">
        @csrf
        <button class="btn btn-primary" id="backupBtn" @disabled(!$driveConn)>
            <i class="bi bi-cloud-arrow-up"></i> Backup Now
        </button>
    </form>

    <div class="card">
        <div class="card-body">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Filename</th>
                        <th>Size</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($backups as $backup)
                    <tr>
                        <td><code>{{ $backup->filename }}</code></td>
                        <td>{{ number_format($backup->size_bytes / 1048576, 2) }} MB</td>
                        <td>
                            <span class="badge bg-{{ $backup->status === 'completed' ? 'success' : ($backup->status === 'failed' ? 'danger' : 'warning') }}">
                                {{ $backup->status }}
                            </span>
                        </td>
                        <td>{{ $backup->created_at->format('Y-m-d H:i') }}</td>
                        <td>
                            @if($backup->status === 'completed')
                                <a href="{{ route('tenant.backup.download', $backup->id) }}"
                                   class="btn btn-sm btn-outline-secondary">Download</a>
                                <button class="btn btn-sm btn-outline-primary"
                                        onclick="startRestore({{ $backup->id }})">
                                    Restore
                                </button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No backups yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Restore Modal --}}
<div class="modal fade" id="restoreModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="restoreTitle">Restore Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {{-- Step 0: mode selector --}}
                <div id="modeStep">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="radio" name="restore_mode"
                               value="merge" id="modeMerge" checked>
                        <label class="form-check-label" for="modeMerge">
                            <strong>Recovery Mode (merge)</strong>
                            <div class="small text-muted">
                                Missing rows are re-inserted. Nothing is ever deleted.
                            </div>
                        </label>
                    </div>
                    @if(auth()->user() && method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('institute-owner'))
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="radio" name="restore_mode"
                               value="smart" id="modeSmart">
                        <label class="form-check-label" for="modeSmart">
                            <strong>Smart Restore</strong> <span class="badge bg-warning text-dark">owner only</span>
                            <div class="small text-muted">
                                Deleted rows respect the backup snapshot.
                                <span class="text-success">Rows created after the backup are never touched.</span>
                            </div>
                        </label>
                    </div>
                    @endif
                    <button type="button" class="btn btn-primary" id="btnPreview">
                        <i class="bi bi-eye"></i> Preview Changes
                    </button>
                </div>

                {{-- Step 1: preview + confirm phrase --}}
                <div id="previewStep" class="d-none">
                    <div class="alert alert-info" id="previewSummary"></div>
                    <div id="previewTable" class="table-responsive mb-3" style="max-height: 260px; overflow:auto;"></div>
                    <p class="text-muted small mb-1">Type <strong>RESTORE</strong> to continue:</p>
                    <input type="text" class="form-control mb-3" id="confirmPhrase"
                           placeholder="RESTORE" autocomplete="off">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-danger" id="btnConfirm" disabled>
                            Continue
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="btnBackToMode">Back</button>
                    </div>
                </div>

                {{-- Step 2: OTP request --}}
                <div id="otpRequestStep" class="d-none">
                    <p>We'll send a 6-digit code to <strong>{{ auth()->user()->email }}</strong></p>
                    <p class="text-muted small">This confirms your identity before restore.</p>
                    <button class="btn btn-primary" onclick="requestOtp()">Send Code</button>
                </div>

                {{-- Step 3: OTP verify --}}
                <div id="otpVerifyStep" class="d-none">
                    <label class="form-label">Enter the 6-digit code:</label>
                    <input type="text" id="otpInput" maxlength="6" class="form-control form-control-lg text-center" style="letter-spacing: 8px; font-size: 24px;">
                    <div id="otpError" class="text-danger small mt-2"></div>
                    <button class="btn btn-primary mt-3" onclick="verifyOtp()">Confirm & Restore</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Phase 2C: Progress Modal (shared by backup + restore) --}}
<div class="modal fade" id="progressModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="progressTitle">Backup in progress</h5>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between mb-1">
                    <span id="progressStage" class="text-muted">Starting...</span>
                    <span id="progressPercent">0%</span>
                </div>
                <div class="progress" style="height: 22px;">
                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated"
                         role="progressbar" style="width: 0%">0%</div>
                </div>
                <div id="progressChunks" class="text-muted small mt-2"></div>
                <div id="progressError" class="text-danger small mt-2"></div>
                <div id="progressRollback" class="small mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary d-none" id="progressCloseBtn"
                        data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentBackupId = null;
let currentPreviewId = null;
let currentMode = 'merge';
let lastRollbackToken = null;
let progressTimer = null;
// Blade-generated base: the app can be served from a sub-path
// (APP_URL=http://localhost/AccumenAI/public), so a hardcoded absolute
// fetch(`/tenant/...`) escapes it and 404s at the web-server root.
const BACKUP_BASE = "{{ url('tenant/backup') }}";
// Safety wrapper: never instantiate bootstrap.Modal at parse time.
// If the layout ever loads Bootstrap after this script, the old top-level
// `new bootstrap.Modal(...)` threw ReferenceError and aborted the ENTIRE
// script block (no submit listener → normal POST → flash, no modal).
let restoreModal = null;
let progressModal = null;

function ensureModals() {
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        console.error('Bootstrap JS not loaded — modal unavailable');
        return false;
    }
    if (!restoreModal) {
        restoreModal = new bootstrap.Modal(document.getElementById('restoreModal'));
    }
    if (!progressModal) {
        progressModal = new bootstrap.Modal(document.getElementById('progressModal'));
    }
    return true;
}
const RESTORE_STEPS = ['modeStep', 'previewStep', 'otpRequestStep', 'otpVerifyStep'];
const STEP_TITLES = {
    modeStep: 'Restore Backup',
    previewStep: 'Preview changes',
    otpRequestStep: 'Verify identity',
    otpVerifyStep: 'Verify identity',
};

function showStep(step) {
    RESTORE_STEPS.forEach((s) => {
        document.getElementById(s).classList.toggle('d-none', s !== step);
    });
    document.getElementById('restoreTitle').textContent = STEP_TITLES[step] || 'Restore Backup';
}

function startRestore(backupId) {
    currentBackupId = backupId;
    currentPreviewId = null;
    currentMode = 'merge';
    lastRollbackToken = null;
    document.getElementById('otpError').textContent = '';
    document.getElementById('confirmPhrase').value = '';
    document.getElementById('btnConfirm').disabled = true;
    const btnPreview = document.getElementById('btnPreview');
    btnPreview.disabled = false;
    btnPreview.innerHTML = '<i class="bi bi-eye"></i> Preview Changes';
    const smart = document.getElementById('modeSmart');
    if (smart) smart.checked = false;
    document.getElementById('modeMerge').checked = true;
    showStep('modeStep');
    if (ensureModals()) {
        restoreModal.show();
    }
}

async function requestOtp() {
    const res = await fetch(`${BACKUP_BASE}/${currentBackupId}/restore/request`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    });
    const data = await res.json();
    if (data.success) {
        document.getElementById('otpRequestStep').classList.add('d-none');
        document.getElementById('otpVerifyStep').classList.remove('d-none');
        document.getElementById('otpInput').focus();
    } else {
        alert(data.message);
    }
}

async function verifyOtp() {
    const otp = document.getElementById('otpInput').value;
    const res = await fetch(`${BACKUP_BASE}/${currentBackupId}/restore/verify`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ otp, preview_id: currentPreviewId }),
    });
    const data = await res.json();
    if (data.success) {
        if (restoreModal) {
            restoreModal.hide();
        }
        if (data.log_id) {
            startProgressTracking(null, data.log_id);
        } else {
            location.reload();
        }
    } else {
        document.getElementById('otpError').textContent = data.message;
    }
}

// ── Phase 3: Smart restore — preview → confirm phrase → OTP ─────────────
document.getElementById('btnPreview').addEventListener('click', async () => {
    const smartEl = document.getElementById('modeSmart');
    currentMode = (smartEl && smartEl.checked) ? 'smart' : 'merge';

    const btn = document.getElementById('btnPreview');
    btn.disabled = true;
    btn.textContent = 'Computing preview...';

    try {
        const res = await fetch(`${BACKUP_BASE}/${currentBackupId}/restore/preview`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ mode: currentMode }),
        });
        const data = await res.json();

        // Legacy backup without manifest → no diff possible, straight to OTP
        if (res.status === 409 && data.error === 'no_manifest') {
            currentPreviewId = null;
            currentMode = 'merge';
            showStep('otpRequestStep');
            return;
        }

        if (!res.ok) {
            alert(data.error || data.message || 'Preview failed');
            return;
        }

        currentPreviewId = data.preview_id;
        currentMode = data.mode;
        renderPreview(data);
        showStep('previewStep');
    } catch (e) {
        alert('Preview error: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-eye"></i> Preview Changes';
    }
});

function renderPreview(data) {
    const keptLine = data.total_kept > 0
        ? `<span class="text-primary">&#128274; ${data.total_kept} rows created AFTER the backup are kept (never touched)</span><br>`
        : '';
    const delLine = data.total_soft_delete > 0
        ? `<span class="text-warning">&#9888; ${data.total_soft_delete} rows will be SOFT DELETED (30-day reversible)</span><br>`
        : '';

    document.getElementById('previewSummary').innerHTML = `
        <strong>Impact — ${data.mode === 'smart' ? 'Smart Restore' : 'Recovery (merge)'} mode:</strong><br>
        <span class="text-success">&#10133; INSERT: ${data.total_insert} rows (recovered)</span><br>
        <span class="text-info">&#8635; UPDATE: ${data.total_update} rows</span><br>
        ${delLine}${keptLine}
        <small class="text-muted">Preview expires in 30 minutes.</small>`;

    let html = '<table class="table table-sm"><thead><tr><th>Table</th><th>+Insert</th><th>~Update</th><th>-Soft del</th><th>&#128274;Kept</th></tr></thead><tbody>';
    let rows = 0;
    for (const [table, info] of Object.entries(data.diff || {})) {
        if (table === '_failed') continue;
        const ins = info.insert || 0, upd = info.update || 0,
              del = info.delete || 0, kept = info.kept || 0;
        if ((ins + upd + del + kept) === 0) continue;
        rows++;
        html += `<tr>
            <td>${table}</td>
            <td class="text-success">${ins ? '+' + ins : '-'}</td>
            <td class="text-info">${upd ? '~' + upd : '-'}</td>
            <td class="text-warning">${del ? '-' + del : '-'}</td>
            <td class="text-primary">${kept ? kept : '-'}</td>
        </tr>`;
    }
    if (rows === 0) html += '<tr><td colspan="5" class="text-muted text-center">No differences</td></tr>';
    html += '</tbody></table>';
    document.getElementById('previewTable').innerHTML = html;
}

document.getElementById('btnBackToMode').addEventListener('click', () => showStep('modeStep'));

document.getElementById('confirmPhrase').addEventListener('input', (e) => {
    document.getElementById('btnConfirm').disabled = e.target.value.trim() !== 'RESTORE';
});

document.getElementById('btnConfirm').addEventListener('click', async () => {
    if (!currentPreviewId) { showStep('otpRequestStep'); return; }

    const btn = document.getElementById('btnConfirm');
    btn.disabled = true;
    btn.textContent = 'Confirming...';

    try {
        const res = await fetch(`${BACKUP_BASE}/restore/${currentPreviewId}/confirm`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ confirm_phrase: 'RESTORE' }),
        });
        const data = await res.json();
        if (!res.ok) {
            alert(data.error || 'Confirmation failed');
            btn.disabled = false;
            btn.textContent = 'Continue';
            return;
        }
        showStep('otpRequestStep');
    } catch (e) {
        alert('Error: ' + e.message);
        btn.disabled = false;
        btn.textContent = 'Continue';
    }
});

// ── Phase 2C: Backup Now → AJAX dispatch + progress polling ─────────────
document.getElementById('backupForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('backupBtn');
    btn.disabled = true;

    const res = await fetch(e.target.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: new FormData(e.target),
    });
    const data = await res.json();

    if (data.success && data.backup_id) {
        startProgressTracking(data.backup_id, null);
    } else {
        alert(data.message || 'Backup failed to queue.');
        btn.disabled = false;
    }
});

function setProgressUi(p) {
    const pct = Math.max(0, Math.min(100, p.progress_percent || 0));
    const bar = document.getElementById('progressBar');
    bar.style.width = pct + '%';
    bar.textContent = pct + '%';
    document.getElementById('progressPercent').textContent = pct + '%';
    document.getElementById('progressStage').textContent =
        (p.progress_stage || '') + (p.progress_message ? ': ' + p.progress_message : '');

    const chunks = p.total_chunks
        ? `${p.uploaded_chunks || p.downloaded_chunks || 0} / ${p.total_chunks} chunks`
        : '';
    document.getElementById('progressChunks').textContent = chunks;
}

function finishProgress(failed, errMsg) {
    clearInterval(progressTimer);
    progressTimer = null;
    const bar = document.getElementById('progressBar');
    bar.classList.remove('progress-bar-animated', 'progress-bar-striped');
    bar.classList.toggle('bg-danger', !!failed);
    bar.classList.toggle('bg-success', !failed);
    document.getElementById('progressCloseBtn').classList.remove('d-none');
    document.getElementById('progressError').textContent = errMsg || '';
    document.getElementById('backupBtn').disabled = false;

    // Phase 3: offer an undo when the restore produced a rollback snapshot
    if (!failed && lastRollbackToken) {
        document.getElementById('progressRollback').innerHTML =
            `<a class="btn btn-sm btn-outline-danger" href="${BACKUP_BASE}/restore/rollback/${lastRollbackToken}">` +
            `&#8630; Undo this restore</a>`;
    }

    setTimeout(() => location.reload(), failed ? 0 : 1200);
}

function startProgressTracking(backupId, logId) {
    clearInterval(progressTimer);
    const bar = document.getElementById('progressBar');
    bar.classList.add('progress-bar-animated', 'progress-bar-striped');
    bar.classList.remove('bg-danger', 'bg-success');
    document.getElementById('progressCloseBtn').classList.add('d-none');
    document.getElementById('progressError').textContent = '';
    document.getElementById('progressRollback').innerHTML = '';
    lastRollbackToken = null;
    document.getElementById('progressTitle').textContent =
        backupId ? 'Backup in progress' : 'Restore in progress';
    if (!ensureModals()) {
        return;
    }
    progressModal.show();

    const url = backupId
        ? `${BACKUP_BASE}/${backupId}/progress`
        : `${BACKUP_BASE}/restore/${logId}/progress`;

    const tick = async () => {
        try {
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) { finishProgress(true, 'Progress unavailable'); return; }
            const p = await res.json();
            setProgressUi(p);
            if (p.rollback_token) lastRollbackToken = p.rollback_token;

            if (p.status === 'completed') {
                finishProgress(false, null);
            } else if (p.status === 'failed') {
                finishProgress(true, p.error_message || 'Failed');
            }
        } catch (err) {
            finishProgress(true, 'Connection lost');
        }
    };

    tick();
    progressTimer = setInterval(tick, 2000);
}
</script>
@endsection

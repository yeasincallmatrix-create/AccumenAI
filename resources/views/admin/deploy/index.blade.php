@extends('layouts.admin')
@section('title', 'Deploy — AccumenAI')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-rocket-takeoff-fill me-2"></i> Deployment Center</h4>
        <p class="text-muted mb-0 small">Dual deployment via Git or ZIP upload. All deployments are backed up and audited. Maximum 5 backups retained.</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('admin.artisan-commands.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-terminal me-1"></i> Artisan Commands</a>
        <span class="badge bg-success"><i class="bi bi-shield-check me-1"></i> Max 5 backups</span>
    </div>
</div>

@if(session('status'))
    <div class="alert alert-success"><i class="bi bi-check-circle-fill me-1"></i> {{ session('status') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> {{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0 small">
            @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
        </ul>
    </div>
@endif

{{-- Deployment Cards with Tabs --}}
<div class="admin-card mb-4">
    <ul class="nav nav-tabs" id="deployTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="git-tab" data-bs-toggle="tab" data-bs-target="#git-pane" type="button" role="tab" aria-controls="git-pane" aria-selected="true">
                <i class="bi bi-git me-1"></i> Git Deploy
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="zip-tab" data-bs-toggle="tab" data-bs-target="#zip-pane" type="button" role="tab" aria-controls="zip-pane" aria-selected="false">
                <i class="bi bi-file-earmark-zip me-1"></i> ZIP Upload
            </button>
        </li>
    </ul>
    <div class="tab-content border border-top-0 rounded-bottom p-0">
        {{-- Git Tab --}}
        <div class="tab-pane fade show active p-3" id="git-pane" role="tabpanel" aria-labelledby="git-tab">
            @if(! $isGitAvailable)
                <div class="alert alert-warning d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <div>
                        <strong>Git not available</strong><br>
                        <span class="small">Git is not installed or not in PATH on this server. Git deployment is disabled. Git check: <code>shell_exec('git --version')</code> failed.</span>
                    </div>
                </div>
            @else
                <div class="alert alert-info py-2 small d-flex justify-content-between flex-wrap gap-2 align-items-center">
                    <span><i class="bi bi-info-circle me-1"></i> Current branch: <code>{{ $currentBranch ?? 'unknown' }}</code> · Commit: <code title="{{ $currentHash }}">{{ \Illuminate\Support\Str::limit($currentHash ?? 'unknown', 12) }}</code></span>
                    @if($currentHash)
                        <span class="small text-muted font-monospace">{{ $currentHash }}</span>
                    @endif
                </div>
            @endif

            <form method="POST" action="{{ route('admin.deploy.git') }}" id="gitDeployForm">
                @csrf
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="gitBranch" class="form-label fw-semibold">Branch Name</label>
                        @if($isGitAvailable && count($branches) > 0)
                            <div class="input-group">
                                <select class="form-select" id="gitBranchSelect" {{ $isGitAvailable ? '' : 'disabled' }}>
                                    @foreach($branches as $b)
                                        <option value="{{ $b }}" {{ $b === ($currentBranch ?? 'main') ? 'selected' : '' }}>{{ $b }}</option>
                                    @endforeach
                                    <option value="__custom">Custom...</option>
                                </select>
                                <input type="text" class="form-control" id="gitBranch" name="branch" value="{{ old('branch', $currentBranch ?? 'main') }}" placeholder="main" pattern="[a-zA-Z0-9_\-/.]+" maxlength="100" {{ $isGitAvailable ? '' : 'disabled' }}>
                            </div>
                        @else
                            <input type="text" class="form-control" id="gitBranch" name="branch" value="{{ old('branch', 'main') }}" placeholder="main" pattern="[a-zA-Z0-9_\-/.]+" maxlength="100" {{ $isGitAvailable ? '' : 'disabled' }}>
                        @endif
                        <div class="form-text">Default is <code>main</code>. Only alphanumeric, <code>_</code>, <code>-</code>, <code>/</code>, <code>.</code> allowed.</div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="1" id="gitConfirm" required>
                            <label class="form-check-label small" for="gitConfirm">
                                I confirm — reset to <code>origin/&lt;branch&gt;</code>, run <code>composer install</code> & <code>migrate</code>.
                            </label>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-primary mt-3" id="gitDeployBtn" {{ $isGitAvailable ? '' : 'disabled' }}>
                    <i class="bi bi-git me-1"></i> Deploy via Git
                </button>
                @unless($isGitAvailable)
                    <span class="small text-muted ms-2">Git tab is disabled — install Git to enable.</span>
                @endunless
            </form>
        </div>

        {{-- ZIP Tab --}}
        <div class="tab-pane fade p-3" id="zip-pane" role="tabpanel" aria-labelledby="zip-tab">
            <div class="alert alert-secondary py-2 small">
                <i class="bi bi-file-earmark-zip me-1"></i> Max <strong>50MB</strong>, only <code>.zip</code> files. Excluded on merge: <code>.env</code>, <code>storage/</code>, <code>vendor/</code>, <code>node_modules/</code>. DB backup is automatic.
            </div>
            <form method="POST" action="{{ route('admin.deploy.zip') }}" enctype="multipart/form-data" id="zipDeployForm">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="zipFile" class="form-label fw-semibold">ZIP File <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="zipFile" name="zip_file" accept=".zip" required>
                        <div class="form-text">Only <code>.zip</code> allowed, up to 50MB.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="zipVersion" class="form-label fw-semibold">Version (optional)</label>
                        <input type="text" class="form-control" id="zipVersion" name="version" value="{{ old('version') }}" placeholder="e.g. v2.1.0" maxlength="100">
                        <div class="form-text">Version string or commit hash for logging.</div>
                    </div>
                </div>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" value="1" id="zipConfirm" required>
                    <label class="form-check-label small" for="zipConfirm">
                        I confirm this ZIP is trusted. Files will be overwritten (except excluded). Backup auto-pruned to 5.
                    </label>
                </div>
                <button type="button" class="btn btn-success mt-3" id="zipDeployBtn">
                    <i class="bi bi-cloud-upload me-1"></i> Upload & Deploy
                </button>
            </form>
        </div>
    </div>
</div>

{{-- Git Confirmation Modal --}}
<div class="modal fade" id="confirmGitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-git text-primary me-2"></i> Confirm Git Deploy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Deploy branch <code id="confirmGitBranchName">main</code> via Git?</p>
                <p class="small text-muted">This will run: <code>git fetch</code>, <code>git reset --hard origin/&lt;branch&gt;</code>, <code>composer install</code>, <code>php artisan migrate</code>, <code>config:cache</code> etc.</p>
                <div class="alert alert-warning small mb-0">
                    <i class="bi bi-shield-exclamation me-1"></i> Code + DB backup will be created automatically. Action is audited.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmGitDeployBtn">Confirm & Deploy</button>
            </div>
        </div>
    </div>
</div>

{{-- ZIP Confirmation Modal --}}
<div class="modal fade" id="confirmZipModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-file-earmark-zip text-success me-2"></i> Confirm ZIP Deploy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Upload and deploy <code id="confirmZipFileName">—</code>?</p>
                <div class="alert alert-warning small mb-0">
                    <i class="bi bi-shield-exclamation me-1"></i> This will overwrite application files. Backup is automatic.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmZipDeployBtn">Confirm & Deploy</button>
            </div>
        </div>
    </div>
</div>

{{-- Deployment Logs --}}
<div class="admin-card">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i> Recent Deployments</h6>
        <div class="d-flex gap-2 align-items-center">
            <div class="btn-group btn-group-sm" role="group">
                <a href="{{ route('admin.deploy.index') }}" class="btn {{ empty($filterType) ? 'btn-primary' : 'btn-outline-primary' }}">All</a>
                <a href="{{ route('admin.deploy.index', ['type' => 'git']) }}" class="btn {{ $filterType === 'git' ? 'btn-dark' : 'btn-outline-dark' }}"><i class="bi bi-git me-1"></i>Git</a>
                <a href="{{ route('admin.deploy.index', ['type' => 'zip']) }}" class="btn {{ $filterType === 'zip' ? 'btn-info' : 'btn-outline-info' }}"><i class="bi bi-file-earmark-zip me-1"></i>ZIP</a>
            </div>
            <span class="badge bg-primary">{{ $logs->count() }} records</span>
        </div>
    </div>
    @if($logs->isEmpty())
        <div class="text-center py-4 text-muted">
            <i class="bi bi-inbox" style="font-size:2rem; opacity:0.3;"></i>
            <p class="small mt-2 mb-0">No deployments yet.</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Time</th>
                        <th>Type</th>
                        <th>Version</th>
                        <th>Status</th>
                        <th>Log</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                    <tr>
                        <td class="small">#{{ $log->id }}</td>
                        <td class="small text-muted text-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td><span class="badge {{ $log->type === 'git' ? 'bg-dark' : 'bg-info' }}">{{ strtoupper($log->type) }}</span></td>
                        <td class="small font-monospace" style="max-width:160px; overflow:hidden; text-overflow:ellipsis;" title="{{ $log->version }}">{{ \Illuminate\Support\Str::limit($log->version ?? '—', 30) }}</td>
                        <td>
                            @if($log->status === 'success')
                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Success</span>
                            @elseif($log->status === 'failed')
                                <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Failed</span>
                            @else
                                <span class="badge bg-warning text-dark"><i class="bi bi-arrow-counterclockwise me-1"></i>Rolled Back</span>
                            @endif
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-secondary view-log-btn" data-log="{{ htmlspecialchars($log->log ?? '', ENT_QUOTES) }}" data-id="{{ $log->id }}">
                                <i class="bi bi-eye me-1"></i> View
                            </button>
                            <span class="small text-muted d-none d-md-inline" style="max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $log->log }}">{{ \Illuminate\Support\Str::limit($log->log ?? '', 30) }}</span>
                        </td>
                        <td>
                            @if($log->status !== 'rolled_back')
                                <button type="button" class="btn btn-sm btn-outline-warning rollback-btn" data-id="{{ $log->id }}" data-type="{{ $log->type }}" data-version="{{ $log->version ?? '—' }}">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Rollback
                                </button>
                            @else
                                <span class="small text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- View Log Modal --}}
<div class="modal fade" id="viewLogModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-code-square me-2"></i> Deployment Log <code id="viewLogId">—</code></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <pre id="viewLogContent" class="mb-0 p-3" style="background:#1e1e2f; color:#d4d4d4; font-size:0.82rem; white-space:pre-wrap; word-break:break-word; max-height:60vh; overflow:auto;"></pre>
            </div>
        </div>
    </div>
</div>

{{-- Rollback Confirmation Modal --}}
<div class="modal fade" id="confirmRollbackModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="rollbackForm" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-counterclockwise text-warning me-2"></i> Confirm Rollback</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Rollback deployment <code id="rollbackLogId">—</code> (<span id="rollbackType">—</span> <code id="rollbackVersion">—</code>)?</p>
                    <div class="alert alert-danger small mb-0">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> This will restore code and database from the backup of that deployment.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-arrow-counterclockwise me-1"></i> Confirm Rollback</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function() {
    // Branch select sync
    const branchSelect = document.getElementById('gitBranchSelect');
    const branchInput = document.getElementById('gitBranch');
    if (branchSelect && branchInput) {
        branchSelect.addEventListener('change', function() {
            if (this.value === '__custom') {
                branchInput.value = '';
                branchInput.focus();
            } else {
                branchInput.value = this.value;
            }
        });
        branchInput.addEventListener('input', function() {
            // if custom typed not in list, set select to custom
            let found = false;
            for (let i=0;i<branchSelect.options.length;i++) {
                if (branchSelect.options[i].value === this.value) { branchSelect.value = this.value; found=true; break; }
            }
            if (!found) branchSelect.value = '__custom';
        });
    }

    // Git deploy confirmation
    const gitBtn = document.getElementById('gitDeployBtn');
    const gitForm = document.getElementById('gitDeployForm');
    const gitModalEl = document.getElementById('confirmGitModal');
    const gitModal = gitModalEl ? new bootstrap.Modal(gitModalEl) : null;
    const confirmGitBtn = document.getElementById('confirmGitDeployBtn');
    const confirmBranchName = document.getElementById('confirmGitBranchName');
    const gitConfirm = document.getElementById('gitConfirm');

    if (gitBtn && gitForm) {
        gitBtn.addEventListener('click', function() {
            if (gitConfirm && !gitConfirm.checked) { alert('Please confirm the deployment.'); gitConfirm.focus(); return; }
            if (!branchInput || !branchInput.value.trim()) { alert('Branch name is required.'); return; }
            if (confirmBranchName) confirmBranchName.textContent = branchInput.value.trim();
            if (gitModal) gitModal.show();
        });
    }
    if (confirmGitBtn && gitForm) {
        confirmGitBtn.addEventListener('click', function() { confirmGitBtn.disabled=true; gitForm.submit(); });
    }

    // ZIP deploy confirmation
    const zipBtn = document.getElementById('zipDeployBtn');
    const zipForm = document.getElementById('zipDeployForm');
    const zipModalEl = document.getElementById('confirmZipModal');
    const zipModal = zipModalEl ? new bootstrap.Modal(zipModalEl) : null;
    const confirmZipBtn = document.getElementById('confirmZipDeployBtn');
    const zipFileInput = document.getElementById('zipFile');
    const confirmZipName = document.getElementById('confirmZipFileName');
    const zipConfirm = document.getElementById('zipConfirm');

    if (zipBtn && zipForm) {
        zipBtn.addEventListener('click', function() {
            if (zipConfirm && !zipConfirm.checked) { alert('Please confirm ZIP deployment.'); zipConfirm.focus(); return; }
            if (!zipFileInput || !zipFileInput.files || zipFileInput.files.length === 0) { alert('Please select a ZIP file.'); return; }
            const f = zipFileInput.files[0];
            if (f.size > 52428800) { alert('File too large (max 50MB).'); return; }
            if (!f.name.toLowerCase().endsWith('.zip')) { alert('Only .zip files allowed.'); return; }
            if (confirmZipName) confirmZipName.textContent = f.name + ' (' + (f.size/1024).toFixed(1) + ' KB)';
            if (zipModal) zipModal.show();
        });
    }
    if (confirmZipBtn && zipForm) {
        confirmZipBtn.addEventListener('click', function() { confirmZipBtn.disabled=true; zipForm.submit(); });
    }

    // View log
    const viewModalEl = document.getElementById('viewLogModal');
    const viewModal = viewModalEl ? new bootstrap.Modal(viewModalEl) : null;
    document.querySelectorAll('.view-log-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            const log = btn.getAttribute('data-log') || '';
            const id = btn.getAttribute('data-id');
            const content = document.getElementById('viewLogContent');
            const idEl = document.getElementById('viewLogId');
            if (idEl) idEl.textContent = '#' + id;
            if (content) content.textContent = log || '(empty)';
            if (viewModal) viewModal.show();
        });
    });

    // Rollback
    const rollbackModalEl = document.getElementById('confirmRollbackModal');
    const rollbackModal = rollbackModalEl ? new bootstrap.Modal(rollbackModalEl) : null;
    const rollbackForm = document.getElementById('rollbackForm');
    const rollbackLogId = document.getElementById('rollbackLogId');
    const rollbackType = document.getElementById('rollbackType');
    const rollbackVersion = document.getElementById('rollbackVersion');
    const rollbackBase = "{{ url('admin/deploy/rollback') }}";

    document.querySelectorAll('.rollback-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = btn.getAttribute('data-id');
            const type = btn.getAttribute('data-type');
            const ver = btn.getAttribute('data-version');
            if (rollbackLogId) rollbackLogId.textContent = '#' + id;
            if (rollbackType) rollbackType.textContent = type;
            if (rollbackVersion) rollbackVersion.textContent = ver || '—';
            if (rollbackForm) rollbackForm.action = rollbackBase + '/' + id;
            if (rollbackModal) rollbackModal.show();
        });
    });
})();
</script>
@endsection

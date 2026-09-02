@extends('layouts.admin')
@section('title', 'Artisan Commands — AccumenAI')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-terminal-fill me-2"></i> Artisan Commands</h4>
        <p class="text-muted mb-0 small">Run predefined safe Artisan commands without terminal access. All executions are audited.</p>
    </div>
    <div class="d-flex gap-2">
        <span class="badge bg-success"><i class="bi bi-shield-check me-1"></i> Safe Mode: Whitelist Only</span>
        <span class="badge bg-secondary">Rate limit: 10 / hour</span>
    </div>
</div>

{{-- Deployment Section (Dual System: Git + ZIP) --}}
<div class="admin-card mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-rocket-takeoff-fill me-2"></i> Deployment</h5>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.deploy.index') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right me-1"></i> Full Deploy Page</a>
            <span class="badge bg-success align-self-center"><i class="bi bi-shield-check me-1"></i> Auto Backup</span>
        </div>
    </div>
    @if(session('status') && str_contains(session('status'), 'deploy') || str_contains(strtolower(session('status') ?? ''), 'rollback'))
        <div class="alert alert-success small">{{ session('status') }}</div>
    @endif
    <ul class="nav nav-tabs" id="deployTabsArtisan" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="git-tab-artisan" data-bs-toggle="tab" data-bs-target="#git-pane-artisan" type="button" role="tab" aria-controls="git-pane-artisan" aria-selected="true">
                <i class="bi bi-git me-1"></i> Git Deploy
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="zip-tab-artisan" data-bs-toggle="tab" data-bs-target="#zip-pane-artisan" type="button" role="tab" aria-controls="zip-pane-artisan" aria-selected="false">
                <i class="bi bi-file-earmark-zip me-1"></i> ZIP Upload
            </button>
        </li>
    </ul>
    <div class="tab-content border border-top-0 rounded-bottom">
        <div class="tab-pane fade show active" id="git-pane-artisan" role="tabpanel" aria-labelledby="git-tab-artisan">
            @include('admin.deploy.partials.git-form', ['gitAvailable' => $gitAvailable ?? false, 'currentCommit' => $currentCommit ?? null, 'currentBranch' => $currentBranch ?? null])
        </div>
        <div class="tab-pane fade" id="zip-pane-artisan" role="tabpanel" aria-labelledby="zip-tab-artisan">
            @include('admin.deploy.partials.zip-form')
        </div>
    </div>
    {{-- Recent deployment logs inline --}}
    @if(isset($deploymentLogs) && $deploymentLogs->count() > 0)
        <div class="mt-4">
            <h6 class="small fw-semibold"><i class="bi bi-clock-history me-1"></i> Recent Deployments</h6>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr><th>ID</th><th>Time</th><th>Type</th><th>Version</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        @foreach($deploymentLogs as $dlog)
                        <tr>
                            <td class="small">#{{ $dlog->id }}</td>
                            <td class="small text-muted">{{ $dlog->created_at?->format('Y-m-d H:i') }}</td>
                            <td><span class="badge {{ $dlog->type === 'git' ? 'bg-dark' : 'bg-info' }}">{{ strtoupper($dlog->type) }}</span></td>
                            <td class="small font-monospace">{{ \Illuminate\Support\Str::limit($dlog->version ?? '—', 18) }}</td>
                            <td>
                                @if($dlog->status === 'success') <span class="badge bg-success">Success</span>
                                @elseif($dlog->status === 'failed') <span class="badge bg-danger">Failed</span>
                                @else <span class="badge bg-warning text-dark">Rolled Back</span>
                                @endif
                            </td>
                            <td>
                                @if($dlog->status !== 'rolled_back')
                                    <button type="button" class="btn btn-sm btn-outline-warning py-0 px-2 rollback-btn-artisan" data-id="{{ $dlog->id }}" data-type="{{ $dlog->type }}" data-version="{{ $dlog->version }}">Rollback</button>
                                @else <span class="small text-muted">—</span> @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

{{-- Rollback modal for artisan page --}}
<div class="modal fade" id="confirmRollbackModalArtisan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="rollbackFormArtisan" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-counterclockwise text-warning me-2"></i> Confirm Rollback</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Rollback deployment <code id="rollbackLogIdArtisan">—</code> (<span id="rollbackTypeArtisan">—</span> <code id="rollbackVersionArtisan">—</code>)?</p>
                    <div class="alert alert-danger small mb-3"><i class="bi bi-exclamation-triangle-fill me-1"></i> Restores code and DB from backup.</div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="rollbackConfirmArtisan" name="confirm" required>
                        <label class="form-check-label" for="rollbackConfirmArtisan">I confirm rollback.</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Confirm Rollback</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Output display area (hidden initially) --}}
<div id="commandOutputCard" class="admin-card mb-4 d-none">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0"><i class="bi bi-code-square me-2"></i> Command Output</h6>
        <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('commandOutputCard').classList.add('d-none')"><i class="bi bi-x-lg"></i> Dismiss</button>
    </div>
    <div class="alert mb-0 p-0 border" style="background:#1e1e2f;">
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom" style="background:#2a2a40;">
            <span class="small text-white-50" id="outputCommandLabel">—</span>
            <span class="badge" id="outputStatusBadge">—</span>
        </div>
        <pre id="commandOutput" class="mb-0 p-3 text-white" style="white-space:pre-wrap; word-break:break-word; font-size:0.85rem; max-height:400px; overflow-y:auto; background:#1e1e2f; color:#d4d4d4; font-family: 'Consolas','Monaco','Courier New',monospace;"></pre>
    </div>
    <div class="small text-muted mt-2" id="outputMeta"></div>
</div>

<div class="row g-3">
    @foreach($commands as $cmd => $meta)
        @php
            $riskColor = match($meta['risk'] ?? 'low') {
                'low' => 'success',
                'medium' => 'warning',
                'high' => 'danger',
                default => 'secondary',
            };
            $riskIcon = match($meta['risk'] ?? 'low') {
                'low' => 'bi-shield-check',
                'medium' => 'bi-shield-exclamation',
                'high' => 'bi-shield-x',
                default => 'bi-shield',
            };
        @endphp
        <div class="col-md-6 col-xl-4">
            <div class="admin-card h-100 d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-terminal me-1 text-primary"></i> {{ $meta['label'] }}</h6>
                    <span class="badge bg-{{ $riskColor }}"><i class="bi {{ $riskIcon }} me-1"></i>{{ ucfirst($meta['risk']) }}</span>
                </div>
                <code class="d-block bg-light px-2 py-1 rounded small mb-2" style="font-size:0.82rem;">{{ $cmd }}</code>
                <p class="small text-muted flex-grow-1 mb-3">{{ $meta['description'] }}</p>
                <button
                    type="button"
                    class="btn btn-sm {{ ($meta['risk'] ?? 'low') === 'high' ? 'btn-danger' : 'btn-primary' }} w-100 artisan-run-btn"
                    data-command="{{ $cmd }}"
                    data-label="{{ $meta['label'] }}"
                    data-description="{{ $meta['description'] }}"
                    data-risk="{{ $meta['risk'] }}"
                >
                    <i class="bi bi-play-fill me-1"></i> Run
                </button>
            </div>
        </div>
    @endforeach
</div>

{{-- Recent execution history --}}
@if(isset($recentLogs) && $recentLogs->count() > 0)
<div class="admin-card mt-4">
    <h6 class="mb-3"><i class="bi bi-clock-history me-2"></i> Recent Executions (Last 20)</h6>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Admin</th>
                    <th>Command</th>
                    <th>Risk</th>
                    <th>Status</th>
                    <th>IP</th>
                    <th>Output</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentLogs as $log)
                <tr>
                    <td class="small text-muted text-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td class="small">{{ $log->admin?->email ?? '—' }}</td>
                    <td><code class="small">{{ $log->command }}</code></td>
                    <td><span class="badge bg-{{ $log->risk === 'high' ? 'danger' : ($log->risk === 'medium' ? 'warning text-dark' : 'success') }}">{{ ucfirst($log->risk) }}</span></td>
                    <td>
                        @if($log->success)
                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Success</span>
                        @else
                            <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Failed</span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $log->ip_address }}</td>
                    <td class="small" style="max-width:240px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="{{ $log->output_summary }}">{{ \Illuminate\Support\Str::limit($log->output_summary, 60) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Confirmation Modal --}}
<div class="modal fade" id="confirmArtisanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i> Confirm Execution</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Are you sure you want to run this command?</p>
                <div class="bg-light rounded p-3 mb-3">
                    <div class="fw-semibold" id="modalCommandLabel">—</div>
                    <code id="modalCommandName" class="small">—</code>
                    <p class="small text-muted mb-0 mt-2" id="modalCommandDesc">—</p>
                    <div class="mt-2">
                        <span class="badge" id="modalRiskBadge">—</span>
                        <span class="small text-muted ms-2">Risk level</span>
                    </div>
                </div>
                <div class="alert alert-warning py-2 small mb-0" id="modalWarningBox">
                    <i class="bi bi-info-circle me-1"></i> This action will be logged with your account, IP and timestamp.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmExecuteBtn">
                    <span class="btn-text"><i class="bi bi-play-fill me-1"></i> Confirm & Run</span>
                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const executeUrl = "{{ route('admin.artisan-commands.execute') }}";
    let pendingCommand = null;

    const modalEl = document.getElementById('confirmArtisanModal');
    const bsModal = modalEl ? new bootstrap.Modal(modalEl) : null;

    const modalLabel = document.getElementById('modalCommandLabel');
    const modalName = document.getElementById('modalCommandName');
    const modalDesc = document.getElementById('modalCommandDesc');
    const modalRisk = document.getElementById('modalRiskBadge');
    const modalWarning = document.getElementById('modalWarningBox');
    const confirmBtn = document.getElementById('confirmExecuteBtn');
    const btnText = confirmBtn ? confirmBtn.querySelector('.btn-text') : null;
    const spinner = confirmBtn ? confirmBtn.querySelector('.spinner-border') : null;

    const outputCard = document.getElementById('commandOutputCard');
    const outputPre = document.getElementById('commandOutput');
    const outputLabel = document.getElementById('outputCommandLabel');
    const outputBadge = document.getElementById('outputStatusBadge');
    const outputMeta = document.getElementById('outputMeta');

    function setRiskBadge(el, risk) {
        el.textContent = risk.charAt(0).toUpperCase() + risk.slice(1) + ' Risk';
        el.className = 'badge bg-' + (risk === 'high' ? 'danger' : (risk === 'medium' ? 'warning text-dark' : 'success'));
    }

    document.querySelectorAll('.artisan-run-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            pendingCommand = btn.getAttribute('data-command');
            const label = btn.getAttribute('data-label');
            const desc = btn.getAttribute('data-description');
            const risk = btn.getAttribute('data-risk') || 'low';

            if (modalLabel) modalLabel.textContent = label;
            if (modalName) modalName.textContent = pendingCommand;
            if (modalDesc) modalDesc.textContent = desc;
            if (modalRisk) setRiskBadge(modalRisk, risk);

            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.className = 'btn ' + (risk === 'high' ? 'btn-danger' : 'btn-primary');
                if (btnText) btnText.innerHTML = '<i class="bi bi-play-fill me-1"></i> Confirm & Run';
                if (spinner) spinner.classList.add('d-none');
            }

            if (bsModal) bsModal.show();
        });
    });

    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            if (!pendingCommand) return;

            confirmBtn.disabled = true;
            if (btnText) btnText.innerHTML = 'Running...';
            if (spinner) spinner.classList.remove('d-none');

            fetch(executeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ command: pendingCommand })
            })
            .then(async function(res) {
                const data = await res.json().catch(() => ({ success:false, message: 'Invalid server response' }));
                if (!res.ok && !data.output) {
                    throw new Error(data.message || 'Request failed (' + res.status + ')');
                }
                return data;
            })
            .then(function(data) {
                if (bsModal) bsModal.hide();

                // Show output
                if (outputCard) outputCard.classList.remove('d-none');
                if (outputLabel) outputLabel.textContent = (data.label || pendingCommand) + ' — ' + (data.command || pendingCommand);
                if (outputPre) outputPre.textContent = data.output || data.message || 'No output.';
                if (outputBadge) {
                    const ok = data.success !== false;
                    outputBadge.textContent = ok ? 'Success' : 'Failed';
                    outputBadge.className = 'badge ' + (ok ? 'bg-success' : 'bg-danger');
                }
                if (outputMeta) outputMeta.textContent = 'Executed at ' + new Date().toLocaleString() + ' • Exit code: ' + (data.exit_code ?? '—');

                // Scroll to output
                if (outputCard) outputCard.scrollIntoView({ behavior: 'smooth', block: 'start' });

                // Reload recent logs after 1s (optional)
                setTimeout(function(){ window.location.reload(); }, 2500);
            })
            .catch(function(err) {
                if (bsModal) bsModal.hide();
                if (outputCard) outputCard.classList.remove('d-none');
                if (outputPre) outputPre.textContent = 'Error: ' + err.message;
                if (outputBadge) {
                    outputBadge.textContent = 'Error';
                    outputBadge.className = 'badge bg-danger';
                }
                if (outputLabel) outputLabel.textContent = pendingCommand;
            })
            .finally(function() {
                confirmBtn.disabled = false;
                if (btnText) btnText.innerHTML = '<i class="bi bi-play-fill me-1"></i> Confirm & Run';
                if (spinner) spinner.classList.add('d-none');
            });
        });
    }
})();
// Deploy section handlers on artisan page (shared partials)
(function(){
    const gitBtn = document.getElementById('gitDeployBtn');
    const gitForm = document.getElementById('gitDeployForm');
    const gitModalEl = document.getElementById('confirmGitModal');
    const gitModal = gitModalEl ? new bootstrap.Modal(gitModalEl) : null;
    const confirmGitBtn = document.getElementById('confirmGitDeployBtn');
    const gitBranchInput = document.getElementById('gitBranch');
    const confirmBranchName = document.getElementById('confirmGitBranchName');
    const gitConfirm = document.getElementById('gitConfirm');
    if (gitBtn && gitForm) {
        gitBtn.addEventListener('click', function(){
            if (!gitConfirm || !gitConfirm.checked) { gitConfirm?.reportValidity(); return; }
            if (gitBranchInput && confirmBranchName) confirmBranchName.textContent = gitBranchInput.value || 'main';
            if (gitModal) gitModal.show();
        });
    }
    if (confirmGitBtn && gitForm) {
        confirmGitBtn.addEventListener('click', function(){ gitForm.submit(); });
    }
    const zipBtn = document.getElementById('zipDeployBtn');
    const zipForm = document.getElementById('zipDeployForm');
    const zipModalEl = document.getElementById('confirmZipModal');
    const zipModal = zipModalEl ? new bootstrap.Modal(zipModalEl) : null;
    const confirmZipBtn = document.getElementById('confirmZipDeployBtn');
    const zipFileInput = document.getElementById('zipFile');
    const confirmZipName = document.getElementById('confirmZipFileName');
    const zipConfirm = document.getElementById('zipConfirm');
    if (zipBtn && zipForm) {
        zipBtn.addEventListener('click', function(){
            if (!zipConfirm || !zipConfirm.checked) { zipConfirm?.reportValidity(); return; }
            if (!zipFileInput || !zipFileInput.files || zipFileInput.files.length===0) { alert('Please select a ZIP file.'); return; }
            const f = zipFileInput.files[0];
            if (f.size > 52428800) { alert('File too large (max 50MB).'); return; }
            if (confirmZipName) confirmZipName.textContent = f.name;
            if (zipModal) zipModal.show();
        });
    }
    if (confirmZipBtn && zipForm) {
        confirmZipBtn.addEventListener('click', function(){ zipForm.submit(); });
    }
    // Rollback on artisan page
    const rollbackModalEl = document.getElementById('confirmRollbackModalArtisan');
    const rollbackModal = rollbackModalEl ? new bootstrap.Modal(rollbackModalEl) : null;
    const rollbackForm = document.getElementById('rollbackFormArtisan');
    const rollbackLogId = document.getElementById('rollbackLogIdArtisan');
    const rollbackType = document.getElementById('rollbackTypeArtisan');
    const rollbackVersion = document.getElementById('rollbackVersionArtisan');
    const rollbackBase = "{{ url('admin/deploy/rollback') }}";
    document.querySelectorAll('.rollback-btn-artisan').forEach(function(btn){
        btn.addEventListener('click', function(){
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

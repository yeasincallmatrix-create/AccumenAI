{{-- Git Deploy Form Partial --}}
@php $gitAvailable = $gitAvailable ?? $isGitAvailable ?? false; @endphp
<div class="p-3">
    @if(! $gitAvailable)
        <div class="alert alert-warning d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div>
                <strong>Git not available</strong><br>
                <span class="small">Git is not installed or not in PATH on this server. Git deployment is disabled.</span>
            </div>
        </div>
    @else
        <div class="alert alert-info py-2 small">
            <i class="bi bi-info-circle me-1"></i> Current branch: <code>{{ $currentBranch ?? 'unknown' }}</code> · Commit: <code>{{ $currentCommit ?? 'unknown' }}</code>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.deploy.git') }}" id="gitDeployForm">
        @csrf
        <div class="mb-3">
            <label for="gitBranch" class="form-label fw-semibold">Branch Name</label>
            <input type="text" class="form-control" id="gitBranch" name="branch" value="{{ old('branch', 'main') }}" placeholder="main" pattern="[a-zA-Z0-9_\-/.]+" maxlength="100" {{ $gitAvailable ? '' : 'disabled' }}>
            <div class="form-text">Default is <code>main</code>. Only alphanumeric, <code>_</code>, <code>-</code>, <code>/</code>, <code>.</code> allowed.</div>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="gitConfirm" name="confirm" required>
            <label class="form-check-label" for="gitConfirm">
                I understand this will <strong>reset</strong> the codebase to <code>origin/&lt;branch&gt;</code>, run <code>composer install</code> and <code>php artisan migrate</code>, and may cause brief downtime. A backup will be taken automatically.
            </label>
        </div>
        <button type="button" class="btn btn-primary" id="gitDeployBtn" {{ $gitAvailable ? '' : 'disabled' }}>
            <i class="bi bi-git me-1"></i> Deploy via Git
        </button>
    </form>
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
                <div class="alert alert-warning small mb-0">
                    <i class="bi bi-shield-exclamation me-1"></i> A code + DB backup will be created. This action is audited.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmGitDeployBtn">Confirm & Deploy</button>
            </div>
        </div>
    </div>
</div>

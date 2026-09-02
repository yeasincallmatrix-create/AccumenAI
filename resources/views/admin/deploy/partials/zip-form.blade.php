{{-- ZIP Upload Form Partial --}}
<div class="p-3">
    <div class="alert alert-secondary py-2 small">
        <i class="bi bi-file-earmark-zip me-1"></i> Max <strong>50MB</strong>, only <code>.zip</code> files. Excluded on merge: <code>.env</code>, <code>storage/</code>, <code>vendor/</code>, <code>node_modules/</code>.
    </div>
    <form method="POST" action="{{ route('admin.deploy.zip') }}" enctype="multipart/form-data" id="zipDeployForm">
        @csrf
        <div class="mb-3">
            <label for="zipFile" class="form-label fw-semibold">ZIP File</label>
            <input type="file" class="form-control" id="zipFile" name="zip_file" accept=".zip" required>
            <div class="form-text">Select a ZIP archive containing the new codebase.</div>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="zipConfirm" name="confirm" required>
            <label class="form-check-label" for="zipConfirm">
                I confirm this ZIP is trusted. Existing files will be overwritten (except <code>.env</code> etc.). A backup will be taken and old backups auto-pruned to 5.
            </label>
        </div>
        <button type="button" class="btn btn-success" id="zipDeployBtn">
            <i class="bi bi-cloud-upload me-1"></i> Upload & Deploy
        </button>
    </form>
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

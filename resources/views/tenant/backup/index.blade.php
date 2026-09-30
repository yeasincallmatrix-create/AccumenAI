@extends('layouts.standalone')
@section('content')
<div class="container py-4">
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

    <form method="POST" action="{{ route('tenant.backup.store') }}" class="mb-3">
        @csrf
        <button class="btn btn-primary">
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
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Restore</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="otpRequestStep">
                    <p>We'll send a 6-digit code to <strong>{{ auth()->user()->email }}</strong></p>
                    <p class="text-muted small">This confirms your identity before restore.</p>
                    <button class="btn btn-primary" onclick="requestOtp()">Send Code</button>
                </div>
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

<script>
let currentBackupId = null;
const modal = new bootstrap.Modal(document.getElementById('restoreModal'));

function startRestore(backupId) {
    currentBackupId = backupId;
    document.getElementById('otpRequestStep').classList.remove('d-none');
    document.getElementById('otpVerifyStep').classList.add('d-none');
    document.getElementById('otpError').textContent = '';
    modal.show();
}

async function requestOtp() {
    const res = await fetch(`/tenant/backup/${currentBackupId}/restore/request`, {
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
    const res = await fetch(`/tenant/backup/${currentBackupId}/restore/verify`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ otp }),
    });
    const data = await res.json();
    if (data.success) {
        modal.hide();
        location.reload();
    } else {
        document.getElementById('otpError').textContent = data.message;
    }
}
</script>
@endsection

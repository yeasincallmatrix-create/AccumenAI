@extends('layouts.admin')
@section('title','Backups & Recovery — Super Admin')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="bi bi-cloud-upload me-2"></i>Backups & Recovery</h1>
        <div class="small text-muted">Backup management, retention, and inventory</div>
    </div>
    <a href="{{ route('super-admin.database.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Dashboard</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Total</div><div class="h4 mb-0">{{ $stats['total_verified']+$stats['total_failed'] }}</div></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Verified</div><div class="h4 mb-0 text-success">{{ $stats['total_verified'] }}</div></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Failed</div><div class="h4 mb-0 text-{{ $stats['total_failed']>0?'danger':'success' }}">{{ $stats['total_failed'] }}</div></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Protected</div><div class="h4 mb-0">{{ $retention['protected_backups']??0 }}</div></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Storage</div><div class="h4 mb-0">{{ $retention['storage']['total_mb']??0 }} MB</div><div class="small text-muted">/ {{ round(($retention['max_storage_bytes']??0)/1024/1024/1024,1) }} GB</div></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Retention</div><span class="badge bg-{{ ($retention['expired_count']??0)===0?'success':'warning' }}">{{ $retention['expired_count']??0 }} expired</span></div></div></div>
</div>

{{-- Create Backup --}}
<div class="card mb-4 border-primary">
    <div class="card-header bg-primary text-white"><i class="bi bi-plus-circle"></i> Create Backup</div>
    <div class="card-body">
        <form method="POST" action="{{ route('super-admin.database.backups.create') }}" onsubmit="return confirm('Create a backup? This will dump the production database.')">
            @csrf
            <div class="d-flex gap-2 align-items-center">
                <select name="type" class="form-select form-select-sm" style="width:160px">
                    <option value="manual">Manual</option>
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                </select>
                <button class="btn btn-primary btn-sm"><i class="bi bi-cloud-upload"></i> Create Backup</button>
            </div>
        </form>
    </div>
</div>

{{-- Backup Table --}}
<h5 class="mb-3">Backup History</h5>
<div class="card mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>Type</th><th>Filename</th><th>Created</th><th>Size</th><th>SHA256</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse($backups as $b)
                    <tr>
                        <td><span class="badge bg-light text-dark border">{{ $b->type }}</span></td>
                        <td class="small">{{ $b->filename }}</td>
                        <td class="small">{{ $b->created_at?->diffForHumans() }}</td>
                        <td class="small">{{ round($b->size_bytes/1024,1) }} KB</td>
                        <td class="small" style="max-width:120px;overflow:hidden;text-overflow:ellipsis">{{ $b->checksum ? substr($b->checksum,0,16).'…' : '—' }}</td>
                        <td><span class="badge bg-{{ $b->status==='verified'?'success':($b->status==='failed'?'danger':'secondary') }}">{{ $b->status }}</span></td>
                        <td>
                            @if($b->status !== 'verified' && $b->status !== 'failed')
                            <form method="POST" action="{{ route('super-admin.database.backups.verify', $b->id) }}" class="d-inline">@csrf<button class="btn btn-outline-success btn-sm py-0">Verify</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center small text-muted">No backups found</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Retention Policy --}}
<h5 class="mb-3">Retention Policy</h5>
<div class="card mb-4">
    <div class="card-body row g-3">
        <div class="col-md-2"><div class="small text-muted">Daily</div><div class="small fw-bold">{{ $retention['policy']['daily']['retain_days']??14 }} days</div></div>
        <div class="col-md-2"><div class="small text-muted">Weekly</div><div class="small fw-bold">{{ $retention['policy']['weekly']['retain_weeks']??8 }} weeks</div></div>
        <div class="col-md-2"><div class="small text-muted">Monthly</div><div class="small fw-bold">{{ $retention['policy']['monthly']['retain_months']??12 }} months</div></div>
        <div class="col-md-2"><div class="small text-muted">Manual</div><div class="small fw-bold">Indefinite</div></div>
        <div class="col-md-2"><div class="small text-muted">Pre-op</div><div class="small fw-bold">{{ $retention['policy']['pre_operation']['retain_days']??30 }} days</div></div>
        <div class="col-md-2">
            <form method="POST" action="{{ route('super-admin.database.retention.execute') }}" onsubmit="return confirm('Execute retention? This will remove expired backup files.')">
                @csrf
                <button class="btn btn-outline-warning btn-sm"><i class="bi bi-trash"></i> Execute Retention</button>
            </form>
        </div>
    </div>
</div>

{{-- Storage & Issues --}}
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h6>Storage</h6>
            <div class="small">Total: {{ $inventory['total_size_mb']??0 }} MB | Verified: {{ round(($inventory['verified_count']??0)*100/ max($inventory['total_backups']??1,1)) }}% | Missing files: {{ $inventory['storage']['files_missing']??0 }}</div>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h6>Issues</h6>
            @if(($inventory['issues_count']??0) > 0)
                @foreach($inventory['issues'] as $issue)
                    <div class="small text-{{ $issue['type']==='missing_file'?'danger':'warning' }}">[{{ $issue['type'] }}] {{ $issue['message'] }}</div>
                @endforeach
            @else
                <div class="small text-success">No issues found</div>
            @endif
        </div></div>
    </div>
</div>
@endsection

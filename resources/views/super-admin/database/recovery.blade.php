@extends('layouts.admin')
@section('title','Disaster Recovery — Super Admin')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="bi bi-arrow-repeat me-2"></i>Disaster Recovery</h1>
        <div class="small text-muted">RPO, RTO, and Restore Drill Management</div>
    </div>
    <a href="{{ route('super-admin.database.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Dashboard</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

{{-- RPO / RTO Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="small text-muted">Recovery Point Objective (RPO)</div>
                <div class="h3 mb-0">{{ $backup_health['checks']['rpo']['current_gap_minutes'] ?? 'N/A' }} min</div>
                <div class="small text-muted">Target: {{ $backup_health['checks']['rpo']['target_minutes'] ?? 1440 }} min</div>
                <span class="badge bg-{{ ($backup_health['checks']['rpo']['status'] ?? 'FAIL')==='PASS'?'success':'danger' }}">{{ $backup_health['checks']['rpo']['status'] ?? 'FAIL' }}</span>
                <div class="small mt-1">{{ $backup_health['checks']['rpo']['message'] ?? '' }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="small text-muted">Recovery Time Objective (RTO)</div>
                <div class="h3 mb-0">{{ $rto['average_recovery_seconds'] ?? '—' }}s</div>
                <div class="small text-muted">Target: {{ $rto['target_rto_minutes'] ?? 60 }} min</div>
                <span class="badge bg-{{ ($rto['rto_status'] ?? 'NOT_CONFIGURED')==='PASS'?'success':(($rto['rto_status'] ?? '')==='WARNING'?'warning':'secondary') }}">{{ $rto['rto_status'] ?? 'NOT_CONFIGURED' }}</span>
                <div class="small mt-1">Drills: {{ $rto['drill_count'] ?? 0 }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="small text-muted">Recovery Point Available</div>
                <div class="h3 mb-0">{{ $backup_health['checks']['recovery_point']['available'] ?? false ? 'YES' : 'NO' }}</div>
                <span class="badge bg-{{ ($backup_health['checks']['recovery_point']['status'] ?? 'FAIL')==='PASS'?'success':'danger' }}">{{ $backup_health['checks']['recovery_point']['status'] ?? 'FAIL' }}</span>
                <div class="small mt-1">{{ $backup_health['checks']['latest_verified']['latest'] ?? 'No verified backup' }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Run Restore Drill --}}
<div class="card mb-4 border-warning">
    <div class="card-header bg-warning text-dark"><i class="bi bi-tools"></i> Restore Drill</div>
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <div class="small">Creates verified backup → isolated temp DB → restore → verify → cleanup.</div>
                <div class="small text-muted">Uses temp database <code>{{ config('backup.restore_drill.temp_database', 'monetix_dr_test') }}</code>. Never touches production.</div>
            </div>
            <form method="POST" action="{{ route('super-admin.database.recovery.drill') }}" onsubmit="return confirm('Run a full restore drill? This creates a backup and restores it to an isolated temp database.')">
                @csrf
                <button class="btn btn-warning"><i class="bi bi-arrow-repeat"></i> Run Restore Drill</button>
            </form>
        </div>
    </div>
</div>

{{-- RTO History --}}
@if($rto['drill_count'] ?? 0 > 0)
<h5 class="mb-3">Drill History</h5>
<div class="card mb-4">
    <div class="card-body row g-3">
        <div class="col-md-3"><div class="small text-muted">Average</div><div class="h5 mb-0">{{ $rto['average_recovery_seconds'] }}s</div></div>
        <div class="col-md-3"><div class="small text-muted">Fastest</div><div class="h5 mb-0 text-success">{{ $rto['fastest_recovery_seconds'] }}s</div></div>
        <div class="col-md-3"><div class="small text-muted">Slowest</div><div class="h5 mb-0 text-warning">{{ $rto['slowest_recovery_seconds'] }}s</div></div>
        <div class="col-md-3"><div class="small text-muted">Latest</div><div class="small">{{ $rto['latest_recovery']['date'] ?? '—' }}</div></div>
    </div>
</div>
@endif

{{-- Storage --}}
<h5 class="mb-3">Backup Storage</h5>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body text-center">
            <div class="small text-muted">Local</div>
            <span class="badge bg-{{ ($storage['local']['status']??'PASS')==='PASS'?'success':'warning' }}">{{ $storage['local']['status']??'PASS' }}</span>
            <div class="small">{{ $storage['local']['file_count']??0 }} files, {{ $storage['local']['total_mb']??0 }} MB</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body text-center">
            <div class="small text-muted">Off-site</div>
            <span class="badge bg-{{ ($storage['offsite']['status']??'NOT_CONFIGURED')==='PASS'?'success':'secondary' }}">{{ $storage['offsite']['status']??'NOT_CONFIGURED' }}</span>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body text-center">
            <div class="small text-muted">Encryption</div>
            <span class="badge bg-{{ ($storage['encryption']['status']??'NOT_CONFIGURED')==='ACTIVE'?'success':'secondary' }}">{{ $storage['encryption']['status']??'NOT_CONFIGURED' }}</span>
        </div></div>
    </div>
</div>

<div class="alert alert-info small"><i class="bi bi-info-circle"></i> Production database was not modified. All restore drills use an isolated temporary database.</div>
@endsection

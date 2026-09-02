@extends('layouts.admin')
@section('title','Database Dashboard — Super Admin')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="bi bi-database-check me-2"></i>Database Dashboard</h1>
        <div class="small text-muted">Generated at {{ $monitoring['generated_at'] }} · Steps 101–126</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('super-admin.database.certification') }}" class="btn btn-outline-warning btn-sm"><i class="bi bi-patch-check"></i> Certification</a>
        <form method="POST" action="{{ route('super-admin.database.refresh') }}" class="d-inline">@csrf<button class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-clockwise"></i> Refresh</button></form>
    </div>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

@php
    $health = $monitoring['health'];
    $cert = $monitoring['certification'];
    $backup = $monitoring['backup'];
    $perf = $monitoring['performance'];
    $integrity = $monitoring['integrity'];
    $backupRecovery = $monitoring['backup_recovery'] ?? [];
@endphp

{{-- Overall Status --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="small text-muted">Certification Score</div>
                <div class="display-6 fw-bold {{ ($cert['overall_score']??0)>=90?'text-success':(($cert['overall_score']??0)>=70?'text-warning':'text-danger') }}">{{ $cert['overall_score']??0 }}/100</div>
                <span class="badge bg-{{ ($cert['status']??'')==='CERTIFIED'?'success':(($cert['status']??'')==='CERTIFIED WITH WARNINGS'?'warning':'danger') }}">{{ $cert['status']??'N/A' }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="small text-muted">Database Health</div>
                <div class="h2 mb-0 {{ $health['status']==='HEALTHY'?'text-success':($health['status']==='WARNING'?'text-warning':'text-danger') }}">{{ $health['score']??0 }}</div>
                <span class="badge bg-{{ $health['status']==='HEALTHY'?'success':($health['status']==='WARNING'?'warning':'danger') }}">{{ $health['status']??'N/A' }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="small text-muted">Backup Status</div>
                <div class="h5 mb-0">{{ $backup['backup_count']??0 }} total</div>
                <span class="badge bg-{{ ($backup['status']??'')==='PASS'?'success':(($backup['status']??'')==='WARNING'?'warning':'secondary') }}">{{ $backup['status']??'N/A' }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="small text-muted">Overall Status</div>
                @php
                    $overall = 'HEALTHY';
                    if (($health['score']??0) < 90 || ($cert['overall_score']??0) < 90) $overall = 'WARNING';
                    if (($health['score']??0) < 70 || ($cert['overall_score']??0) < 70) $overall = 'FAIL';
                @endphp
                <div class="h4 mb-0 {{ $overall==='HEALTHY'?'text-success':($overall==='WARNING'?'text-warning':'text-danger') }}">{{ $overall }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Infrastructure --}}
<h5 class="mb-3"><i class="bi bi-hdd-stack"></i> Infrastructure</h5>
<div class="row g-3 mb-4">
    @php $infra = [
        ['label'=>'Tables','value'=>$health['checks']['migrations']['ran']??0,'status'=>'PASS','route'=>'super-admin.database.health'],
        ['label'=>'Pending Migrations','value'=>count($health['checks']['migrations']['pending']??[]),'status'=>empty($health['checks']['migrations']['pending']??[])?'PASS':'FAIL','route'=>'super-admin.database.health'],
        ['label'=>'Missing Tables','value'=>count($health['missing_tables']??[]),'status'=>empty($health['missing_tables']??[])?'PASS':'FAIL','route'=>'super-admin.database.health'],
        ['label'=>'Schema Version','value'=>$monitoring['schema']['schema_version']??'—','status'=>$monitoring['schema']['status']??'PASS','route'=>'super-admin.database.health'],
        ['label'=>'Seed Integrity','value'=>empty($health['checks']['seeds']['missing']??[])?'OK':'ISSUES','status'=>empty($health['checks']['seeds']['missing']??[])?'PASS':'WARNING','route'=>'super-admin.database.health'],
        ['label'=>'Tenant Isolation','value'=>empty($health['checks']['tenant_isolation']['issues']??[])?'SECURE':'ISSUES','status'=>empty($health['checks']['tenant_isolation']['issues']??[])?'PASS':'FAIL','route'=>'super-admin.database.integrity'],
    ]; @endphp
    @foreach($infra as $item)
    <div class="col-md-2">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body text-center p-2">
                <div class="small text-muted">{{ $item['label'] }}</div>
                <div class="fw-bold">{{ $item['value'] }}</div>
                <span class="badge bg-{{ $item['status']==='PASS'?'success':($item['status']==='WARNING'?'warning':'danger') }}">{{ $item['status'] }}</span>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Data Integrity --}}
<h5 class="mb-3"><i class="bi bi-shield-check"></i> Data Integrity</h5>
<div class="row g-3 mb-4">
    @php $intItems = [
        ['label'=>'Orphan Records','value'=>$health['checks']['orphans']['orphans']??0,'status'=>empty($health['checks']['orphans']['orphans']??[])?'PASS':'WARNING'],
        ['label'=>'Foreign Keys','value'=>($fk['total_missing']??count($monitoring['integrity']['foreign_key']['missing']??[])),'status'=>($monitoring['integrity']['foreign_key_status']??'PASS')],
        ['label'=>'Duplicates','value'=>$dup['critical']??0,'status'=>($monitoring['integrity']['duplicate_status']??'PASS')],
        ['label'=>'Cross-Tenant Leakage','value'=>$tenant['leakage']??0,'status'=>($tenant['status']??'SECURE')==='SECURE'?'PASS':'FAIL'],
        ['label'=>'Soft Delete Issues','value'=>count($monitoring['integrity']['consistency_raw']['soft_delete']['issues']??[]),'status'=>$monitoring['integrity']['soft_delete_status']??'PASS'],
        ['label'=>'Accounting Integrity','value'=>($accounting['healthy']??true)?'OK':'ISSUES','status'=>$monitoring['integrity']['accounting_status']??'PASS'],
        ['label'=>'Inventory Integrity','value'=>($inventory['healthy']??true)?'OK':'ISSUES','status'=>$monitoring['integrity']['inventory_status']??'PASS'],
    ]; @endphp
    @foreach($intItems as $item)
    <div class="col-md-3 col-lg-2">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body text-center p-2">
                <div class="small text-muted">{{ $item['label'] }}</div>
                <div class="fw-bold">{{ $item['value'] }}</div>
                <span class="badge bg-{{ $item['status']==='PASS'||$item['status']==='SECURE'?'success':($item['status']==='WARNING'?'warning':'danger') }}">{{ $item['status'] }}</span>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Backup / Recovery --}}
<h5 class="mb-3"><i class="bi bi-cloud-upload"></i> Backup / Recovery</h5>
<div class="row g-3 mb-4">
    @php $bkItems = [
        ['label'=>'Latest Backup','value'=>$backup['latest_backup_timestamp']??'—','status'=>$backup['latest_backup_status']??'NOT_CONFIGURED'],
        ['label'=>'Verified Backup','value'=>$backup['latest_verified_backup']->filename??'—','status'=>$backup['restore_verification_status']??'NOT_CONFIGURED'],
        ['label'=>'Backup Health','value'=>$backupHealth['overall']??'—','status'=>$backupHealth['overall']??'FAIL'],
        ['label'=>'RPO','value'=>($backupRecovery['rpo_gap_minutes']??'N/A').' min','status'=>$backupRecovery['rpo_status']??'FAIL'],
        ['label'=>'RTO','value'=>($rto['average_recovery_seconds']??'—').'s','status'=>$rto['rto_status']??'NOT_CONFIGURED'],
        ['label'=>'Drill Count','value'=>$rto['drill_count']??0,'status'=>$rto['drill_count']??0>0?'PASS':'WARNING'],
        ['label'=>'Off-site','value'=>$storage['offsite']['status']??'NOT_CONFIGURED','status'=>$storage['offsite']['status']??'NOT_CONFIGURED'],
        ['label'=>'Encryption','value'=>$storage['encryption']['status']??'NOT_CONFIGURED','status'=>$storage['encryption']['status']??'NOT_CONFIGURED'],
    ]; @endphp
    @foreach($bkItems as $item)
    <div class="col-md-3 col-lg-2">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body text-center p-2">
                <div class="small text-muted">{{ $item['label'] }}</div>
                <div class="fw-bold" style="font-size:0.85rem">{{ $item['value'] }}</div>
                <span class="badge bg-{{ in_array($item['status'],['PASS','SECURE','ACTIVE','HEALTHY'])?'success':($item['status']==='WARNING'?'warning':($item['status']==='FAIL'?'danger':'secondary')) }}">{{ $item['status'] }}</span>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Performance --}}
<h5 class="mb-3"><i class="bi bi-speedometer2"></i> Performance</h5>
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body text-center">
            <div class="small text-muted">Slow Queries (24h)</div>
            <div class="h4 mb-0">{{ $perf['slow_query_count']??0 }}</div>
            <span class="badge bg-{{ ($perf['slow_query_count']??0)>0?'warning':'success' }}">{{ ($perf['slow_query_count']??0)>0?'WARNING':'PASS' }}</span>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body text-center">
            <div class="small text-muted">Failed Queries</div>
            <div class="h4 mb-0">{{ $perf['failed_query_count']??0 }}</div>
            <span class="badge bg-{{ ($perf['failed_query_count']??0)>0?'danger':'success' }}">{{ ($perf['failed_query_count']??0)>0?'FAIL':'PASS' }}</span>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body text-center">
            <div class="small text-muted">Avg Query Time</div>
            <div class="h4 mb-0">{{ $perf['average_query_time']??0 }}ms</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body text-center">
            <div class="small text-muted">Missing Indexes</div>
            <div class="h4 mb-0">{{ count($monitoring['indexes']['recommendations']??[]) }}</div>
            <span class="badge bg-secondary">RECOMMENDATION ONLY</span>
        </div></div>
    </div>
</div>

{{-- Quick Actions --}}
<h5 class="mb-3"><i class="bi bi-lightning"></i> Quick Actions</h5>
<div class="row g-3 mb-4">
    <div class="col-md-2"><a href="{{ route('super-admin.database.health') }}" class="btn btn-outline-primary w-100"><i class="bi bi-heart-pulse"></i> Health</a></div>
    <div class="col-md-2"><a href="{{ route('super-admin.database.integrity') }}" class="btn btn-outline-primary w-100"><i class="bi bi-shield-check"></i> Integrity</a></div>
    <div class="col-md-2"><a href="{{ route('super-admin.database.performance') }}" class="btn btn-outline-primary w-100"><i class="bi bi-graph-up"></i> Performance</a></div>
    <div class="col-md-2"><a href="{{ route('super-admin.database.backups') }}" class="btn btn-outline-primary w-100"><i class="bi bi-cloud-upload"></i> Backups</a></div>
    <div class="col-md-2"><a href="{{ route('super-admin.database.recovery') }}" class="btn btn-outline-primary w-100"><i class="bi bi-arrow-repeat"></i> Recovery</a></div>
    <div class="col-md-2"><a href="{{ route('super-admin.database.certification') }}" class="btn btn-outline-warning w-100"><i class="bi bi-patch-check"></i> Certify</a></div>
</div>

{{-- Recent Events --}}
<h5 class="mb-3"><i class="bi bi-clock-history"></i> Recent Database Events</h5>
<div class="card mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>Time</th><th>Event</th><th>Module</th><th>Status</th><th>Actor</th></tr></thead>
                <tbody>
                @forelse($monitoring['recent_events']??[] as $ev)
                    <tr>
                        <td class="small">{{ $ev['timestamp']??'—' }}</td>
                        <td class="small">{{ $ev['event']??'—' }}</td>
                        <td><span class="badge bg-light text-dark border">{{ $ev['module']??'—' }}</span></td>
                        <td><span class="badge bg-{{ ($ev['status']??'')==='PASS'||($ev['status']??'')==='healthy'||($ev['status']??'')==='verified'||($ev['status']??'')==='completed'?'success':(($ev['status']??'')==='FAIL'||($ev['status']??'')==='failed'?'danger':'secondary') }}">{{ $ev['status']??'—' }}</span></td>
                        <td class="small">{{ $ev['actor']??'—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center small text-muted">No recent events</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@extends('layouts.admin')
@section('title','Database Control Center — Super Admin')
@section('content')

@php
    $healthData = $monitoring['health'] ?? [];
    $certData = $monitoring['certification'] ?? [];
    $backupData = $monitoring['backup'] ?? [];
    $perfData = $monitoring['performance'] ?? [];
    $integrityData = $monitoring['integrity'] ?? [];
    $schemaData = $monitoring['schema'] ?? [];
    $backupRecovery = $monitoring['backup_recovery'] ?? [];
    $qm = $query_metrics ?? [];
    $cap = $capacity ?? [];
@endphp

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="bi bi-database-check me-2"></i>Database Control Center</h1>
        <div class="small text-muted">Steps 101–127 · READ-ONLY · Generated {{ $monitoring['generated_at'] ?? now() }}</div>
    </div>
    <div class="d-flex gap-2">
        <span class="badge bg-dark fs-6">READ ONLY</span>
        <form method="POST" action="{{ route('super-admin.database.refresh') }}" class="d-inline">@csrf<button class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-clockwise"></i> Refresh</button></form>
    </div>
</div>

@if(session('status'))<div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('status') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
@if(session('error'))<div class="alert alert-danger alert-dismissible fade show" role="alert">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif

{{-- ═══════════════════ OVERALL STATUS CARDS ═══════════════════ --}}
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
                <div class="h2 mb-0 {{ ($healthData['status']??'')==='HEALTHY'?'text-success':(($healthData['status']??'')==='WARNING'?'text-warning':'text-danger') }}">{{ $healthData['score']??0 }}</div>
                <span class="badge bg-{{ ($healthData['status']??'')==='HEALTHY'?'success':(($healthData['status']??'')==='WARNING'?'warning':'danger') }}">{{ $healthData['status']??'N/A' }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="small text-muted">Production Readiness</div>
                @php
                    $readiness = 'READY';
                    if (($healthData['score']??0) < 90 || ($cert['overall_score']??0) < 90) $readiness = 'CAUTION';
                    if (($healthData['score']??0) < 70 || ($cert['overall_score']??0) < 70) $readiness = 'NOT READY';
                @endphp
                <div class="h4 mb-0 {{ $readiness==='READY'?'text-success':($readiness==='CAUTION'?'text-warning':'text-danger') }}">{{ $readiness }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="small text-muted">Database Size</div>
                <div class="h5 mb-0">{{ round(($cap['database_size']??0)/1048576, 2) }} MB</div>
                <div class="small text-muted">{{ $cap['table_count'] ?? 0 }} tables</div>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════ ALERTS ═══════════════════ --}}
@if(!empty($alerts['alerts']))
<div class="alert alert-warning mb-4">
    <strong><i class="bi bi-exclamation-triangle"></i> Active Alerts ({{ count($alerts['alerts']) }}):</strong>
    <ul class="mb-0 mt-1">
        @foreach($alerts['alerts'] as $alert)
            <li class="small">{{ $alert['message'] ?? $alert['type'] ?? 'Unknown alert' }} <span class="badge bg-warning text-dark">{{ $alert['severity'] ?? 'WARNING' }}</span></li>
        @endforeach
    </ul>
</div>
@endif

{{-- ═══════════════════ TABS ═══════════════════ --}}
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-overview">Overview</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-health">Health &amp; Integrity</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-backup">Backup &amp; Recovery</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-performance">Performance</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-operations">Operations</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-events">Recent Events</a></li>
</ul>

<div class="tab-content">

{{-- ═══════════ TAB: OVERVIEW ═══════════ --}}
<div class="tab-pane fade show active" id="tab-overview">
    {{-- Infrastructure --}}
    <h6 class="mb-3"><i class="bi bi-hdd-stack"></i> Infrastructure</h6>
    <div class="row g-3 mb-4">
        @php $infra = [
            ['label'=>'Tables','value'=>$healthData['checks']['migrations']['ran']??0,'status'=>'PASS'],
            ['label'=>'Pending Migrations','value'=>count($healthData['checks']['migrations']['pending']??[]),'status'=>empty($healthData['checks']['migrations']['pending']??[])?'PASS':'FAIL'],
            ['label'=>'Missing Tables','value'=>count($healthData['missing_tables']??[]),'status'=>empty($healthData['missing_tables']??[])?'PASS':'FAIL'],
            ['label'=>'Schema Version','value'=>$schemaData['schema_version']??'—','status'=>$schemaData['status']??'PASS'],
            ['label'=>'Seed Integrity','value'=>empty($healthData['checks']['seeds']['missing']??[])?'OK':'ISSUES','status'=>empty($healthData['checks']['seeds']['missing']??[])?'PASS':'WARNING'],
            ['label'=>'Tenant Isolation','value'=>($tenant['status']??'SECURE'),'status'=>($tenant['status']??'SECURE')==='SECURE'?'PASS':'FAIL'],
        ]; @endphp
        @foreach($infra as $item)
        <div class="col-md-2">
            <div class="card h-100 border-0 shadow-sm"><div class="card-body text-center p-2">
                <div class="small text-muted">{{ $item['label'] }}</div>
                <div class="fw-bold">{{ $item['value'] }}</div>
                <span class="badge bg-{{ $item['status']==='PASS'?'success':($item['status']==='WARNING'?'warning':'danger') }}">{{ $item['status'] }}</span>
            </div></div>
        </div>
        @endforeach
    </div>

    {{-- Quick Status Row --}}
    <h6 class="mb-3"><i class="bi bi-clipboard-data"></i> Quick Status</h6>
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center">
            <div class="small text-muted">Total Backups</div>
            <div class="h4 mb-0">{{ $backup_stats['total_verified'] ?? 0 }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center">
            <div class="small text-muted">Backup Failures</div>
            <div class="h4 mb-0 {{ ($backup_stats['total_failed'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">{{ $backup_stats['total_failed'] ?? 0 }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center">
            <div class="small text-muted">Slow Queries (24h)</div>
            <div class="h4 mb-0 {{ ($perf['slow_query_count'] ?? 0) > 0 ? 'text-warning' : 'text-success' }}">{{ $perf['slow_query_count'] ?? 0 }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center">
            <div class="small text-muted">Failed Queries (24h)</div>
            <div class="h4 mb-0 {{ ($perf['failed_query_count'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">{{ $perf['failed_query_count'] ?? 0 }}</div>
        </div></div></div>
    </div>

    {{-- Quick Actions --}}
    <h6 class="mb-3"><i class="bi bi-lightning"></i> Quick Actions</h6>
    <div class="row g-2 mb-3">
        <div class="col-auto"><a href="{{ route('super-admin.database.health') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-heart-pulse"></i> Health</a></div>
        <div class="col-auto"><a href="{{ route('super-admin.database.integrity') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-shield-check"></i> Integrity</a></div>
        <div class="col-auto"><a href="{{ route('super-admin.database.performance') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-graph-up"></i> Performance</a></div>
        <div class="col-auto"><a href="{{ route('super-admin.database.backups') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-cloud-upload"></i> Backups</a></div>
        <div class="col-auto"><a href="{{ route('super-admin.database.recovery') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-repeat"></i> Recovery</a></div>
        <div class="col-auto"><a href="{{ route('super-admin.database.certification') }}" class="btn btn-outline-warning btn-sm"><i class="bi bi-patch-check"></i> Certify</a></div>
        <div class="col-auto"><a href="{{ route('super-admin.database.audit') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-journal-text"></i> Audit</a></div>
    </div>
</div>

{{-- ═══════════ TAB: HEALTH & INTEGRITY ═══════════ --}}
<div class="tab-pane fade" id="tab-health">
    <h6 class="mb-3"><i class="bi bi-shield-check"></i> Data Integrity</h6>
    <div class="row g-3 mb-4">
        @php $intItems = [
            ['label'=>'Orphan Records','value'=>$healthData['checks']['orphans']['orphans']??0,'status'=>empty($healthData['checks']['orphans']['orphans']??[])?'PASS':'WARNING'],
            ['label'=>'Foreign Keys','value'=>$fk['total_missing']??0,'status'=>($fk['total_missing']??0)===0?'PASS':'FAIL'],
            ['label'=>'Duplicates','value'=>$dup['critical']??0,'status'=>($dup['critical']??0)===0?'PASS':'WARNING'],
            ['label'=>'Cross-Tenant Leakage','value'=>$tenant['leakage']??0,'status'=>($tenant['status']??'SECURE')==='SECURE'?'PASS':'FAIL'],
            ['label'=>'Soft Delete Issues','value'=>count($consistency['soft_delete']['issues']??[]),'status'=>empty($consistency['soft_delete']['issues']??[])?'PASS':'WARNING'],
            ['label'=>'Accounting Integrity','value'=>($accounting['healthy']??true)?'OK':'ISSUES','status'=>($accounting['healthy']??true)?'PASS':'FAIL'],
            ['label'=>'Inventory Integrity','value'=>($inventory['healthy']??true)?'OK':'ISSUES','status'=>($inventory['healthy']??true)?'PASS':'FAIL'],
            ['label'=>'Consistency Score','value'=>$consistency['score']??'—','status'=>($consistency['score']??0)>=90?'PASS':(($consistency['score']??0)>=70?'WARNING':'FAIL')],
        ]; @endphp
        @foreach($intItems as $item)
        <div class="col-md-3 col-lg-2">
            <div class="card h-100 border-0 shadow-sm"><div class="card-body text-center p-2">
                <div class="small text-muted">{{ $item['label'] }}</div>
                <div class="fw-bold">{{ $item['value'] }}</div>
                <span class="badge bg-{{ $item['status']==='PASS'?'success':($item['status']==='WARNING'?'warning':'danger') }}">{{ $item['status'] }}</span>
            </div></div>
        </div>
        @endforeach
    </div>

    {{-- FK Missing Indexes --}}
    @if(!empty($fk['missing']))
    <div class="card mb-3 border-warning">
        <div class="card-header small bg-warning text-dark"><strong>FK Without Index ({{ count($fk['missing']) }})</strong> <span class="badge bg-dark">RECOMMENDATION ONLY</span></div>
        <div class="card-body p-2">
            @foreach(array_slice($fk['missing'], 0, 10) as $m)
                <div class="small mb-1">{{ $m['table'] }}.{{ $m['column'] }} → {{ $m['references'] }}</div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Duplicate Data --}}
    @if(!empty($dup['duplicates']))
    <div class="card mb-3 border-warning">
        <div class="card-header small bg-warning text-dark"><strong>Duplicate Records ({{ count($dup['duplicates']) }})</strong></div>
        <div class="card-body p-2">
            @foreach(array_slice($dup['duplicates'], 0, 10) as $d)
                <div class="small mb-1">{{ $d['table'] }}: {{ $d['count'] ?? '?' }} duplicates on {{ implode(',', $d['columns'] ?? []) }}</div>
            @endforeach
        </div>
    </div>
    @endif
</div>

{{-- ═══════════ TAB: BACKUP & RECOVERY ═══════════ --}}
<div class="tab-pane fade" id="tab-backup">
    <h6 class="mb-3"><i class="bi bi-cloud-upload"></i> Backup &amp; Recovery</h6>
    <div class="row g-3 mb-4">
        @php $bkItems = [
            ['label'=>'Latest Backup','value'=>$backupData['latest_backup_timestamp']??'—','status'=>$backupData['latest_backup_status']??'NOT_CONFIGURED'],
            ['label'=>'Verified Backup','value'=>$backupData['latest_verified_backup']->filename??'—','status'=>$backupData['restore_verification_status']??'NOT_CONFIGURED'],
            ['label'=>'Backup Health','value'=>$backup_health['overall']??'—','status'=>$backup_health['overall']??'FAIL'],
            ['label'=>'RPO','value'=>($backupRecovery['rpo_gap_minutes']??'N/A').' min','status'=>$backupRecovery['rpo_status']??'FAIL'],
            ['label'=>'RTO','value'=>($monitoring['backup_recovery']['rto_recovery_seconds']??'—').'s','status'=>$monitoring['backup_recovery']['rto_status']??'NOT_CONFIGURED'],
            ['label'=>'Drill Count','value'=>$monitoring['backup_recovery']['drill_count']??0,'status'=>($monitoring['backup_recovery']['drill_count']??0)>0?'PASS':'WARNING'],
            ['label'=>'Off-site','value'=>$monitoring['backup_recovery']['offsite_status']??'NOT_CONFIGURED','status'=>$monitoring['backup_recovery']['offsite_status']??'NOT_CONFIGURED'],
            ['label'=>'Encryption','value'=>$monitoring['backup_recovery']['encryption_status']??'NOT_CONFIGURED','status'=>$monitoring['backup_recovery']['encryption_status']??'NOT_CONFIGURED'],
        ]; @endphp
        @foreach($bkItems as $item)
        <div class="col-md-3 col-lg-2">
            <div class="card h-100 border-0 shadow-sm"><div class="card-body text-center p-2">
                <div class="small text-muted">{{ $item['label'] }}</div>
                <div class="fw-bold" style="font-size:0.85rem">{{ $item['value'] }}</div>
                <span class="badge bg-{{ in_array($item['status'],['PASS','SECURE','ACTIVE','HEALTHY'])?'success':($item['status']==='WARNING'?'warning':($item['status']==='FAIL'?'danger':'secondary')) }}">{{ $item['status'] }}</span>
            </div></div>
        </div>
        @endforeach
    </div>

    {{-- Backup Actions --}}
    <div class="row g-2 mb-4">
        <div class="col-auto">
            <form method="POST" action="{{ route('super-admin.database.backups.create') }}" class="d-inline">@csrf
                <input type="hidden" name="type" value="manual">
                <button class="btn btn-outline-primary btn-sm" onclick="return confirm('Create a manual backup?')"><i class="bi bi-plus-circle"></i> Create Backup</button>
            </form>
        </div>
        <div class="col-auto">
            <form method="POST" action="{{ route('super-admin.database.recovery.drill') }}" class="d-inline">@csrf
                <button class="btn btn-outline-warning btn-sm" onclick="return confirm('Run a disaster recovery drill?')"><i class="bi bi-arrow-repeat"></i> Run Drill</button>
            </form>
        </div>
    </div>

    {{-- Recent Backups --}}
    <h6>Recent Backups</h6>
    <div class="table-responsive mb-3">
        <table class="table table-sm table-hover">
            <thead><tr class="small text-muted"><th>File</th><th>Type</th><th>Status</th><th>Size</th><th>Created</th></tr></thead>
            <tbody>
            @forelse($backup_stats['recent'] ?? [] as $b)
                <tr>
                    <td class="small">{{ $b->filename ?? '—' }}</td>
                    <td><span class="badge bg-light text-dark border">{{ $b->type ?? '—' }}</span></td>
                    <td><span class="badge bg-{{ ($b->status??'')==='verified'?'success':(($b->status??'')==='completed'?'primary':'danger') }}">{{ $b->status ?? '—' }}</span></td>
                    <td class="small">{{ $b->size_bytes ? round($b->size_bytes/1048576, 2).' MB' : '—' }}</td>
                    <td class="small">{{ $b->created_at ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center small text-muted">No backups found</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ═══════════ TAB: PERFORMANCE ═══════════ --}}
<div class="tab-pane fade" id="tab-performance">
    {{-- Query Metrics --}}
    <h6 class="mb-3"><i class="bi bi-graph-up"></i> Query Metrics (24h)</h6>
    <div class="row g-3 mb-4">
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body text-center p-2">
            <div class="small text-muted">Total Queries</div>
            <div class="h5 mb-0">{{ number_format($qm['total_queries'] ?? 0) }}</div>
        </div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body text-center p-2">
            <div class="small text-muted">SELECT</div>
            <div class="h5 mb-0">{{ number_format($qm['select_count'] ?? 0) }}</div>
        </div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body text-center p-2">
            <div class="small text-muted">INSERT</div>
            <div class="h5 mb-0">{{ number_format($qm['insert_count'] ?? 0) }}</div>
        </div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body text-center p-2">
            <div class="small text-muted">UPDATE</div>
            <div class="h5 mb-0">{{ number_format($qm['update_count'] ?? 0) }}</div>
        </div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body text-center p-2">
            <div class="small text-muted">DELETE</div>
            <div class="h5 mb-0">{{ number_format($qm['delete_count'] ?? 0) }}</div>
        </div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body text-center p-2">
            <div class="small text-muted">Failed</div>
            <div class="h5 mb-0 {{ ($qm['failed_queries'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">{{ $qm['failed_queries'] ?? 0 }}</div>
        </div></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center p-2">
            <div class="small text-muted">Avg Duration</div>
            <div class="h5 mb-0">{{ $qm['average_duration'] ?? 0 }}ms</div>
        </div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center p-2">
            <div class="small text-muted">Max Duration</div>
            <div class="h5 mb-0">{{ $qm['max_duration'] ?? 0 }}ms</div>
        </div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center p-2">
            <div class="small text-muted">P95</div>
            <div class="h5 mb-0 {{ ($qm['p95'] ?? 0) > 500 ? 'text-warning' : 'text-success' }}">{{ $qm['p95'] ?? 0 }}ms</div>
        </div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center p-2">
            <div class="small text-muted">P99</div>
            <div class="h5 mb-0 {{ ($qm['p99'] ?? 0) > 1000 ? 'text-danger' : (($qm['p99'] ?? 0) > 500 ? 'text-warning' : 'text-success') }}">{{ $qm['p99'] ?? 0 }}ms</div>
        </div></div></div>
    </div>

    {{-- Slow Queries --}}
    <h6 class="mb-3"><i class="bi bi-hourglass-split"></i> Slow Queries</h6>
    @if(count($slow_queries) > 0)
    <div class="table-responsive mb-4">
        <table class="table table-sm table-hover">
            <thead><tr class="small text-muted"><th>Query</th><th>Time (ms)</th><th>Status</th></tr></thead>
            <tbody>
            @foreach($slow_queries as $sq)
                <tr>
                    <td class="small text-monospace" style="max-width:500px;overflow:hidden;text-overflow:ellipsis">{{ substr($sq->query ?? '', 0, 120) }}</td>
                    <td><span class="badge bg-{{ ($sq->execution_time ?? 0) > 1000 ? 'danger' : 'warning' }}">{{ $sq->execution_time ?? 0 }}</span></td>
                    <td><span class="badge bg-secondary">{{ $sq->status ?? 'slow' }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="alert alert-success mb-4"><i class="bi bi-check-circle"></i> No slow queries detected in the last 24 hours.</div>
    @endif

    {{-- Top Query Fingerprints --}}
    @if(!empty($qm['top_slow_fingerprints']))
    <h6 class="mb-3"><i class="bi bi-fingerprint"></i> Top Query Fingerprints (by duration)</h6>
    <div class="table-responsive mb-4">
        <table class="table table-sm table-hover">
            <thead><tr class="small text-muted"><th>Query Preview</th><th>Count</th><th>Avg (ms)</th><th>Max (ms)</th></tr></thead>
            <tbody>
            @foreach(array_slice($qm['top_slow_fingerprints'], 0, 10) as $fp)
                <tr>
                    <td class="small text-monospace" style="max-width:400px;overflow:hidden;text-overflow:ellipsis">{{ $fp['normalized_query'] ?? $fp['query_preview'] ?? '—' }}</td>
                    <td>{{ $fp['execution_count'] ?? $fp['count'] ?? 0 }}</td>
                    <td>{{ round($fp['average_duration'] ?? $fp['avg_ms'] ?? 0, 1) }}</td>
                    <td>{{ round($fp['max_duration'] ?? $fp['max_ms'] ?? 0, 1) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- N+1 Detection --}}
    <h6 class="mb-3"><i class="bi bi-diagram-3"></i> N+1 Detection</h6>
    @php $n1Findings = $n1['findings'] ?? []; @endphp
    @if(count($n1Findings) > 0)
    <div class="table-responsive mb-4">
        <table class="table table-sm table-hover">
            <thead><tr class="small text-muted"><th>Model</th><th>Classification</th><th>Severity</th><th>Type</th><th>Recommendation</th></tr></thead>
            <tbody>
            @foreach($n1Findings as $nf)
                <tr>
                    <td class="fw-bold">{{ $nf['model'] ?? '—' }}</td>
                    <td><span class="badge bg-{{ ($nf['classification']??'')==='CONFIRMED'?'danger':(($nf['classification']??'')==='SUSPECTED'?'warning':'secondary') }}">{{ $nf['classification'] ?? '—' }}</span></td>
                    <td><span class="badge bg-{{ ($nf['severity']??'')==='HIGH'?'danger':(($nf['severity']??'')==='MEDIUM'?'warning':'secondary') }}">{{ $nf['severity'] ?? '—' }}</span></td>
                    <td class="small">{{ $nf['type'] ?? '—' }}</td>
                    <td class="small">{{ $nf['recommendation'] ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="small text-muted mb-3">Summary: {{ $n1['summary']['confirmed'] ?? 0 }} confirmed, {{ $n1['summary']['suspected'] ?? 0 }} suspected, {{ $n1['summary']['review'] ?? 0 }} review</div>
    @else
    <div class="alert alert-success mb-4"><i class="bi bi-check-circle"></i> No N+1 patterns detected.</div>
    @endif

    {{-- Index Recommendations --}}
    <h6 class="mb-3"><i class="bi bi-list-check"></i> Index Recommendations <span class="badge bg-secondary">RECOMMENDATION ONLY</span></h6>
    @if(!empty($index_recs))
    <div class="table-responsive mb-4">
        <table class="table table-sm table-hover">
            <thead><tr class="small text-muted"><th>Table</th><th>Columns</th><th>Impact</th><th>Risk</th><th>Decision</th></tr></thead>
            <tbody>
            @foreach($index_recs as $rec)
                <tr>
                    <td>{{ $rec['table'] ?? '—' }}</td>
                    <td class="small">{{ $rec['columns'] ?? '—' }}</td>
                    <td><span class="badge bg-{{ ($rec['estimated_impact']??'')==='high'?'danger':(($rec['estimated_impact']??'')==='medium'?'warning':'secondary') }}">{{ $rec['estimated_impact'] ?? '—' }}</span></td>
                    <td class="small">{{ $rec['duplicate_risk'] ?? '—' }}</td>
                    <td><span class="badge bg-{{ str_contains($rec['recommendation']??'','CREATE')?'success':(str_contains($rec['recommendation']??'','DEFER')?'secondary':'warning') }}">{{ $rec['recommendation'] ?? '—' }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="alert alert-success mb-4"><i class="bi bi-check-circle"></i> No index recommendations.</div>
    @endif

    {{-- Duplicate Indexes --}}
    @if(!empty($dup_index))
    <h6 class="mb-3"><i class="bi bi-copy"></i> Duplicate Index Evidence <span class="badge bg-warning text-dark">REVIEW</span></h6>
    <div class="table-responsive mb-4">
        <table class="table table-sm table-hover">
            <thead><tr class="small text-muted"><th>Table</th><th>Type</th><th>Index A</th><th>Index B</th><th>Columns</th></tr></thead>
            <tbody>
            @foreach($dup_index as $d)
                <tr>
                    <td>{{ $d['table'] ?? '—' }}</td>
                    <td><span class="badge bg-warning text-dark">{{ $d['type'] ?? '—' }}</span></td>
                    <td class="small">{{ $d['index_a'] ?? $d['shorter'] ?? '—' }}</td>
                    <td class="small">{{ $d['index_b'] ?? $d['longer'] ?? '—' }}</td>
                    <td class="small">{{ $d['columns'] ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

{{-- ═══════════ TAB: OPERATIONS ═══════════ --}}
<div class="tab-pane fade" id="tab-operations">
    <h6 class="mb-3"><i class="bi bi-gear"></i> Database Operations <span class="badge bg-dark">READ ONLY</span></h6>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-heart-pulse"></i> Health &amp; Integrity</h6>
                    <div class="d-grid gap-2">
                        <a href="{{ route('super-admin.database.health') }}" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-heart-pulse"></i> Run Health Audit</a>
                        <a href="{{ route('super-admin.database.integrity') }}" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-shield-check"></i> Run Integrity Check</a>
                        <a href="{{ route('super-admin.database.monitoring') }}" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-binoculars"></i> Full Monitoring Snapshot</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-cloud-upload"></i> Backup &amp; Recovery</h6>
                    <div class="d-grid gap-2">
                        <form method="POST" action="{{ route('super-admin.database.backups.create') }}">@csrf
                            <input type="hidden" name="type" value="manual">
                            <button class="btn btn-outline-primary btn-sm text-start w-100" onclick="return confirm('Create manual backup?')"><i class="bi bi-plus-circle"></i> Create Backup</button>
                        </form>
                        <form method="POST" action="{{ route('super-admin.database.recovery.drill') }}">@csrf
                            <button class="btn btn-outline-warning btn-sm text-start w-100" onclick="return confirm('Run DR drill?')"><i class="bi bi-arrow-repeat"></i> Run DR Drill</button>
                        </form>
                        <a href="{{ route('super-admin.database.backups') }}" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-list"></i> View All Backups</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-clipboard-check"></i> Audit &amp; Certification</h6>
                    <div class="d-grid gap-2">
                        <a href="{{ route('super-admin.database.certification') }}" class="btn btn-outline-warning btn-sm text-start"><i class="bi bi-patch-check"></i> Run Certification</a>
                        <a href="{{ route('super-admin.database.audit') }}" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-journal-text"></i> View Audit Logs</a>
                        <a href="{{ route('super-admin.database.performance') }}" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-graph-up"></i> Performance Details</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <h6>Artisan Commands (read-only)</h6>
        <div class="small text-muted mb-2">All commands are safe, read-only operations. No destructive operations permitted.</div>
        <div class="row g-2">
            <div class="col-auto"><code class="small">database:performance-baseline --json</code></div>
            <div class="col-auto"><code class="small">database:query-stats --json</code></div>
            <div class="col-auto"><code class="small">database:slow-queries --json</code></div>
            <div class="col-auto"><code class="small">database:n1-detection --json</code></div>
            <div class="col-auto"><code class="small">database:index-audit --json</code></div>
            <div class="col-auto"><code class="small">database:certify --json</code></div>
            <div class="col-auto"><code class="small">database:monitor --json</code></div>
        </div>
    </div>
</div>

{{-- ═══════════ TAB: RECENT EVENTS ═══════════ --}}
<div class="tab-pane fade" id="tab-events">
    <h6 class="mb-3"><i class="bi bi-clock-history"></i> Recent Database Events</h6>
    <div class="card mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr class="small text-muted"><th>Time</th><th>Event</th><th>Module</th><th>Status</th><th>Actor</th></tr></thead>
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
</div>

</div>{{-- /tab-content --}}

@endsection

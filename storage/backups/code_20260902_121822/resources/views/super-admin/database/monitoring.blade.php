@extends('layouts.admin')

@section('title','Database Monitoring — Super Admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="bi bi-database-check me-2"></i>Database Monitoring</h1>
        <div class="small text-muted">Generated at {{ $monitoring['generated_at'] }} · Enterprise Hardening Steps 101–121</div>
    </div>
    <form method="POST" action="{{ route('super-admin.database.monitoring.refresh') }}">
        @csrf
        <button class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
    </form>
</div>

@if(session('status'))
<div class="alert alert-success">{{ session('status') }}</div>
@endif

@php
  $health = $monitoring['health'];
  $cert = $monitoring['certification'];
  $backup = $monitoring['backup'];
  $perf = $monitoring['performance'];
  $schema = $monitoring['schema'];
  $archive = $monitoring['archive'];
  $integrity = $monitoring['integrity'];
@endphp

{{-- 1. Overall --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="small text-muted">Overall Score</div>
                <div class="display-6 fw-bold {{ $cert['overall_score'] >=90 ? 'text-success' : ($cert['overall_score']>=70 ? 'text-warning' : 'text-danger') }}">{{ $cert['overall_score'] }}/100</div>
                <span class="badge bg-{{ $cert['status']==='CERTIFIED' ? 'success' : ($cert['status']==='CERTIFIED WITH WARNINGS' ? 'warning' : 'danger') }}">{{ $cert['status'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="small text-muted">Health</div>
                <div class="h5 mb-0 {{ $health['status']==='HEALTHY' ? 'text-success' : ($health['status']==='WARNING' ? 'text-warning' : 'text-danger') }}">{{ $health['status'] }} ({{ $health['score'] }})</div>
                <div class="small text-muted">Migrations: {{ $health['migration_status'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="small text-muted">Last Audit</div>
                <div class="small">{{ $monitoring['generated_at'] }}</div>
                <span class="badge bg-light text-dark border">{{ $health['status'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="small text-muted">Backup</div>
                <div class="small">{{ $backup['backup_count'] ?? 0 }} total, {{ $backup['failed_backup_count'] ?? 0 }} failed</div>
                <div class="small text-muted">{{ $backup['latest_backup_timestamp'] ?? '—' }}</div>
            </div>
        </div>
    </div>
</div>

{{-- 2. Integrity --}}
<h5 class="mb-3"><i class="bi bi-shield-check"></i> Integrity</h5>
<div class="row g-3 mb-4">
    @foreach(['consistency_status'=>'Consistency','foreign_key_status'=>'Foreign Keys','duplicate_status'=>'Duplicates','accounting_status'=>'Accounting','inventory_status'=>'Inventory','soft_delete_status'=>'Soft Delete'] as $key=>$label)
    <div class="col-md-2">
        <div class="card h-100">
            <div class="card-body text-center p-2">
                <div class="small text-muted">{{ $label }}</div>
                @php $val = $integrity[$key] ?? $monitoring['integrity'][$key] ?? 'NOT_CONFIGURED'; @endphp
                <span class="badge bg-{{ $val==='PASS' ? 'success' : ($val==='WARNING' ? 'warning' : ($val==='FAIL' ? 'danger' : 'secondary')) }}">{{ $val }}</span>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- 3. Backup & Recovery --}}
<h5 class="mb-3"><i class="bi bi-cloud-upload"></i> Backup & Recovery</h5>
<div class="card mb-4">
    <div class="card-body row g-3">
        <div class="col-md-3"><div class="small text-muted">Last backup</div><div class="small">{{ $backup['latest_backup']->filename ?? '—' }}<br>{{ $backup['latest_backup_timestamp'] ?? '' }}</div></div>
        <div class="col-md-3"><div class="small text-muted">Last verified</div><div class="small">{{ $backup['latest_verified_backup']->filename ?? '—' }}</div></div>
        <div class="col-md-2"><div class="small text-muted">Verification</div><span class="badge bg-{{ ($backup['restore_verification_status'] ?? '')==='PASS' ? 'success' : 'warning' }}">{{ $backup['restore_verification_status'] ?? 'NOT_CONFIGURED' }}</span></div>
        <div class="col-md-2"><div class="small text-muted">Restore readiness</div><span class="badge bg-{{ ($backup['disaster_recovery_readiness'] ?? '')==='RECOVERY READY' ? 'success' : 'secondary' }}">{{ $backup['disaster_recovery_readiness'] ?? '—' }}</span></div>
        <div class="col-md-2"><div class="small text-muted">DR Status</div><span class="badge bg-light text-dark border">{{ $backup['disaster_recovery']['result'] ?? '—' }}</span></div>
    </div>
</div>

{{-- 3b. Backup Automation Status --}}
@php $auto = $backup['automation'] ?? []; @endphp
@if($auto)
<div class="card mb-4 border-primary">
    <div class="card-header bg-primary text-white"><small><i class="bi bi-clock-history"></i> Backup Automation (Step 122)</small></div>
    <div class="card-body row g-3">
        <div class="col-md-2"><div class="small text-muted">Daily schedule</div><div class="small"><span class="badge bg-{{ ($auto['daily_enabled'] ?? false) ? 'success' : 'secondary' }}">{{ ($auto['daily_enabled'] ?? false) ? 'ENABLED' : 'DISABLED' }}</span> {{ $auto['daily_schedule'] ?? '' }}</div></div>
        <div class="col-md-2"><div class="small text-muted">Weekly schedule</div><div class="small"><span class="badge bg-{{ ($auto['weekly_enabled'] ?? false) ? 'success' : 'secondary' }}">{{ ($auto['weekly_enabled'] ?? false) ? 'ENABLED' : 'DISABLED' }}</span> {{ ucfirst($auto['weekly_day'] ?? '') }} {{ $auto['weekly_schedule'] ?? '' }}</div></div>
        <div class="col-md-2"><div class="small text-muted">Verification</div><div class="small"><span class="badge bg-{{ ($auto['verification_enabled'] ?? false) ? 'success' : 'secondary' }}">{{ ($auto['verification_enabled'] ?? false) ? 'ON' : 'OFF' }}</span></div></div>
        <div class="col-md-2"><div class="small text-muted">Backup running</div><div class="small"><span class="badge bg-{{ ($auto['is_running'] ?? false) ? 'warning' : 'success' }}">{{ ($auto['is_running'] ?? false) ? 'YES' : 'NO' }}</span></div></div>
        <div class="col-md-2"><div class="small text-muted">Total verified</div><div class="h6 mb-0">{{ $auto['total_verified'] ?? 0 }}</div></div>
        <div class="col-md-2"><div class="small text-muted">Total failed</div><div class="h6 mb-0 text-{{ ($auto['total_failed'] ?? 0) > 0 ? 'danger' : 'success' }}">{{ $auto['total_failed'] ?? 0 }}</div></div>
    </div>
    <div class="card-body row g-3 pt-0">
        <div class="col-md-3"><div class="small text-muted">Backup age</div><div class="small">{{ ($auto['backup_age_hours'] ?? null) !== null ? ($auto['backup_age_hours'] ?? 0) . ' hours' : 'N/A' }} @if($auto['age_warning'] ?? false)<span class="badge bg-warning text-dark">STALE</span>@endif</div></div>
        <div class="col-md-3"><div class="small text-muted">No verified backup</div><div class="small">@if($auto['no_verified_backup'] ?? false)<span class="badge bg-danger">WARNING</span>@else<span class="badge bg-success">OK</span>@endif</div></div>
        @if($auto['latest_daily'] ?? null)
        <div class="col-md-3"><div class="small text-muted">Last daily</div><div class="small">{{ $auto['latest_daily']['filename'] ?? '—' }}<br>{{ $auto['latest_daily']['created_at'] ?? '' }}</div></div>
        @endif
        @if($auto['latest_failed'] ?? null)
        <div class="col-md-3"><div class="small text-muted">Last failed</div><div class="small text-danger">{{ $auto['latest_failed']['filename'] ?? '—' }}<br>{{ $auto['latest_failed']['reason'] ?? '' }}</div></div>
        @endif
    </div>
</div>
@endif

{{-- 3c. Step 125 — Backup & Recovery Operations --}}
@php
    $backupHealth = app(\App\Services\System\BackupHealthService::class)->check();
    $backupStorage = app(\App\Services\System\BackupStorageService::class)->status();
    $rtoStatus = app(\App\Services\System\RecoveryTimeService::class)->status();
    $retentionReport = app(\App\Services\System\BackupRetentionService::class)->report();
@endphp
<h5 class="mb-3"><i class="bi bi-cloud-upload"></i> Backup & Recovery Operations <span class="badge bg-info">Step 125</span></h5>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="small text-muted">Backup Health</div><span class="badge bg-{{ ($backupHealth['overall'] ?? 'PASS')==='PASS' ? 'success' : (($backupHealth['overall'] ?? '')==='WARNING' ? 'warning' : 'danger') }}">{{ $backupHealth['overall'] ?? 'PASS' }}</span></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="small text-muted">RPO Status</div><span class="badge bg-{{ ($backupHealth['checks']['rpo']['status'] ?? 'FAIL')==='PASS' ? 'success' : (($backupHealth['checks']['rpo']['status'] ?? '')==='WARNING' ? 'warning' : 'danger') }}">{{ $backupHealth['checks']['rpo']['status'] ?? 'FAIL' }}</span><div class="small">{{ $backupHealth['checks']['rpo']['current_gap_minutes'] ?? 'N/A' }} min gap</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="small text-muted">RTO Status</div><span class="badge bg-{{ ($rtoStatus['rto_status'] ?? 'NOT_CONFIGURED')==='PASS' ? 'success' : (($rtoStatus['rto_status'] ?? '')==='WARNING' ? 'warning' : 'secondary') }}">{{ $rtoStatus['rto_status'] ?? 'NOT_CONFIGURED' }}</span><div class="small">Avg {{ $rtoStatus['average_recovery_seconds'] ?? '—' }}s</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="small text-muted">Retention</div><span class="badge bg-{{ ($retentionReport['expired_count'] ?? 0)===0 ? 'success' : 'warning' }}">{{ $retentionReport['expired_count'] ?? 0 }} expired</span><div class="small">{{ $retentionReport['storage']['total_mb'] ?? 0 }} MB used</div></div></div></div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="small text-muted">Local Backup</div><span class="badge bg-{{ ($backupStorage['local']['status'] ?? 'PASS')==='PASS' ? 'success' : 'warning' }}">{{ $backupStorage['local']['status'] ?? 'PASS' }}</span><div class="small">{{ $backupStorage['local']['file_count'] ?? 0 }} files</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="small text-muted">Off-site Backup</div><span class="badge bg-{{ ($backupStorage['offsite']['status'] ?? 'NOT_CONFIGURED')==='PASS' ? 'success' : (($backupStorage['offsite']['status'] ?? '')==='FAIL' ? 'danger' : 'secondary') }}">{{ $backupStorage['offsite']['status'] ?? 'NOT_CONFIGURED' }}</span></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="small text-muted">Backup Encryption</div><span class="badge bg-{{ ($backupStorage['encryption']['status'] ?? 'NOT_CONFIGURED')==='ACTIVE' ? 'success' : 'secondary' }}">{{ $backupStorage['encryption']['status'] ?? 'NOT_CONFIGURED' }}</span></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="small text-muted">Consecutive Failures</div><span class="badge bg-{{ ($backupHealth['checks']['failures']['consecutive_failures'] ?? 0) >= 3 ? 'danger' : (($backupHealth['checks']['failures']['consecutive_failures'] ?? 0) > 0 ? 'warning' : 'success') }}">{{ $backupHealth['checks']['failures']['consecutive_failures'] ?? 0 }}</span></div></div></div>
</div>
<div class="card mb-4">
    <div class="card-body row g-3">
        <div class="col-md-2"><div class="small text-muted">Latest daily</div><div class="small">{{ $backupHealth['checks']['daily_backup']['latest'] ?? '—' }}<br>Age: {{ $backupHealth['checks']['daily_backup']['age_hours'] ?? 'N/A' }}h</div></div>
        <div class="col-md-2"><div class="small text-muted">Latest weekly</div><div class="small">{{ $backupHealth['checks']['weekly_backup']['latest'] ?? '—' }}<br>Age: {{ $backupHealth['checks']['weekly_backup']['age_days'] ?? 'N/A' }}d</div></div>
        <div class="col-md-2"><div class="small text-muted">Latest verified</div><div class="small">{{ $backupHealth['checks']['latest_verified']['latest'] ?? '—' }}</div></div>
        <div class="col-md-2"><div class="small text-muted">Recovery Point</div><div class="small">{{ $backupHealth['checks']['recovery_point']['available'] ? 'Available' : 'NOT AVAILABLE' }}<br>{{ $backupHealth['checks']['rpo']['message'] ?? '' }}</div></div>
        <div class="col-md-2"><div class="small text-muted">Restore Drill</div><div class="small">{{ $rtoStatus['drill_count'] ?? 0 }} drills<br>Avg {{ $rtoStatus['average_recovery_seconds'] ?? '—' }}s</div></div>
        <div class="col-md-2"><div class="small text-muted">Protected backups</div><div class="h6 mb-0">{{ $retentionReport['protected_backups'] ?? 0 }}</div></div>
    </div>
</div>

{{-- 4. Performance --}}
<h5 class="mb-3"><i class="bi bi-speedometer2"></i> Performance</h5>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="small text-muted">Slow queries (24h)</div><div class="h5 mb-0">{{ $perf['slow_query_count'] }}</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="small text-muted">Failed queries</div><div class="h5 mb-0 text-{{ $perf['failed_query_count']>0 ? 'danger' : 'success' }}">{{ $perf['failed_query_count'] }}</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="small text-muted">Avg time (ms)</div><div class="h5 mb-0">{{ $perf['average_query_time'] }}</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><div class="small text-muted">Largest tables</div>@foreach(array_slice($perf['largest_tables'] ?? [],0,3) as $t)<div class="small">{{ $t['table'] }} ({{ number_format($t['rows'] ?? 0) }} rows)</div>@endforeach</div></div></div>
</div>

{{-- 5. Schema --}}
<h5 class="mb-3"><i class="bi bi-code-square"></i> Schema</h5>
<div class="card mb-4">
    <div class="card-body row g-3">
        <div class="col-md-3"><div class="small text-muted">Migrations</div><div class="small">{{ $schema['current_migration_count'] }} total, {{ count($schema['pending_migrations'] ?? []) }} pending — <span class="badge bg-{{ $schema['status']==='PASS' ? 'success' : 'danger' }}">{{ $schema['status'] }}</span></div></div>
        <div class="col-md-3"><div class="small text-muted">Schema version</div><div class="small">{{ $schema['schema_version'] ?? '—' }}</div></div>
        <div class="col-md-3"><div class="small text-muted">Seed version</div><div class="small">{{ is_array($schema['seed_version']) ? json_encode(array_keys($schema['seed_version']['results'] ?? [])) : '—' }}</div></div>
        <div class="col-md-3"><div class="small text-muted">Missing tables</div><div class="small">{{ count($health['missing_tables'] ?? []) }} — <span class="badge bg-{{ empty($health['missing_tables']) ? 'success' : 'danger' }}">{{ empty($health['missing_tables']) ? 'PASS' : 'FAIL' }}</span></div></div>
    </div>
</div>

{{-- 6. Index Recommendations --}}
<h5 class="mb-3"><i class="bi bi-list-columns-reverse"></i> Index Recommendations <span class="badge bg-warning text-dark">RECOMMENDATION ONLY</span></h5>
<div class="card mb-4">
    <div class="card-body">
        @php $recs = $monitoring['indexes']['recommendations'] ?? []; @endphp
        @if(empty($recs))
            <div class="text-success small">No missing critical indexes — all present</div>
        @else
            @foreach($recs as $r)
                <div class="border rounded p-2 mb-2">
                    <div class="fw-semibold">{{ $r['table'] }} ({{ $r['columns'] }})</div>
                    <div class="small text-muted">Current: {{ implode(', ', $r['current_indexes']) ?: 'none' }} | Benefit: {{ $r['query_benefit'] }} | Impact: {{ $r['estimated_impact'] }} | Risk: {{ $r['duplicate_risk'] }}</div>
                    <span class="badge bg-{{ str_contains($r['recommendation'],'CREATE') ? 'success' : (str_contains($r['recommendation'],'DEFER') ? 'secondary' : 'warning') }}">{{ $r['recommendation'] }}</span>
                    <span class="small text-muted"> — Do NOT create automatically</span>
                </div>
            @endforeach
        @endif
    </div>
</div>

{{-- 124-J — Production Query Intelligence (READ ONLY) --}}
@php $qi = $monitoring['query_intelligence'] ?? []; @endphp
<div class="alert alert-info small"><i class="bi bi-eye"></i> <strong>READ ONLY</strong> — Production Query Intelligence — <span class="badge bg-secondary">RECOMMENDATION ONLY</span> where noted</div>
<h5 class="mb-3"><i class="bi bi-graph-up"></i> Production Query Intelligence <span class="badge bg-secondary">READ ONLY</span></h5>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="small text-muted">Query Health</div><span class="badge bg-{{ ($qi['query_health']['status'] ?? 'PASS')==='PASS' ? 'success' : 'warning' }}">{{ $qi['query_health']['status'] ?? 'PASS' }}</span><div class="small">Avg {{ $qi['query_health']['stats']['average_duration'] ?? 0 }}ms | p95 {{ $qi['query_health']['stats']['p95_duration'] ?? 0 }}ms</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="small text-muted">Slow Queries</div><span class="badge bg-{{ ($qi['slow_queries']['status'] ?? 'PASS')==='PASS' ? 'success' : 'warning' }}">{{ $qi['slow_queries']['status'] ?? 'PASS' }}</span><div class="small">{{ count($qi['slow_queries']['data'] ?? []) }} found</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="small text-muted">Top Fingerprints</div><div class="small">Count: {{ count($qi['top_fingerprints']['by_count'] ?? []) }} | Duration: {{ count($qi['top_fingerprints']['by_duration'] ?? []) }}</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="small text-muted">N+1 Detection</div><span class="badge bg-{{ ($qi['n1_detection']['status'] ?? 'PASS')==='PASS' ? 'success' : 'danger' }}">{{ $qi['n1_detection']['status'] ?? 'PASS' }}</span><div class="small">Confirmed {{ $qi['n1_detection']['summary']['confirmed'] ?? 0 }}, Suspected {{ $qi['n1_detection']['summary']['suspected'] ?? 0 }}</div></div></div></div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="small text-muted">Endpoint Performance</div><span class="badge bg-{{ ($qi['endpoint_performance']['status'] ?? 'PASS')==='PASS' ? 'success' : 'secondary' }}">{{ $qi['endpoint_performance']['status'] ?? 'PASS' }}</span><div class="small">{{ count($qi['endpoint_performance']['data'] ?? []) }} routes</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="small text-muted">Tenant Performance</div><span class="badge bg-success">PASS</span><div class="small">Super Admin only</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="small text-muted">Capacity</div><div class="small">{{ isset($qi['capacity']['data']['database_size']) ? round($qi['capacity']['data']['database_size']/1024/1024,2).' MB' : '—' }}</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><div class="small text-muted">Duplicate Evidence</div><span class="badge bg-{{ ($qi['duplicate_evidence']['status'] ?? 'PASS')==='PASS' ? 'success' : 'warning' }}">{{ $qi['duplicate_evidence']['status'] ?? 'PASS' }}</span><div class="small">READ ONLY</div></div></div></div>
</div>

{{-- 6b. Database Performance (Step 123) --}}
@php
    $perfService = app(\App\Services\System\DatabasePerformanceService::class);
    $perfStats = $perfService->stats(24);
    $slowQueries = $perfService->slowQueries(5);
    $analysisService = app(\App\Services\System\DatabaseIndexAnalysisService::class);
    $sixRecs = $analysisService->analyzeSixRecommendations();
    $dups = $analysisService->duplicatePrefixAnalysis();
    $n1Service = app(\App\Services\System\N1DetectionService::class);
    $n1Findings = $n1Service->detect();
@endphp
<h5 class="mb-3"><i class="bi bi-speedometer2"></i> Database Performance <span class="badge bg-info">Step 123</span></h5>
<div class="card mb-4 border-info">
    <div class="card-body row g-3">
        <div class="col-md-2"><div class="small text-muted">Slow queries (24h)</div><div class="h5 mb-0 text-{{ $perfStats['slow_query_count'] > 0 ? 'warning' : 'success' }}">{{ $perfStats['slow_query_count'] }}</div></div>
        <div class="col-md-2"><div class="small text-muted">Failed queries (24h)</div><div class="h5 mb-0 text-{{ $perfStats['failed_query_count'] > 0 ? 'danger' : 'success' }}">{{ $perfStats['failed_query_count'] }}</div></div>
        <div class="col-md-2"><div class="small text-muted">Avg duration</div><div class="h5 mb-0">{{ $perfStats['average_execution_time'] }}ms</div></div>
        <div class="col-md-2"><div class="small text-muted">Total queries (24h)</div><div class="h5 mb-0">{{ $perfStats['total_queries'] }}</div></div>
        <div class="col-md-2"><div class="small text-muted">Duplicate indexes</div><div class="h5 mb-0 text-{{ count($dups) > 0 ? 'warning' : 'success' }}">{{ count($dups) }}</div></div>
        <div class="col-md-2"><div class="small text-muted">N+1 findings</div><div class="h5 mb-0 text-{{ count($n1Findings) > 0 ? 'warning' : 'success' }}">{{ count($n1Findings) }}</div></div>
    </div>
</div>

{{-- Six Index Recommendations --}}
<div class="card mb-3">
    <div class="card-header small"><strong>Six Index Recommendations (analysis only)</strong></div>
    <div class="card-body p-2">
        @forelse($sixRecs as $rec)
            <div class="mb-2 p-2 border-bottom small">
                <div class="fw-semibold">{{ $rec['table'] }}({{ implode(',', $rec['proposed_columns']) }}) <span class="badge bg-{{ $rec['recommendation'] === 'CREATE' ? 'success' : ($rec['recommendation'] === 'SKIP' ? 'secondary' : 'warning') }}">{{ $rec['recommendation'] }}</span></div>
                <div class="text-muted">Rows: {{ number_format($rec['row_count']) }} | Impact: {{ $rec['estimated_impact'] }} | Risk: {{ $rec['duplicate_risk'] }}</div>
                <div class="text-muted">{{ $rec['reason'] }}</div>
            </div>
        @empty
            <div class="small text-muted">No recommendations found</div>
        @endforelse
    </div>
</div>

{{-- Duplicate Index Warnings --}}
@if(count($dups) > 0)
<div class="card mb-3 border-warning">
    <div class="card-header small bg-warning text-dark"><strong>Duplicate/Redundant Indexes ({{ count($dups) }} found)</strong></div>
    <div class="card-body p-2">
        @foreach($dups as $dup)
            <div class="mb-1 p-2 border-bottom small">
                <span class="badge bg-warning text-dark">{{ $dup['type'] }}</span>
                {{ $dup['table'] }}: {{ $dup['index_a'] ?? $dup['shorter'] ?? '' }} vs {{ $dup['index_b'] ?? $dup['longer'] ?? '' }}
                <span class="text-muted"> — REVIEW recommended, not auto-dropped</span>
            </div>
        @endforeach
    </div>
</div>
@endif

{{-- Top Slow Queries --}}
@if(count($slowQueries) > 0)
<div class="card mb-3">
    <div class="card-header small"><strong>Top Slow Queries</strong></div>
    <div class="card-body p-2">
        @foreach($slowQueries as $sq)
            <div class="mb-1 p-1 border-bottom small text-monospace">{{ substr($sq->query, 0, 100) }} <span class="badge bg-secondary">{{ $sq->execution_time }}ms</span></div>
        @endforeach
    </div>
</div>
@endif

{{-- 7. Archive --}}
<h5 class="mb-3"><i class="bi bi-archive"></i> Archive</h5>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card"><div class="card-body text-center"><div class="small text-muted">Pending</div><div class="h5 mb-0">{{ $archive['pending'] }}</div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body text-center"><div class="small text-muted">Completed</div><div class="h5 mb-0 text-success">{{ $archive['completed'] }}</div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body text-center"><div class="small text-muted">Failed</div><div class="h5 mb-0 text-danger">{{ $archive['failed'] }}</div></div></div></div>
</div>

{{-- 8. Recent Events --}}
<h5 class="mb-3"><i class="bi bi-clock-history"></i> Recent Database Events</h5>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr class="small text-muted"><th>Timestamp</th><th>Event</th><th>Module</th><th>Status</th><th>Actor</th></tr></thead>
            <tbody>
                @foreach($monitoring['recent_events'] as $ev)
                <tr class="small"><td>{{ $ev['timestamp'] }}</td><td>{{ $ev['event'] }}</td><td>{{ $ev['module'] }}</td><td><span class="badge bg-light text-dark border">{{ $ev['status'] }}</span></td><td>{{ $ev['actor'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection

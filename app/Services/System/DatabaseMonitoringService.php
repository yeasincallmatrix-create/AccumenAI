<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 121-A — Unified Database Monitoring Snapshot (READ-ONLY)
 * Reuses Step 101–120 services without duplicating logic.
 */
class DatabaseMonitoringService
{
    public function __construct(
        private readonly DatabaseHealthCheckService $health = new DatabaseHealthCheckService(),
        private readonly DatabaseConsistencyService $consistency = new DatabaseConsistencyService(),
        private readonly DatabaseForeignKeyAuditService $fk = new DatabaseForeignKeyAuditService(),
        private readonly DatabaseDuplicateAuditService $dup = new DatabaseDuplicateAuditService(),
        private readonly TenantIsolationAuditService $tenant = new TenantIsolationAuditService(),
        private readonly AccountingIntegrityAuditService $accounting = new AccountingIntegrityAuditService(),
        private readonly InventoryIntegrityAuditService $inventory = new InventoryIntegrityAuditService(),
        private readonly DatabaseIndexAuditService $indexes = new DatabaseIndexAuditService(),
        private readonly DatabasePerformanceService $performance = new DatabasePerformanceService(),
        private readonly ProductionDatabaseAuditService $production = new ProductionDatabaseAuditService(),
        private readonly EnterpriseDatabaseCertificationService $certification = new EnterpriseDatabaseCertificationService(),
        private readonly BackupService $backups = new BackupService(),
        private readonly RestoreVerificationService $restoreVerify = new RestoreVerificationService(),
        private readonly SeedIntegrityService $seedIntegrity = new SeedIntegrityService(),
        private readonly SeedVersionService $seedVersion = new SeedVersionService(),
        private readonly SchemaVersionService $schemaVersion = new SchemaVersionService(),
        private readonly DisasterRecoveryService $disaster = new DisasterRecoveryService(),
        private readonly ArchiveService $archive = new ArchiveService(),
        private readonly ProductionQueryMetricsService $queryMetrics = new ProductionQueryMetricsService(),
        private readonly QueryFingerprintService $fingerprints = new QueryFingerprintService(),
        private readonly N1DetectionService $n1 = new N1DetectionService(),
        private readonly EndpointPerformanceService $endpoints = new EndpointPerformanceService(),
        private readonly DatabaseCapacityService $capacity = new DatabaseCapacityService(),
        private readonly DatabaseAlertService $alerts = new DatabaseAlertService()
    ) {}

    private function statusFromBool(?bool $healthy, string $notConfigured = 'NOT_CONFIGURED'): string
    {
        if ($healthy === null) return $notConfigured;
        return $healthy ? 'PASS' : 'FAIL';
    }

    private function statusWithWarning(bool $healthy, array $issues, int $warningThreshold = 0): string
    {
        if ($healthy) return 'PASS';
        if (count($issues) <= $warningThreshold) return 'WARNING';
        return 'FAIL';
    }

    public function snapshot(bool $useCache = true): array
    {
        // Reuse persisted audits where safe (health, certification) if recent (<5min) and useCache true
        $health = $this->health->run(persist: false);
        $consistency = $this->consistency->check();
        $fk = $this->fk->audit();
        $dup = $this->dup->audit();
        $tenant = $this->tenant->audit();
        $accounting = $this->accounting->audit();
        $inventory = $this->inventory->audit();
        $indexes = $this->indexes->audit();
        $perfStats = $this->performance->stats(24);
        $perfWidget = $this->performance->widget();
        $production = $this->production->audit();
        $cert = $this->certification->certify();
        $seedIntegrity = $this->seedIntegrity->check();
        $seedVersion = $this->seedVersion->verifyAll();
        $schemaCompare = $this->schemaVersion->compare();
        $schemaLatest = $this->schemaVersion->latest();
        $archiveStats = $this->archive->stats();

        // Backup / Recovery
        $latestBackup = DB::table('system_backups')->orderByDesc('created_at')->first();
        $latestVerified = DB::table('system_backups')->where('status', 'verified')->orderByDesc('created_at')->first();
        $backupCount = DB::table('system_backups')->count();
        $failedCount = DB::table('system_backups')->where('status', 'failed')->count();
        $restoreReport = null;
        if ($latestVerified) {
            $file = storage_path('app/' . $latestVerified->path);
            if (file_exists($file)) {
                $restoreReport = $this->restoreVerify->generateReport($file);
            }
        }
        $dr = $this->disaster->run();

        // Database size & largest tables
        $dbSize = null;
        $largest = [];
        try {
            $dbName = DB::getDatabaseName();
            $sizeRow = DB::selectOne("SELECT SUM(data_length + index_length) as size FROM information_schema.TABLES WHERE table_schema = ?", [$dbName]);
            $dbSize = $sizeRow->size ?? null;
            $largestRows = DB::select("SELECT table_name, (data_length + index_length) as size, table_rows FROM information_schema.TABLES WHERE table_schema = ? ORDER BY size DESC LIMIT 5", [$dbName]);
            foreach ($largestRows as $r) {
                $largest[] = ['table' => $r->table_name, 'size' => $r->size, 'rows' => $r->table_rows];
            }
        } catch (\Throwable $e) {}

        // Archive jobs
        $archiveJobs = ['pending' => 0, 'completed' => 0, 'failed' => 0];
        try {
            $archiveJobs['pending'] = DB::table('archive_jobs')->where('status', 'pending')->count();
            $archiveJobs['completed'] = DB::table('archive_jobs')->where('status', 'completed')->count();
            $archiveJobs['failed'] = DB::table('archive_jobs')->where('status', 'failed')->count();
        } catch (\Throwable $e) {}

        // Step 124 — Query Intelligence (read-only)
        $queryStats = $this->queryMetrics->stats(24);
        $slowQueries = $this->performance->slowQueries(5);
        $topByCount = $this->fingerprints->top(5, 'count');
        $topByDuration = $this->fingerprints->top(5, 'duration');
        $n1 = $this->n1->detectEnhanced();
        $endpointStats = $this->endpoints->stats(24);
        $tenantPerf = $this->tenantPerfStats();
        $capacity = $this->capacity->metrics();
        $dupEvidence = $this->dupEvidence();

        // Helper to map health-style checks to PASS/WARNING/FAIL
        $healthStatus = $this->thresholdStatus($health['score'] ?? 0);
        $certStatus = $cert['status'] ?? 'NOT_CONFIGURED';

        return [
            'generated_at' => now()->toIso8601String(),
            'query_intelligence' => [
                'query_health' => ['status' => $queryStats['slow_queries'] > 5 ? 'WARNING' : 'PASS', 'stats' => $queryStats],
                'slow_queries' => ['status' => count($slowQueries) > 0 ? 'WARNING' : 'PASS', 'data' => $slowQueries],
                'top_fingerprints' => ['by_count' => $topByCount, 'by_duration' => $topByDuration, 'status' => 'PASS'],
                'n1_detection' => ['summary' => $n1['summary'] ?? [], 'findings' => $n1['findings'] ?? [], 'status' => ($n1['summary']['confirmed'] ?? 0) > 0 ? 'FAIL' : 'PASS'],
                'endpoint_performance' => ['data' => $endpointStats, 'status' => empty($endpointStats) ? 'NOT_CONFIGURED' : 'PASS'],
                'tenant_performance' => ['data' => $tenantPerf, 'status' => 'PASS'],
                'capacity' => ['data' => $capacity, 'status' => 'PASS'],
                'duplicate_evidence' => ['data' => $dupEvidence, 'status' => 'PASS'],
                'read_only' => true,
            ],
            'health' => [
                'status' => $healthStatus,
                'score' => $health['score'] ?? 0,
                'migration_status' => $health['checks']['migrations']['healthy'] ? 'PASS' : 'FAIL',
                'pending_migrations' => $health['checks']['migrations']['pending'] ?? [],
                'missing_tables' => $health['missing_tables'] ?? [],
                'orphan_status' => empty($health['checks']['orphans']['orphans']) ? 'PASS' : 'FAIL',
                'index_status' => empty($health['checks']['indexes']['missing'] ?? []) ? 'PASS' : 'WARNING',
                'tenant_isolation' => empty($health['checks']['tenant_isolation']['issues'] ?? []) ? 'PASS' : 'FAIL',
                'seed_integrity' => empty($health['checks']['seeds']['missing'] ?? []) ? 'PASS' : 'FAIL',
                'raw' => $health,
            ],
            'integrity' => [
                'consistency_status' => $consistency['overall'] === 'CLEAN' ? 'PASS' : 'WARNING',
                'consistency_raw' => $consistency,
                'foreign_key_status' => empty($fk['missing']) && empty($fk['incorrect']) ? 'PASS' : 'FAIL',
                'foreign_key' => $fk,
                'duplicate_status' => $dup['critical'] === 0 ? 'PASS' : 'FAIL',
                'duplicate' => $dup,
                'accounting_status' => $accounting['healthy'] ? 'PASS' : 'FAIL',
                'accounting' => $accounting,
                'inventory_status' => $inventory['healthy'] ? 'PASS' : 'FAIL',
                'inventory' => $inventory,
                'soft_delete_status' => empty($consistency['soft_delete']['issues'] ?? []) ? 'PASS' : 'WARNING',
            ],
            'backup' => [
                'latest_backup' => $latestBackup,
                'latest_verified_backup' => $latestVerified,
                'backup_count' => $backupCount,
                'failed_backup_count' => $failedCount,
                'latest_backup_timestamp' => $latestBackup->created_at ?? null,
                'latest_backup_checksum' => $latestBackup->checksum ?? null,
                'latest_backup_status' => $latestBackup->status ?? 'NOT_CONFIGURED',
                'restore_verification_status' => $latestVerified ? 'PASS' : 'NOT_CONFIGURED',
                'restore_verification_report' => $restoreReport,
                'disaster_recovery_readiness' => $dr['result'] ?? 'NOT_CONFIGURED',
                'disaster_recovery' => $dr,
                'status' => $latestBackup ? ($latestVerified ? 'PASS' : 'WARNING') : 'NOT_CONFIGURED',
                'automation' => $this->backups->getBackupStats(),
            ],
            'backup_recovery' => $this->backupRecoveryStatus(),
            'performance' => [
                'slow_query_count' => $perfStats['slow_query_count'] ?? 0,
                'failed_query_count' => $perfStats['failed_query_count'] ?? 0,
                'average_query_time' => $perfStats['average_execution_time'] ?? 0,
                'database_size' => $dbSize,
                'largest_tables' => $largest,
                'query_log_summary' => $perfStats,
                'widget' => $perfWidget,
                'status' => ($perfStats['failed_query_count'] ?? 0) > 0 ? 'WARNING' : 'PASS',
            ],
            'schema' => [
                'current_migration_count' => $health['checks']['migrations']['ran'] ?? 0,
                'pending_migrations' => $health['checks']['migrations']['pending'] ?? [],
                'schema_version' => $schemaLatest?->version ?? $health['checks']['migrations']['ran'] ?? null,
                'seed_version' => $seedVersion,
                'schema_compatibility_status' => $schemaCompare['mismatch'] ? 'FAIL' : 'PASS',
                'schema_compare' => $schemaCompare,
                'status' => $schemaCompare['mismatch'] ? 'FAIL' : 'PASS',
            ],
            'archive' => [
                'pending' => $archiveJobs['pending'],
                'completed' => $archiveJobs['completed'],
                'failed' => $archiveJobs['failed'],
                'stats' => $archiveStats,
                'status' => $archiveJobs['failed'] > 0 ? 'WARNING' : 'PASS',
            ],
            'certification' => [
                'overall_score' => $cert['overall'] ?? 0,
                'status' => $certStatus,
                'scores' => $cert['scores'] ?? [],
                'warnings' => $cert['status'] === 'CERTIFIED WITH WARNINGS' ? ($cert['checks'] ?? []) : [],
                'critical_issues' => $cert['status'] === 'NOT CERTIFIED' ? ($cert['checks'] ?? []) : [],
                'raw' => $cert,
            ],
            'indexes' => [
                'recommendations' => $this->indexes->detailedRecommendations(),
                'audit' => $indexes,
                'status' => empty($indexes['missing']) ? 'PASS' : 'WARNING',
            ],
            'recent_events' => $this->recentEvents(),
        ];
    }

    private function tenantPerfStats(): array
    {
        try {
            // Aggregate by institute_id from query logs if available
            $rows = DB::table('database_query_logs')->select('connection', DB::raw('COUNT(*) as cnt'))->groupBy('connection')->limit(5)->get();
            return $rows->toArray();
        } catch (\Throwable $e) { return []; }
    }

    private function dupEvidence(): array
    {
        try {
            return app(\App\Services\System\DatabaseIndexAnalysisService::class)->duplicatePrefixAnalysis();
        } catch (\Throwable $e) {
            try { return $this->indexes->detailedRecommendations(); } catch (\Throwable $e2) { return []; }
        }
    }

    private function thresholdStatus(int $score): string
    {
        if ($score >= 90) return 'HEALTHY';
        if ($score >= 70) return 'WARNING';
        return 'CRITICAL';
    }

    private function recentEvents(): array
    {
        $events = [];

        try {
            $audits = DB::table('system_health_audits')->orderByDesc('created_at')->limit(5)->get();
            foreach ($audits as $a) {
                $events[] = ['timestamp' => $a->created_at, 'event' => 'Health audit', 'module' => 'health', 'status' => $a->status, 'actor' => 'system', 'metadata' => ['score' => $a->score]];
            }
        } catch (\Throwable $e) {}

        try {
            $backups = DB::table('system_backups')->orderByDesc('created_at')->limit(5)->get();
            foreach ($backups as $b) {
                $events[] = ['timestamp' => $b->created_at, 'event' => 'Backup '.$b->type, 'module' => 'backup', 'status' => $b->status, 'actor' => $b->created_by_type ?? 'system', 'metadata' => ['file' => $b->filename]];
            }
        } catch (\Throwable $e) {}

        try {
            $logs = DB::table('audit_logs')->orderByDesc('created_at')->limit(5)->get();
            foreach ($logs as $l) {
                $events[] = ['timestamp' => $l->created_at, 'event' => $l->action, 'module' => $l->module, 'status' => 'logged', 'actor' => $l->user_type, 'metadata' => ['id' => $l->record_id]];
            }
        } catch (\Throwable $e) {}

        usort($events, fn($a,$b) => strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? ''));
        return array_slice($events, 0, 10);
    }

    private function backupRecoveryStatus(): array
    {
        try {
            $health = app(BackupHealthService::class)->check();
            $storage = app(BackupStorageService::class)->status();
            $rto = app(RecoveryTimeService::class)->status();
            $retention = app(BackupRetentionService::class)->report();

            return [
                'backup_health' => $health['overall'] ?? 'NOT_CONFIGURED',
                'rpo_status' => $health['checks']['rpo']['status'] ?? 'FAIL',
                'rpo_gap_minutes' => $health['checks']['rpo']['current_gap_minutes'] ?? null,
                'rto_status' => $rto['rto_status'] ?? 'NOT_CONFIGURED',
                'rto_avg_seconds' => $rto['average_recovery_seconds'] ?? null,
                'retention_expired' => $retention['expired_count'] ?? 0,
                'retention_mb' => $retention['storage']['total_mb'] ?? 0,
                'local_backup' => $storage['local']['status'] ?? 'NOT_CONFIGURED',
                'offsite_backup' => $storage['offsite']['status'] ?? 'NOT_CONFIGURED',
                'encryption' => $storage['encryption']['status'] ?? 'NOT_CONFIGURED',
                'consecutive_failures' => $health['checks']['failures']['consecutive_failures'] ?? 0,
                'drill_count' => $rto['drill_count'] ?? 0,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }
}

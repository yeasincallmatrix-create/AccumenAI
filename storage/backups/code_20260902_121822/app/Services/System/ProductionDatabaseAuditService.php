<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;

/**
 * Step 110 — Final Database Production Audit
 */
class ProductionDatabaseAuditService
{
    public function __construct(
        private readonly DatabaseHealthCheckService $health = new DatabaseHealthCheckService(),
        private readonly DatabaseIndexAuditService $indexes = new DatabaseIndexAuditService(),
        private readonly DataIntegrityService $integrity = new DataIntegrityService(),
        private readonly BackupService $backups = new BackupService(),
        private readonly SeedIntegrityService $seeds = new SeedIntegrityService(),
        private readonly SchemaVersionService $schema = new SchemaVersionService(),
        private readonly DatabasePerformanceService $performance = new DatabasePerformanceService()
    ) {}

    public function audit(): array
    {
        $migrations = $this->health->checkMigrations();
        $tables = $this->health->checkMissingTables();
        $seedCheckRaw = $this->seeds->check();
        $seedCheck = ['healthy' => $seedCheckRaw['healthy'], 'missing' => $seedCheckRaw['missing'], 'results' => $seedCheckRaw];
        $indexAudit = $this->indexes->audit();
        $tenant = $this->health->checkTenantIsolation();
        $orphans = $this->health->checkOrphans();
        $integrity = $this->integrity->check();
        $schema = $this->schema->compare();
        $perf = $this->performance->stats(24);

        // Backups
        $backupCount = DB::table('system_backups')->count();
        $latestBackup = DB::table('system_backups')->orderByDesc('created_at')->first();
        $backupHealthy = $backupCount > 0 && $latestBackup && $latestBackup->status !== 'failed';

        // Restore capability: at least one verified backup
        $verifiedBackups = DB::table('system_backups')->where('status', 'verified')->count();
        $restoreHealthy = $verifiedBackups > 0;

        // Scores per category
        $scores = [
            'integrity' => $this->scoreIntegrity($integrity, $orphans, $tenant),
            'backup' => $backupHealthy ? 100 : 0,
            'restore' => $restoreHealthy ? 100 : 50,
            'security' => $this->scoreSecurity($tenant, $orphans),
            'performance' => $this->scorePerformance($perf),
            'migrations' => $migrations['healthy'] ? 100 : 0,
            'seeds' => $seedCheck['healthy'] ? 100 : 0,
            'indexes' => empty($indexAudit['missing']) ? 100 : max(0, 100 - count($indexAudit['missing']) * 5),
            'schema' => ! $schema['mismatch'] ? 100 : 0,
        ];

        $overall = (int)round(array_sum($scores) / count($scores));

        return [
            'checks' => [
                'migrations' => $migrations,
                'missing_tables' => $tables,
                'seeds' => $seedCheck,
                'indexes' => $indexAudit,
                'tenant_isolation' => $tenant,
                'orphans' => $orphans,
                'integrity' => $integrity,
                'schema' => $schema,
                'performance' => $perf,
                'backups' => ['count' => $backupCount, 'latest' => $latestBackup, 'healthy' => $backupHealthy],
                'restore' => ['verified' => $verifiedBackups, 'healthy' => $restoreHealthy],
            ],
            'scores' => $scores,
            'overall' => $overall,
            'status' => $overall >= 90 ? 'READY' : ($overall >= 70 ? 'WARNING' : 'NOT READY'),
        ];
    }

    private function scoreIntegrity(array $integrity, array $orphans, array $tenant): int
    {
        $issues = ($integrity['total_issues'] ?? 0) + count($orphans['orphans'] ?? []) + count($tenant['issues'] ?? []);
        if ($issues === 0) return 100;
        if ($issues < 5) return 90;
        if ($issues < 20) return 70;
        return 50;
    }

    private function scoreSecurity(array $tenant, array $orphans): int
    {
        $issues = count($tenant['issues'] ?? []) + count($orphans['orphans'] ?? []);
        return $issues === 0 ? 100 : 80;
    }

    private function scorePerformance(array $perf): int
    {
        $slow = $perf['slow_query_count'] ?? 0;
        $failed = $perf['failed_query_count'] ?? 0;
        if ($failed > 0) return 70;
        if ($slow > 10) return 85;
        if ($slow > 0) return 95;
        return 100;
    }

    public function score(): int
    {
        return $this->audit()['overall'];
    }
}

<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\System\AccountingIntegrityAuditService;
use App\Services\System\BackupHealthService;
use App\Services\System\BackupService;
use App\Services\System\DatabaseAlertService;
use App\Services\System\DatabaseCapacityService;
use App\Services\System\DatabaseConsistencyService;
use App\Services\System\DatabaseDuplicateAuditService;
use App\Services\System\DatabaseForeignKeyAuditService;
use App\Services\System\DatabaseHealthCheckService;
use App\Services\System\DatabaseIndexAnalysisService;
use App\Services\System\DatabaseIndexAuditService;
use App\Services\System\DatabaseMonitoringService;
use App\Services\System\DatabasePerformanceBaselineService;
use App\Services\System\DatabasePerformanceService;
use App\Services\System\DisasterRecoveryService;
use App\Services\System\EndpointPerformanceService;
use App\Services\System\EnterpriseDatabaseCertificationService;
use App\Services\System\InventoryIntegrityAuditService;
use App\Services\System\N1DetectionService;
use App\Services\System\ProductionQueryMetricsService;
use App\Services\System\TenantIsolationAuditService;

/**
 * Step 127 — Super Admin Database Control Center.
 *
 * Aggregates ALL database services into a single unified view.
 * READ-ONLY with respect to business data.
 */
class DatabaseControlCenterController extends Controller
{
    /**
     * GET /super-admin/database/control-center
     */
    public function index()
    {
        // Core health & certification
        $monitoring = app(DatabaseMonitoringService::class)->snapshot(useCache: true);
        $cert = app(EnterpriseDatabaseCertificationService::class)->certify();
        $health = app(DatabaseHealthCheckService::class)->run(persist: false);

        // Backup & recovery
        $backupStats = app(BackupService::class)->getBackupStats();
        $backupHealth = app(BackupHealthService::class)->check();

        // Performance
        $perf = app(DatabasePerformanceService::class)->stats(24);
        $queryMetrics = app(ProductionQueryMetricsService::class)->stats(24);
        $slowQueries = app(DatabasePerformanceService::class)->slowQueries(10);
        $baseline = app(DatabasePerformanceBaselineService::class)->baseline();

        // Indexes
        $indexAudit = app(DatabaseIndexAuditService::class)->audit();
        $indexRecs = app(DatabaseIndexAuditService::class)->detailedRecommendations();
        $dupIndex = app(DatabaseIndexAnalysisService::class)->duplicatePrefixAnalysis();
        $explain = app(DatabaseIndexAnalysisService::class)->explainAnalysis();

        // N+1
        $n1 = app(N1DetectionService::class)->detectEnhanced();

        // Endpoint & tenant
        $endpointPerf = app(EndpointPerformanceService::class)->stats(24);

        // Capacity
        $capacity = app(DatabaseCapacityService::class)->metrics();

        // Integrity
        $tenant = app(TenantIsolationAuditService::class)->audit();
        $fk = app(DatabaseForeignKeyAuditService::class)->audit();
        $dup = app(DatabaseDuplicateAuditService::class)->audit();
        $consistency = app(DatabaseConsistencyService::class)->check();
        $accounting = app(AccountingIntegrityAuditService::class)->audit();
        $inventory = app(InventoryIntegrityAuditService::class)->audit();

        // Alerts
        $alerts = app(DatabaseAlertService::class)->evaluate();

        return view('super-admin.database.control-center', [
            'monitoring' => $monitoring,
            'cert' => $cert,
            'health' => $health,
            'backup_stats' => $backupStats,
            'backup_health' => $backupHealth,
            'perf' => $perf,
            'query_metrics' => $queryMetrics,
            'slow_queries' => $slowQueries,
            'baseline' => $baseline,
            'index_audit' => $indexAudit,
            'index_recs' => $indexRecs,
            'dup_index' => $dupIndex,
            'explain' => $explain,
            'n1' => $n1,
            'endpoint_perf' => $endpointPerf,
            'capacity' => $capacity,
            'tenant' => $tenant,
            'fk' => $fk,
            'dup' => $dup,
            'consistency' => $consistency,
            'accounting' => $accounting,
            'inventory' => $inventory,
            'alerts' => $alerts,
        ]);
    }

    /**
     * GET /super-admin/database/control-center/json — JSON status
     */
    public function json()
    {
        $monitoring = app(DatabaseMonitoringService::class)->snapshot(useCache: false);
        $cert = app(EnterpriseDatabaseCertificationService::class)->certify();
        $perf = app(ProductionQueryMetricsService::class)->stats(24);
        $capacity = app(DatabaseCapacityService::class)->metrics();
        $alerts = app(DatabaseAlertService::class)->evaluate();

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'certification' => ['score' => $cert['overall_score'] ?? 0, 'status' => $cert['status'] ?? 'UNKNOWN'],
            'health' => ['score' => $monitoring['health']['score'] ?? 0, 'status' => $monitoring['health']['status'] ?? 'UNKNOWN'],
            'query_metrics' => $perf,
            'capacity' => ['database_size' => $capacity['database_size'] ?? 0],
            'alerts' => $alerts,
        ]);
    }
}

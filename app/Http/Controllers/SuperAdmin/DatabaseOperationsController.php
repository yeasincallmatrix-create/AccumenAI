<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SystemBackup;
use App\Services\System\BackupHealthService;
use App\Services\System\BackupInventoryService;
use App\Services\System\BackupRetentionService;
use App\Services\System\BackupService;
use App\Services\System\BackupStorageService;
use App\Services\System\DatabaseConsistencyService;
use App\Services\System\DatabaseDuplicateAuditService;
use App\Services\System\DatabaseForeignKeyAuditService;
use App\Services\System\DatabaseHealthCheckService;
use App\Services\System\DatabaseIndexAuditService;
use App\Services\System\DatabaseMonitoringService;
use App\Services\System\DatabasePerformanceService;
use App\Services\System\DatabaseIndexAnalysisService;
use App\Services\System\DisasterRecoveryService;
use App\Services\System\EnterpriseDatabaseCertificationService;
use App\Services\System\InventoryIntegrityAuditService;
use App\Services\System\N1DetectionService;
use App\Services\System\ProductionDatabaseAuditService;
use App\Services\System\RecoveryTimeService;
use App\Services\System\RestoreVerificationService;
use App\Services\System\SeedIntegrityService;
use App\Services\System\SeedVersionService;
use App\Services\System\SchemaVersionService;
use App\Services\System\TenantIsolationAuditService;
use App\Services\System\AccountingIntegrityAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Step 126 — Super Admin Database Operations & Recovery Dashboard
 * All methods are READ-ONLY except backup creation (which has safety checks).
 */
class DatabaseOperationsController extends Controller
{
    /**
     * GET /super-admin/database — Main Database Dashboard
     */
    public function dashboard(DatabaseMonitoringService $monitoring)
    {
        $data = $monitoring->snapshot(useCache: true);
        $cert = app(EnterpriseDatabaseCertificationService::class)->certify();
        $backupHealth = app(BackupHealthService::class)->check();
        $rto = app(RecoveryTimeService::class)->status();
        $storage = app(BackupStorageService::class)->status();

        return view('super-admin.database.dashboard', [
            'monitoring' => $data,
            'cert' => $cert,
            'backup_health' => $backupHealth,
            'rto' => $rto,
            'storage' => $storage,
        ]);
    }

    /**
     * POST /super-admin/database/refresh
     */
    public function refresh(DatabaseMonitoringService $monitoring)
    {
        $monitoring->snapshot(useCache: false);
        return redirect()->route('super-admin.database.dashboard')
            ->with('status', 'Dashboard refreshed at ' . now()->toDateTimeString());
    }

    /**
     * GET /super-admin/database/backups — Backups & Recovery Page
     */
    public function backups()
    {
        $service = app(BackupService::class);
        $inventory = app(BackupInventoryService::class)->inventory();
        $retention = app(BackupRetentionService::class)->report();
        $backupHealth = app(BackupHealthService::class)->check();
        $storage = app(BackupStorageService::class)->status();
        $backups = $service->listBackups(50);
        $stats = $service->getBackupStats();

        return view('super-admin.database.backups', [
            'backups' => $backups,
            'stats' => $stats,
            'inventory' => $inventory,
            'retention' => $retention,
            'backup_health' => $backupHealth,
            'storage' => $storage,
        ]);
    }

    /**
     * POST /super-admin/database/backups/create — Create backup (protected action)
     */
    public function createBackup(Request $request)
    {
        $request->validate(['type' => 'required|in:daily,weekly,manual']);
        $type = $request->input('type', 'manual');
        $service = app(BackupService::class);

        if (! $service->acquireLock()) {
            return back()->with('error', 'Another backup is currently running.');
        }

        try {
            $backup = $service->create($type, auth('platform_admin')->id(), 'platform_admin');
            return back()->with('status', "Backup created: {$backup->filename} ({$backup->status})");
        } catch (\Throwable $e) {
            return back()->with('error', 'Backup failed: ' . $e->getMessage());
        } finally {
            $service->releaseLock();
        }
    }

    /**
     * POST /super-admin/database/backups/{id}/verify — Verify a backup
     */
    public function verifyBackup(SystemBackup $backup)
    {
        $service = app(BackupService::class);
        $verified = $service->verify($backup);
        return back()->with($verified ? 'status' : 'error',
            $verified ? "Backup {$backup->filename} verified successfully." : "Verification failed for {$backup->filename}.");
    }

    /**
     * POST /super-admin/database/backups/retention/execute — Execute retention (protected)
     */
    public function executeRetention()
    {
        $service = app(BackupRetentionService::class);
        $result = $service->deleteExpired();
        return back()->with('status', "Retention executed: {$result['deleted_count']} expired backups removed.");
    }

    /**
     * GET /super-admin/database/recovery — Recovery Dashboard
     */
    public function recovery()
    {
        $backupHealth = app(BackupHealthService::class)->check();
        $rto = app(RecoveryTimeService::class)->status();
        $storage = app(BackupStorageService::class)->status();
        $retention = app(BackupRetentionService::class)->report();

        return view('super-admin.database.recovery', [
            'backup_health' => $backupHealth,
            'rto' => $rto,
            'storage' => $storage,
            'retention' => $retention,
        ]);
    }

    /**
     * POST /super-admin/database/recovery/drill — Run restore drill (protected)
     */
    public function runDrill()
    {
        $dr = app(DisasterRecoveryService::class);
        $result = $dr->restoreDrill();
        $isFailed = str_contains($result['result'] ?? '', 'FAILED');
        $totalSec = $result['total_seconds'] ?? '—';
        $statusKey = $isFailed ? 'error' : 'status';
        return back()->with($statusKey, "Restore drill: {$result['result']} ({$totalSec}s)");
    }

    /**
     * GET /super-admin/database/health — Database Health Page
     */
    public function health()
    {
        $health = app(DatabaseHealthCheckService::class)->run(persist: false);
        $seeds = app(SeedIntegrityService::class)->check();
        $seedVersion = app(SeedVersionService::class)->verifyAll();
        $consistency = app(DatabaseConsistencyService::class)->check();
        $fk = app(DatabaseForeignKeyAuditService::class)->audit();
        $dup = app(DatabaseDuplicateAuditService::class)->audit();
        $tenant = app(TenantIsolationAuditService::class)->audit();
        $schemaCompare = app(SchemaVersionService::class)->compare();

        return view('super-admin.database.health', [
            'health' => $health,
            'seeds' => $seeds,
            'seed_version' => $seedVersion,
            'consistency' => $consistency,
            'fk' => $fk,
            'dup' => $dup,
            'tenant' => $tenant,
            'schema_compare' => $schemaCompare,
        ]);
    }

    /**
     * GET /super-admin/database/performance — Performance Page
     */
    public function performance()
    {
        $perf = app(DatabasePerformanceService::class)->stats(24);
        $slowQueries = app(DatabasePerformanceService::class)->slowQueries(10);
        $indexAudit = app(DatabaseIndexAuditService::class)->audit();
        $indexRecs = app(DatabaseIndexAuditService::class)->detailedRecommendations();
        $n1 = app(N1DetectionService::class)->detectEnhanced();
        $baseline = app(\App\Services\System\DatabasePerformanceBaselineService::class)->baseline();
        $dupIndex = app(DatabaseIndexAnalysisService::class)->duplicatePrefixAnalysis();

        return view('super-admin.database.performance', [
            'perf' => $perf,
            'slow_queries' => $slowQueries,
            'index_audit' => $indexAudit,
            'index_recs' => $indexRecs,
            'n1' => $n1,
            'baseline' => $baseline,
            'dup_index' => $dupIndex,
        ]);
    }

    /**
     * GET /super-admin/database/integrity — Integrity & Security Page
     */
    public function integrity()
    {
        $tenant = app(TenantIsolationAuditService::class)->audit();
        $fk = app(DatabaseForeignKeyAuditService::class)->audit();
        $dup = app(DatabaseDuplicateAuditService::class)->audit();
        $consistency = app(DatabaseConsistencyService::class)->check();
        $accounting = app(AccountingIntegrityAuditService::class)->audit();
        $inventory = app(InventoryIntegrityAuditService::class)->audit();

        return view('super-admin.database.integrity', [
            'tenant' => $tenant,
            'fk' => $fk,
            'dup' => $dup,
            'consistency' => $consistency,
            'accounting' => $accounting,
            'inventory' => $inventory,
        ]);
    }

    /**
     * GET /super-admin/database/certification — Certification Scorecard
     */
    public function certification()
    {
        $cert = app(EnterpriseDatabaseCertificationService::class)->certify();
        $backupStorage = app(BackupStorageService::class)->status();
        $rto = app(RecoveryTimeService::class)->status();
        $backupHealth = app(BackupHealthService::class)->check();

        return view('super-admin.database.certification', [
            'cert' => $cert,
            'backup_storage' => $backupStorage,
            'rto' => $rto,
            'backup_health' => $backupHealth,
        ]);
    }

    /**
     * GET /super-admin/database/audit — Audit Logs
     */
    public function audit()
    {
        $logs = DB::table('audit_logs')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return view('super-admin.database.audit', [
            'logs' => $logs,
        ]);
    }

    /**
     * GET /super-admin/database/status — JSON status endpoint (for AJAX refresh)
     */
    public function status()
    {
        $monitoring = app(DatabaseMonitoringService::class)->snapshot(useCache: false);
        return response()->json([
            'generated_at' => $monitoring['generated_at'],
            'health_status' => $monitoring['health']['status'],
            'health_score' => $monitoring['health']['score'],
            'cert_status' => $monitoring['certification']['status'],
            'cert_score' => $monitoring['certification']['overall_score'],
            'backup_status' => $monitoring['backup']['status'],
            'rpo_status' => $monitoring['backup_recovery']['rpo_status'] ?? 'FAIL',
            'rto_status' => $monitoring['backup_recovery']['rto_status'] ?? 'NOT_CONFIGURED',
        ]);
    }
}

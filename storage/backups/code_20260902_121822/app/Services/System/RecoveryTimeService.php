<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;

/**
 * Step 125-F — Recovery Time Objective (RTO) Service
 * Tracks historical restore/disaster-test duration and RTO compliance.
 * Do NOT perform a real production restore automatically.
 */
class RecoveryTimeService
{
    private const TABLE = 'system_recovery_times';

    public function __construct()
    {
        $this->ensureTable();
    }

    public function record(array $metrics): void
    {
        DB::table(self::TABLE)->insert([
            'backup_id' => $metrics['backup_id'] ?? null,
            'backup_preparation_ms' => $metrics['backup_preparation_ms'] ?? 0,
            'verification_ms' => $metrics['verification_ms'] ?? 0,
            'schema_validation_ms' => $metrics['schema_validation_ms'] ?? 0,
            'simulated_restore_ms' => $metrics['simulated_restore_ms'] ?? 0,
            'total_ms' => $metrics['total_ms'] ?? 0,
            'temp_database' => $metrics['temp_database'] ?? null,
            'status' => $metrics['status'] ?? 'completed',
            'created_at' => now(),
        ]);
    }

    public function status(): array
    {
        $targetRto = config('backup.rto.target_minutes', 60);
        $warningRto = config('backup.rto.warning_minutes', 45);
        $criticalRto = config('backup.rto.critical_minutes', 120);

        $records = DB::table(self::TABLE)->orderByDesc('created_at')->limit(50)->get();

        if ($records->isEmpty()) {
            return [
                'generated_at' => now()->toIso8601String(),
                'status' => 'NOT_CONFIGURED',
                'average_recovery_ms' => null,
                'fastest_recovery_ms' => null,
                'slowest_recovery_ms' => null,
                'latest_recovery' => null,
                'target_rto_minutes' => $targetRto,
                'rto_status' => 'NOT_CONFIGURED',
                'drill_count' => 0,
                'message' => 'No recovery drills recorded',
            ];
        }

        $totalMs = $records->sum('total_ms');
        $avgMs = $totalMs / $records->count();
        $fastest = $records->min('total_ms');
        $slowest = $records->max('total_ms');
        $latest = $records->first();
        $drillCount = $records->count();

        $rtoStatus = 'PASS';
        if ($slowest > $criticalRto * 60 * 1000) $rtoStatus = 'CRITICAL';
        elseif ($avgMs > $targetRto * 60 * 1000) $rtoStatus = 'WARNING';
        elseif ($slowest > $warningRto * 60 * 1000) $rtoStatus = 'WARNING';

        return [
            'generated_at' => now()->toIso8601String(),
            'status' => 'ACTIVE',
            'average_recovery_ms' => round($avgMs),
            'average_recovery_seconds' => round($avgMs / 1000, 1),
            'fastest_recovery_ms' => $fastest,
            'fastest_recovery_seconds' => round($fastest / 1000, 1),
            'slowest_recovery_ms' => $slowest,
            'slowest_recovery_seconds' => round($slowest / 1000, 1),
            'latest_recovery' => [
                'date' => $latest->created_at,
                'total_ms' => $latest->total_ms,
                'status' => $latest->status,
                'backup_preparation_ms' => $latest->backup_preparation_ms,
                'verification_ms' => $latest->verification_ms,
                'schema_validation_ms' => $latest->schema_validation_ms,
                'simulated_restore_ms' => $latest->simulated_restore_ms,
            ],
            'target_rto_minutes' => $targetRto,
            'target_rto_ms' => $targetRto * 60 * 1000,
            'rto_status' => $rtoStatus,
            'drill_count' => $drillCount,
            'message' => "Average: {$avgMs}ms, Target: {$targetRto}min",
        ];
    }

    private function ensureTable(): void
    {
        if (! DB::getSchemaBuilder()->hasTable(self::TABLE)) {
            DB::statement("CREATE TABLE `system_recovery_times` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `backup_id` BIGINT UNSIGNED NULL,
                `backup_preparation_ms` INT UNSIGNED DEFAULT 0,
                `verification_ms` INT UNSIGNED DEFAULT 0,
                `schema_validation_ms` INT UNSIGNED DEFAULT 0,
                `simulated_restore_ms` INT UNSIGNED DEFAULT 0,
                `total_ms` INT UNSIGNED DEFAULT 0,
                `temp_database` VARCHAR(255) NULL,
                `status` VARCHAR(50) DEFAULT 'completed',
                `created_at` TIMESTAMP NULL
            )");
        }
    }
}

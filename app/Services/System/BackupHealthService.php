<?php

namespace App\Services\System;

use App\Models\SystemBackup;
use Illuminate\Support\Facades\DB;

/**
 * Step 125-D + 125-E — Backup Health & RPO Service
 * Checks backup freshness, RPO compliance, storage, verification failures, recovery-point availability.
 */
class BackupHealthService
{
    public function check(): array
    {
        $backupService = app(BackupService::class);
        $retentionService = app(BackupRetentionService::class);

        $latestDaily = $backupService->latestSuccessful('daily');
        $latestWeekly = $backupService->latestSuccessful('weekly');
        $latestVerified = $backupService->latestVerified();
        $latestFailed = $backupService->latestFailed();
        $retention = $retentionService->report();

        $checks = [];

        // Latest daily backup freshness
        $dailyAgeHours = $latestDaily ? now()->diffInHours($latestDaily->created_at) : null;
        $dailyThreshold = config('backup.notification_threshold_hours', 48);
        $checks['daily_backup'] = [
            'status' => $dailyAgeHours === null ? 'FAIL' : ($dailyAgeHours <= $dailyThreshold ? 'PASS' : 'WARNING'),
            'latest' => $latestDaily?->filename,
            'age_hours' => $dailyAgeHours,
            'threshold_hours' => $dailyThreshold,
        ];

        // Latest weekly backup freshness
        $weeklyAgeDays = $latestWeekly ? now()->diffInDays($latestWeekly->created_at) : null;
        $checks['weekly_backup'] = [
            'status' => $latestWeekly ? 'PASS' : 'WARNING',
            'latest' => $latestWeekly?->filename,
            'age_days' => $weeklyAgeDays,
        ];

        // Latest verified backup
        $verifiedAgeHours = $latestVerified ? now()->diffInHours($latestVerified->created_at) : null;
        $checks['latest_verified'] = [
            'status' => $latestVerified ? 'PASS' : 'FAIL',
            'latest' => $latestVerified?->filename,
            'age_hours' => $verifiedAgeHours,
            'checksum' => $latestVerified?->checksum,
        ];

        // Failed backups
        $failedCount = SystemBackup::where('status', 'failed')->count();
        $consecutiveFailures = $this->consecutiveFailures();
        $failureThreshold = config('backup.failure.consecutive_failure_alert_threshold', 3);
        $checks['failures'] = [
            'status' => $consecutiveFailures >= $failureThreshold ? 'FAIL' : ($failedCount > 0 ? 'WARNING' : 'PASS'),
            'total_failed' => $failedCount,
            'consecutive_failures' => $consecutiveFailures,
            'threshold' => $failureThreshold,
        ];

        // Verification failures
        $unverifiedCount = SystemBackup::whereNotIn('status', ['verified', 'failed'])->count();
        $checks['verification'] = [
            'status' => $unverifiedCount > 0 ? 'WARNING' : 'PASS',
            'unverified_count' => $unverifiedCount,
        ];

        // Storage usage
        $maxStorage = config('backup.retention.max_storage_bytes', 10 * 1024 * 1024 * 1024);
        $checks['storage'] = [
            'status' => $retention['storage']['total_bytes'] <= $maxStorage ? 'PASS' : 'WARNING',
            'total_bytes' => $retention['storage']['total_bytes'],
            'total_mb' => $retention['storage']['total_mb'],
            'max_bytes' => $maxStorage,
            'files_missing' => $retention['storage']['files_missing'],
        ];

        // Retention compliance
        $checks['retention'] = [
            'status' => $retention['expired_count'] === 0 ? 'PASS' : 'WARNING',
            'expired_count' => $retention['expired_count'],
            'protected_count' => $retention['protected_backups'],
        ];

        // RPO — Step 125-E
        $rpoStatus = $this->calculateRPO($latestVerified);
        $checks['rpo'] = $rpoStatus;

        // Recovery-point availability
        $checks['recovery_point'] = [
            'status' => $latestVerified ? 'PASS' : 'FAIL',
            'available' => $latestVerified !== null,
            'backup_id' => $latestVerified?->id,
            'filename' => $latestVerified?->filename,
        ];

        // Overall
        $statuses = array_column($checks, 'status');
        $overall = 'PASS';
        if (in_array('FAIL', $statuses)) $overall = 'FAIL';
        elseif (in_array('WARNING', $statuses)) $overall = 'WARNING';

        return [
            'generated_at' => now()->toIso8601String(),
            'overall' => $overall,
            'checks' => $checks,
        ];
    }

    private function calculateRPO(?SystemBackup $latestVerified): array
    {
        $target = config('backup.rpo.target_minutes', 1440);
        $warning = config('backup.rpo.warning_minutes', 1080);
        $critical = config('backup.rpo.critical_minutes', 2880);

        if (! $latestVerified) {
            return [
                'status' => 'FAIL',
                'current_gap_minutes' => null,
                'target_minutes' => $target,
                'message' => 'No verified backup exists — RPO cannot be met',
            ];
        }

        $gapMinutes = (int) now()->diffInMinutes($latestVerified->created_at);
        $status = 'PASS';
        if ($gapMinutes > $critical) $status = 'CRITICAL';
        elseif ($gapMinutes > $target) $status = 'FAIL';
        elseif ($gapMinutes > $warning) $status = 'WARNING';

        return [
            'status' => $status,
            'current_gap_minutes' => $gapMinutes,
            'target_minutes' => $target,
            'warning_minutes' => $warning,
            'critical_minutes' => $critical,
            'last_verified_backup' => $latestVerified->filename,
            'last_verified_at' => $latestVerified->created_at?->toIso8601String(),
            'message' => "RPO gap: {$gapMinutes} min (target: {$target} min)",
        ];
    }

    private function consecutiveFailures(): int
    {
        $backups = SystemBackup::orderByDesc('created_at')->limit(20)->get();
        $count = 0;
        foreach ($backups as $backup) {
            if ($backup->status === 'failed') {
                $count++;
            } else {
                break;
            }
        }
        return $count;
    }
}

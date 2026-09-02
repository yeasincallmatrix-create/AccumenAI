<?php

namespace App\Services\System;

use App\Models\SystemBackup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Step 125-B — Backup Rotation & Retention Service (READ-ONLY by default)
 * Identifies expired backups, calculates storage, shows deletion candidates.
 * NEVER deletes protected/manual backups automatically.
 */
class BackupRetentionService
{
    public function report(): array
    {
        $policy = config('backup.retention', []);
        $backups = SystemBackup::orderByDesc('created_at')->get();

        $retentionClasses = $this->classifyBackups($backups, $policy);
        $expired = $this->findExpired($retentionClasses, $policy);
        $storage = $this->calculateStorage($backups);
        $protectedCount = collect($retentionClasses)->where('protected', true)->count();

        return [
            'generated_at' => now()->toIso8601String(),
            'policy' => $policy,
            'total_backups' => $backups->count(),
            'protected_backups' => $protectedCount,
            'expired_backups' => $expired,
            'expired_count' => count($expired),
            'storage' => $storage,
            'deletion_candidates' => $expired,
            'max_storage_bytes' => $policy['max_storage_bytes'] ?? 10 * 1024 * 1024 * 1024,
            'storage_within_limit' => ($storage['total_bytes'] ?? 0) <= ($policy['max_storage_bytes'] ?? 10 * 1024 * 1024 * 1024),
        ];
    }

    public function deleteExpired(): array
    {
        $report = $this->report();
        $deleted = [];
        $maxRetries = config('backup.failure.max_retries', 3);

        foreach ($report['deletion_candidates'] as $candidate) {
            $backup = SystemBackup::find($candidate['id']);
            if (! $backup) continue;

            $fullPath = storage_path('app/' . $backup->path);
            $fileDeleted = false;

            if ($backup->path && file_exists($fullPath)) {
                $fileDeleted = @unlink($fullPath);
            }

            $backup->update(['metadata' => array_merge($backup->metadata ?? [], [
                'retention_deleted_at' => now()->toIso8601String(),
                'retention_deleted_file' => $fileDeleted,
            ])]);

            $deleted[] = [
                'id' => $backup->id,
                'filename' => $backup->filename,
                'type' => $backup->type,
                'created_at' => $backup->created_at?->toIso8601String(),
                'file_deleted' => $fileDeleted,
            ];
        }

        return [
            'deleted_count' => count($deleted),
            'deleted' => $deleted,
        ];
    }

    private function classifyBackups($backups, array $policy): array
    {
        $classified = [];

        foreach ($backups as $backup) {
            $class = $this->classifyOne($backup, $policy);
            $classified[] = $class;
        }

        return $classified;
    }

    private function classifyOne(SystemBackup $backup, array $policy): array
    {
        $type = $backup->type;
        $createdAt = $backup->created_at;
        $protected = false;
        $retentionClass = 'unknown';
        $retentionDays = null;

        // Manual backups are always protected unless manual retention says otherwise
        if ($type === 'manual' || $type === 'pre_restore' || $type === 'pre_orphan_cleanup' || $type === 'pre_destructive') {
            $manualConfig = $policy['manual'] ?? ['retain_indefinitely' => true];
            $protected = $manualConfig['retain_indefinitely'] ?? true;
            $retentionClass = 'manual';
        }
        // Pre-operation backups
        elseif (str_starts_with($type, 'pre_')) {
            $preOpConfig = $policy['pre_operation'] ?? ['retain_days' => 30];
            $retentionDays = $preOpConfig['retain_days'] ?? 30;
            $retentionClass = 'pre_operation';
            $protected = false;
        }
        // Daily backups
        elseif ($type === 'daily') {
            $dailyConfig = $policy['daily'] ?? ['retain_days' => 14];
            $retentionDays = $dailyConfig['retain_days'] ?? 14;
            $retentionClass = 'daily';
            $protected = false;
        }
        // Weekly backups
        elseif ($type === 'weekly') {
            $weeklyConfig = $policy['weekly'] ?? ['retain_weeks' => 8];
            $retentionDays = ($weeklyConfig['retain_weeks'] ?? 8) * 7;
            $retentionClass = 'weekly';
            $protected = false;
        }
        // Monthly backups
        elseif ($type === 'monthly') {
            $monthlyConfig = $policy['monthly'] ?? ['retain_months' => 12];
            $retentionDays = ($monthlyConfig['retain_months'] ?? 12) * 30;
            $retentionClass = 'monthly';
            $protected = false;
        }
        // Failed backups — retain 7 days
        elseif ($backup->status === 'failed') {
            $retentionDays = 7;
            $retentionClass = 'failed';
            $protected = false;
        }

        $expired = false;
        $expiresAt = null;
        if ($retentionDays !== null && $createdAt) {
            $expiresAt = $createdAt->copy()->addDays($retentionDays);
            $expired = now()->greaterThan($expiresAt);
        }

        return [
            'id' => $backup->id,
            'filename' => $backup->filename,
            'type' => $type,
            'status' => $backup->status,
            'created_at' => $createdAt?->toIso8601String(),
            'size_bytes' => $backup->size_bytes,
            'retention_class' => $retentionClass,
            'protected' => $protected,
            'retention_days' => $retentionDays,
            'expires_at' => $expiresAt?->toIso8601String(),
            'expired' => $expired,
        ];
    }

    private function findExpired(array $classified, array $policy): array
    {
        return array_values(array_filter($classified, fn($c) => $c['expired'] && ! $c['protected']));
    }

    private function calculateStorage($backups): array
    {
        $totalBytes = 0;
        $verifiedBytes = 0;
        $failedBytes = 0;
        $filesFound = 0;
        $filesMissing = 0;

        foreach ($backups as $backup) {
            $totalBytes += $backup->size_bytes ?? 0;
            if ($backup->status === 'verified') $verifiedBytes += $backup->size_bytes ?? 0;
            if ($backup->status === 'failed') $failedBytes += $backup->size_bytes ?? 0;

            if ($backup->path) {
                $fullPath = storage_path('app/' . $backup->path);
                if (file_exists($fullPath)) {
                    $filesFound++;
                } else {
                    $filesMissing++;
                }
            }
        }

        return [
            'total_bytes' => $totalBytes,
            'total_mb' => round($totalBytes / 1024 / 1024, 2),
            'verified_bytes' => $verifiedBytes,
            'failed_bytes' => $failedBytes,
            'files_found' => $filesFound,
            'files_missing' => $filesMissing,
        ];
    }
}

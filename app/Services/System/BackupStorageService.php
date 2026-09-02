<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Storage;

/**
 * Step 125-H + 125-I — Backup Storage Service
 * Off-site backup abstraction + encryption readiness.
 * Uses a disk-based interface so providers can be swapped later.
 * Do not require a cloud provider connection if none exists.
 */
class BackupStorageService
{
    public function status(): array
    {
        $offsiteConfig = config('backup.offsite', []);
        $encryptionConfig = config('backup.encryption', []);

        // Off-site status
        $offsiteEnabled = $offsiteConfig['enabled'] ?? false;
        $offsiteDisk = $offsiteConfig['disk'] ?? null;
        $offsiteAccessible = false;
        $offsiteFileCount = 0;

        if ($offsiteEnabled && $offsiteDisk) {
            try {
                $disk = Storage::disk($offsiteDisk);
                $offsiteAccessible = method_exists($disk, 'files') || method_exists($disk, 'allFiles');
                if ($offsiteAccessible) {
                    $offsitePath = $offsiteConfig['path'] ?? 'backups-offsite';
                    $offsiteFileCount = count($disk->files($offsitePath));
                }
            } catch (\Throwable $e) {
                $offsiteAccessible = false;
            }
        }

        // Encryption status
        $encryptionEnabled = $encryptionConfig['enabled'] ?? false;
        $encryptionKeyEnv = $encryptionConfig['key_env'] ?? 'BACKUP_ENCRYPTION_KEY';
        $encryptionKeySet = false;

        if ($encryptionEnabled) {
            $encryptionKeySet = ! empty(getenv($encryptionKeyEnv));
        }

        // Local backup status
        $localPath = config('backup.path', 'backups');
        $localFiles = 0;
        $localSize = 0;
        try {
            $files = Storage::disk('local')->files($localPath);
            $localFiles = count($files);
            foreach ($files as $file) {
                $localSize += Storage::disk('local')->size($file);
            }
        } catch (\Throwable $e) {}

        return [
            'generated_at' => now()->toIso8601String(),
            'local' => [
                'status' => $localFiles > 0 ? 'PASS' : 'WARNING',
                'disk' => 'local',
                'path' => $localPath,
                'file_count' => $localFiles,
                'total_bytes' => $localSize,
                'total_mb' => round($localSize / 1024 / 1024, 2),
            ],
            'offsite' => [
                'status' => $offsiteEnabled ? ($offsiteAccessible ? 'PASS' : 'FAIL') : 'NOT_CONFIGURED',
                'enabled' => $offsiteEnabled,
                'disk' => $offsiteDisk,
                'path' => $offsiteConfig['path'] ?? null,
                'accessible' => $offsiteAccessible,
                'file_count' => $offsiteFileCount,
            ],
            'encryption' => [
                'status' => $encryptionEnabled ? ($encryptionKeySet ? 'ACTIVE' : 'NOT_CONFIGURED') : 'NOT_CONFIGURED',
                'enabled' => $encryptionEnabled,
                'algorithm' => $encryptionConfig['algorithm'] ?? 'aes-256-cbc',
                'key_env' => $encryptionKeyEnv,
                'key_set' => $encryptionKeySet,
            ],
        ];
    }

    /**
     * Copy a backup file to off-site storage.
     * Do nothing if off-site is not configured.
     */
    public function syncToOffsite(string $localPath): bool
    {
        $offsiteConfig = config('backup.offsite', []);
        if (! ($offsiteConfig['enabled'] ?? false)) return false;

        $disk = $offsiteConfig['disk'] ?? null;
        if (! $disk) return false;

        try {
            $fullPath = storage_path('app/' . $localPath);
            if (! file_exists($fullPath)) return false;

            $contents = file_get_contents($fullPath);
            $offsitePath = ($offsiteConfig['path'] ?? 'backups-offsite') . '/' . basename($localPath);

            Storage::disk($disk)->put($offsitePath, $contents);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get a summary for the monitoring dashboard.
     */
    public function dashboardSummary(): array
    {
        $status = $this->status();
        return [
            'local_status' => $status['local']['status'],
            'local_file_count' => $status['local']['file_count'],
            'local_size_mb' => $status['local']['total_mb'],
            'offsite_status' => $status['offsite']['status'],
            'offsite_file_count' => $status['offsite']['file_count'],
            'encryption_status' => $status['encryption']['status'],
            'encryption_algorithm' => $status['encryption']['algorithm'],
        ];
    }
}

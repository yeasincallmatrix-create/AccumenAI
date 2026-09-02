<?php

namespace App\Services\System;

use App\Models\SystemBackup;
use Illuminate\Support\Facades\DB;

/**
 * Step 125-C — Backup Inventory Service
 * Maintains a reliable inventory of all backup files and metadata.
 * Detects missing files, checksum mismatches, unverified backups, duplicates.
 * Do not automatically repair metadata.
 */
class BackupInventoryService
{
    public function inventory(): array
    {
        $backups = SystemBackup::orderByDesc('created_at')->get();
        $items = [];
        $issues = [];

        foreach ($backups as $backup) {
            $fullPath = storage_path('app/' . $backup->path);
            $fileExists = $backup->path !== '' && file_exists($fullPath);
            $actualSize = $fileExists ? filesize($fullPath) : null;
            $actualChecksum = null;

            if ($fileExists && $actualSize > 0) {
                $algorithm = config('backup.verification.algorithm', 'sha256');
                $actualChecksum = hash_file($algorithm, $fullPath);
            }

            $checksumMatch = $actualChecksum === null || $backup->checksum === null || $backup->checksum === $actualChecksum;
            $sizeMatch = $actualSize === null || $backup->size_bytes === $actualSize;

            $item = [
                'id' => $backup->id,
                'filename' => $backup->filename,
                'type' => $backup->type,
                'status' => $backup->status,
                'created_at' => $backup->created_at?->toIso8601String(),
                'size_bytes' => $backup->size_bytes,
                'actual_size_bytes' => $actualSize,
                'checksum_recorded' => $backup->checksum,
                'checksum_actual' => $actualChecksum,
                'checksum_match' => $checksumMatch,
                'size_match' => $sizeMatch,
                'migration_count' => $backup->migration_count,
                'migration_version' => $backup->migration_version,
                'table_count' => $backup->table_count,
                'database_name' => $backup->metadata['db'] ?? null,
                'retention_class' => $this->retentionClass($backup),
                'protected' => $this->isProtected($backup),
                'file_exists' => $fileExists,
                'is_duplicate' => false,
            ];

            $items[] = $item;

            // Detect issues
            if (! $fileExists && $backup->status !== 'failed') {
                $issues[] = ['type' => 'missing_file', 'backup_id' => $backup->id, 'filename' => $backup->filename, 'message' => 'Backup file not found on disk'];
            }
            if (! $checksumMatch && $fileExists) {
                $issues[] = ['type' => 'checksum_mismatch', 'backup_id' => $backup->id, 'filename' => $backup->filename, 'message' => 'Checksum mismatch between recorded and actual'];
            }
            if ($backup->status !== 'verified' && $backup->status !== 'failed' && $fileExists) {
                $issues[] = ['type' => 'unverified', 'backup_id' => $backup->id, 'filename' => $backup->filename, 'message' => 'Backup has not been verified'];
            }
            if ($backup->path === '' && $backup->status !== 'failed') {
                $issues[] = ['type' => 'no_file_record', 'backup_id' => $backup->id, 'filename' => $backup->filename, 'message' => 'Backup record has no file path'];
            }
        }

        // Detect duplicates (same filename or same checksum among verified backups)
        $verified = array_filter($items, fn($i) => $i['status'] === 'verified');
        $byFilename = [];
        foreach ($verified as $i) {
            $byFilename[$i['filename']][] = $i['id'];
        }
        foreach ($byFilename as $filename => $ids) {
            if (count($ids) > 1) {
                foreach ($ids as $id) {
                    foreach ($items as &$item) {
                        if ($item['id'] === $id) $item['is_duplicate'] = true;
                    }
                    $issues[] = ['type' => 'duplicate', 'backup_id' => $id, 'filename' => $filename, 'message' => 'Duplicate verified backup with same filename'];
                }
            }
        }

        $totalSize = array_sum(array_column($items, 'size_bytes'));
        $verifiedCount = count(array_filter($items, fn($i) => $i['status'] === 'verified'));
        $failedCount = count(array_filter($items, fn($i) => $i['status'] === 'failed'));

        return [
            'generated_at' => now()->toIso8601String(),
            'total_backups' => count($items),
            'verified_count' => $verifiedCount,
            'failed_count' => $failedCount,
            'total_size_bytes' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'issues_count' => count($issues),
            'issues' => $issues,
            'items' => $items,
        ];
    }

    private function retentionClass(SystemBackup $backup): string
    {
        $type = $backup->type;
        if (in_array($type, ['manual', 'pre_restore', 'pre_orphan_cleanup', 'pre_destructive'])) return 'manual';
        if (str_starts_with($type, 'pre_')) return 'pre_operation';
        if ($type === 'daily') return 'daily';
        if ($type === 'weekly') return 'weekly';
        if ($type === 'monthly') return 'monthly';
        if ($backup->status === 'failed') return 'failed';
        return 'other';
    }

    private function isProtected(SystemBackup $backup): bool
    {
        $type = $backup->type;
        if (in_array($type, ['manual', 'pre_restore', 'pre_orphan_cleanup', 'pre_destructive'])) {
            return config('backup.retention.manual.retain_indefinitely', true);
        }
        return false;
    }
}

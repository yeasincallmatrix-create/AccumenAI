<?php

namespace App\Console\Commands;

use App\Services\System\BackupService;
use Illuminate\Console\Command;

/**
 * Step 122-C — Automated Database Backup Command.
 *
 * php artisan database:backup --type=daily --verify --force --json
 */
class DatabaseBackupCommand extends Command
{
    protected $signature = 'database:backup
        {--type=daily : Backup type (daily, weekly, manual, pre_restore)}
        {--verify : Verify the backup after creation}
        {--force : Skip concurrency lock check}
        {--json : Output as JSON}';

    protected $description = 'Create a database backup with optional verification';

    public function handle(BackupService $backupService): int
    {
        $type = $this->option('type');
        $verify = $this->option('verify');
        $force = $this->option('force');
        $jsonOutput = $this->option('json');

        // Check concurrency
        if (! $force && $backupService->isBackupRunning()) {
            $msg = 'Another backup is currently running. Use --force to override.';
            if ($jsonOutput) {
                $this->line(json_encode(['status' => 'blocked', 'message' => $msg]));
                return self::FAILURE;
            }
            $this->error($msg);
            return self::FAILURE;
        }

        // Acquire lock
        if (! $force) {
            $backupService->acquireLock();
        }

        $startTime = microtime(true);

        try {
            if (! $jsonOutput) {
                $this->line('');
                $this->line('DATABASE BACKUP');
                $this->line('Type: ' . $type);
                $this->line('');
            }

            $backup = $backupService->createScheduledBackup($type);

            $duration = round((microtime(true) - $startTime) * 1000);

            if ($backup->status === 'failed') {
                $reason = $backup->metadata['failure_reason'] ?? 'Unknown error';
                if ($jsonOutput) {
                    $this->line(json_encode([
                        'type' => $type,
                        'status' => 'failed',
                        'reason' => $reason,
                        'duration_ms' => $duration,
                    ]));
                } else {
                    $this->error('Status: FAILED');
                    $this->error('Reason: ' . $reason);
                    $this->error('Duration: ' . $duration . 'ms');
                }
                return self::FAILURE;
            }

            // Additional verification if requested
            if ($verify && $backup->status !== 'verified') {
                $backupService->verify($backup);
                $backup->refresh();
            }

            $status = $backup->status === 'verified' ? 'VERIFIED' : 'COMPLETED';

            if ($jsonOutput) {
                $this->line(json_encode([
                    'type' => $type,
                    'status' => $status,
                    'filename' => $backup->filename,
                    'file_size' => $backup->size_bytes,
                    'checksum' => $backup->checksum,
                    'table_count' => $backup->table_count,
                    'migration_count' => $backup->migration_count,
                    'duration_ms' => $duration,
                ]));
            } else {
                $this->line('Status: ' . $status);
                $this->line('File: ' . $backup->filename);
                $this->line('Size: ' . number_format($backup->size_bytes) . ' bytes');
                $this->line('SHA256: ' . ($backup->checksum ?? 'N/A'));
                $this->line('Tables: ' . $backup->table_count);
                $this->line('Migrations: ' . $backup->migration_count);
                $this->line('Duration: ' . $duration . 'ms');
                $this->line('');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $duration = round((microtime(true) - $startTime) * 1000);
            if ($jsonOutput) {
                $this->line(json_encode([
                    'type' => $type,
                    'status' => 'failed',
                    'reason' => $e->getMessage(),
                    'duration_ms' => $duration,
                ]));
            } else {
                $this->error('Status: FAILED');
                $this->error('Reason: ' . $e->getMessage());
                $this->error('Duration: ' . $duration . 'ms');
            }
            return self::FAILURE;
        } finally {
            if (! $force) {
                $backupService->releaseLock();
            }
        }
    }
}

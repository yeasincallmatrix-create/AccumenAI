<?php

namespace App\Services\System;

use App\Models\SystemBackup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Step 101/122 — Backup Management.
 * Handles database dump generation, timestamped backups, metadata, verification,
 * scheduled backups, concurrency protection, and failure recording.
 */
class BackupService
{
    public const BACKUP_DISK = 'local';
    public const BACKUP_PATH = 'backups';

    /**
     * Create a database backup of the given type.
     */
    public function create(string $type = 'manual', ?int $userId = null, string $userType = 'user'): SystemBackup
    {
        $timestamp = now()->format('Ymd_His');
        $appName = config('backup.app_name', 'monetix');
        $filename = "{$appName}_{$type}_{$timestamp}.sql";
        $relativePath = $this->getBackupPath() . '/' . $filename;
        $fullPath = storage_path('app/' . $relativePath);

        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        $this->generateDump($fullPath);

        $size = file_exists($fullPath) ? filesize($fullPath) : 0;
        $algorithm = config('backup.verification.algorithm', 'sha256');
        $checksum = $size > 0 ? hash_file($algorithm, $fullPath) : null;
        $migrationCount = DB::table('migrations')->count();
        $tableCount = count(DB::select('SHOW TABLES'));
        $latestMigration = DB::table('migrations')->orderByDesc('batch')->value('migration');

        $backup = SystemBackup::create([
            'filename' => $filename,
            'path' => $relativePath,
            'size_bytes' => $size,
            'checksum' => $checksum,
            'type' => $type,
            'status' => $size > 0 ? 'completed' : 'failed',
            'migration_count' => $migrationCount,
            'migration_version' => $latestMigration,
            'table_count' => $tableCount,
            'metadata' => [
                'db' => config('database.connections.mysql.database'),
                'generated_at' => now()->toIso8601String(),
                'driver' => $this->getDumpDriver(),
            ],
            'created_by' => $userId ?? auth()->id(),
            'created_by_type' => $userType,
        ]);

        $this->audit('backup_created', $backup);

        return $backup;
    }

    /**
     * Create a scheduled backup (daily/weekly) with verification and failure recording.
     *
     * @param  string  $type  'daily' or 'weekly'
     */
    public function createScheduledBackup(string $type = 'daily'): SystemBackup
    {
        $startTime = microtime(true);

        try {
            $backup = $this->create($type, null, 'scheduler');

            if ($backup->status === 'failed') {
                $this->recordFailure($backup, 'Dump generation failed — zero-size file');
                return $backup;
            }

            $minSize = config('backup.min_size_bytes', 100);
            if ($backup->size_bytes < $minSize) {
                $backup->update(['status' => 'failed']);
                $this->recordFailure($backup, "Backup size {$backup->size_bytes} bytes below minimum {$minSize} bytes");
                return $backup;
            }

            $verificationEnabled = config('backup.verification.enabled', true);
            if ($verificationEnabled) {
                $verified = $this->verify($backup);
                if (! $verified) {
                    $this->recordFailure($backup, 'Verification failed after creation');
                    return $backup;
                }
            }

            $duration = round((microtime(true) - $startTime) * 1000);
            $this->audit('scheduled_backup_completed', $backup);

            return $backup;
        } catch (\Throwable $e) {
            $duration = round((microtime(true) - $startTime) * 1000);
            $backup = SystemBackup::create([
                'filename' => 'FAILED_' . now()->format('Ymd_His') . '.sql',
                'path' => '',
                'size_bytes' => 0,
                'checksum' => null,
                'type' => $type,
                'status' => 'failed',
                'migration_count' => 0,
                'table_count' => 0,
                'metadata' => [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'generated_at' => now()->toIso8601String(),
                ],
                'created_by' => null,
                'created_by_type' => 'scheduler',
            ]);
            $this->recordFailure($backup, $e->getMessage());
            return $backup;
        }
    }

    /**
     * Verify a backup file matches its recorded checksum and passes integrity checks.
     */
    public function verify(SystemBackup $backup): bool
    {
        $fullPath = storage_path('app/' . $backup->path);

        if (! file_exists($fullPath)) {
            $backup->update(['status' => 'failed']);
            return false;
        }

        $size = filesize($fullPath);
        if ($size === 0) {
            $backup->update(['status' => 'failed']);
            return false;
        }

        $algorithm = config('backup.verification.algorithm', 'sha256');
        $checksum = hash_file($algorithm, $fullPath);
        $valid = $backup->checksum === null || $backup->checksum === $checksum;

        if ($valid) {
            $backup->update(['status' => 'verified', 'size_bytes' => $size, 'checksum' => $checksum]);
            $this->audit('backup_verified', $backup);
        } else {
            $backup->update(['status' => 'failed']);
            $this->recordFailure($backup, "Checksum mismatch: expected {$backup->checksum}, got {$checksum}");
        }

        return $valid;
    }

    /**
     * Record a backup failure for audit and monitoring purposes (Step 125-J).
     */
    public function recordFailure(SystemBackup $backup, string $reason, ?string $exceptionClass = null, ?int $retryCount = null): void
    {
        $metadata = $backup->metadata ?? [];
        $metadata['failure_reason'] = $reason;
        $metadata['failed_at'] = now()->toIso8601String();
        if ($exceptionClass) $metadata['exception_class'] = $exceptionClass;
        if ($retryCount !== null) $metadata['retry_count'] = $retryCount;
        $metadata['safe_error'] = $this->sanitizeError($reason);
        $backup->update(['metadata' => $metadata]);

        $this->audit('backup_failed', $backup);
    }

    /**
     * Step 125-J — Retry a failed backup with controlled concurrency.
     */
    public function retryBackup(SystemBackup $failedBackup): ?SystemBackup
    {
        $maxRetries = config('backup.failure.max_retries', 3);
        $retryDelay = config('backup.failure.retry_delay_seconds', 60);
        $currentRetry = ($failedBackup->metadata['retry_count'] ?? 0);

        if ($currentRetry >= $maxRetries) return null;
        if (! $this->acquireLock()) return null;

        try {
            sleep($retryDelay);
            $retry = $this->create($failedBackup->type, $failedBackup->created_by, $failedBackup->created_by_type);
            $metadata = $retry->metadata ?? [];
            $metadata['retry_count'] = $currentRetry + 1;
            $metadata['retry_of'] = $failedBackup->id;
            $retry->update(['metadata' => $metadata]);
            return $retry;
        } catch (\Throwable $e) {
            return null;
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Step 125-J — Get recent failure count for alerting.
     */
    public function recentFailureCount(int $minutes = 60): int
    {
        return SystemBackup::where('status', 'failed')
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->count();
    }

    private function sanitizeError(string $reason): string
    {
        $reason = preg_replace('/password[=:]\s*\S+/i', 'password=***', $reason);
        $reason = preg_replace('/\/[^\s]+\.exe/', '[binary]', $reason);
        return substr($reason, 0, 500);
    }

    /**
     * Check if a backup is currently running (concurrency lock).
     */
    public function isBackupRunning(): bool
    {
        $lockKey = config('backup.lock.key', 'backup_lock_running');
        return Cache::has($lockKey);
    }

    /**
     * Acquire the backup lock. Returns true if acquired, false otherwise.
     */
    public function acquireLock(): bool
    {
        $lockKey = config('backup.lock.key', 'backup_lock_running');
        $timeout = config('backup.lock.timeout', 300);
        return Cache::add($lockKey, getmypid(), $timeout);
    }

    /**
     * Release the backup lock.
     */
    public function releaseLock(): void
    {
        $lockKey = config('backup.lock.key', 'backup_lock_running');
        Cache::forget($lockKey);
    }

    /**
     * Get the latest successful (completed or verified) backup.
     */
    public function latestSuccessful(?string $type = null): ?SystemBackup
    {
        $q = SystemBackup::whereIn('status', ['completed', 'verified']);
        if ($type) {
            $q->where('type', $type);
        }
        return $q->orderByDesc('created_at')->first();
    }

    /**
     * Get the latest verified backup.
     */
    public function latestVerified(?string $type = null): ?SystemBackup
    {
        $q = SystemBackup::where('status', 'verified');
        if ($type) {
            $q->where('type', $type);
        }
        return $q->orderByDesc('created_at')->first();
    }

    /**
     * Get the latest failed backup.
     */
    public function latestFailed(): ?SystemBackup
    {
        return SystemBackup::where('status', 'failed')->orderByDesc('created_at')->first();
    }

    /**
     * Get backup statistics for monitoring dashboard.
     */
    public function getBackupStats(): array
    {
        $dailyEnabled = config('backup.daily.enabled', true);
        $weeklyEnabled = config('backup.weekly.enabled', true);

        $latest = $this->latest();
        $latestVerified = $this->latestVerified();
        $latestFailed = $this->latestFailed();
        $latestDaily = $this->latestVerified('daily');
        $latestWeekly = $this->latestVerified('weekly');

        $totalVerified = SystemBackup::where('status', 'verified')->count();
        $totalFailed = SystemBackup::where('status', 'failed')->count();
        $totalCompleted = SystemBackup::whereIn('status', ['completed', 'verified'])->count();

        $lastVerifiedAge = $latestVerified
            ? now()->diffInHours($latestVerified->created_at)
            : null;

        $threshold = config('backup.notification_threshold_hours', 48);
        $ageWarning = $lastVerifiedAge !== null && $lastVerifiedAge > $threshold;

        return [
            'daily_enabled' => $dailyEnabled,
            'weekly_enabled' => $weeklyEnabled,
            'daily_schedule' => config('backup.daily.schedule', '01:00'),
            'weekly_schedule' => config('backup.weekly.schedule', '02:00'),
            'weekly_day' => config('backup.weekly.day', 'sunday'),
            'verification_enabled' => config('backup.verification.enabled', true),
            'latest_backup' => $latest ? [
                'filename' => $latest->filename,
                'type' => $latest->type,
                'status' => $latest->status,
                'created_at' => $latest->created_at?->toIso8601String(),
                'size_bytes' => $latest->size_bytes,
            ] : null,
            'latest_verified' => $latestVerified ? [
                'filename' => $latestVerified->filename,
                'type' => $latestVerified->type,
                'created_at' => $latestVerified->created_at?->toIso8601String(),
                'size_bytes' => $latestVerified->size_bytes,
                'checksum' => $latestVerified->checksum,
            ] : null,
            'latest_daily' => $latestDaily ? [
                'filename' => $latestDaily->filename,
                'created_at' => $latestDaily->created_at?->toIso8601String(),
            ] : null,
            'latest_weekly' => $latestWeekly ? [
                'filename' => $latestWeekly->filename,
                'created_at' => $latestWeekly->created_at?->toIso8601String(),
            ] : null,
            'latest_failed' => $latestFailed ? [
                'filename' => $latestFailed->filename,
                'type' => $latestFailed->type,
                'created_at' => $latestFailed->created_at?->toIso8601String(),
                'reason' => ($latestFailed->metadata['failure_reason'] ?? 'Unknown'),
            ] : null,
            'total_verified' => $totalVerified,
            'total_failed' => $totalFailed,
            'total_completed' => $totalCompleted,
            'backup_age_hours' => $lastVerifiedAge,
            'age_warning' => $ageWarning,
            'is_running' => $this->isBackupRunning(),
            'no_verified_backup' => $latestVerified === null,
        ];
    }

    /**
     * Get the backup path from config.
     */
    public function getBackupPath(): string
    {
        return config('backup.path', self::BACKUP_PATH);
    }

    /**
     * Get the mysqldump binary path.
     */
    public function findMysqldumpBinary(): ?string
    {
        $configured = config('backup.mysqldump_path');
        if ($configured && file_exists($configured)) {
            return $configured;
        }

        $candidates = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            'mysqldump',
        ];
        foreach ($candidates as $c) {
            if ($c === 'mysqldump') return $c;
            if (file_exists($c)) return $c;
        }
        return null;
    }

    /**
     * Determine which dump driver is being used.
     */
    public function getDumpDriver(): string
    {
        $mysqldump = $this->findMysqldumpBinary();
        if ($mysqldump && $mysqldump !== 'mysqldump') {
            return 'mysqldump';
        }
        if ($mysqldump === 'mysqldump') {
            return 'mysqldump_path';
        }
        return 'php_fallback';
    }

    public function createPreRestoreBackup(): SystemBackup
    {
        return $this->create('pre_restore');
    }

    public function listBackups(int $limit = 20)
    {
        return SystemBackup::orderByDesc('created_at')->limit($limit)->get();
    }

    public function latest(string $type = null): ?SystemBackup
    {
        $q = SystemBackup::orderByDesc('created_at');
        if ($type) $q->where('type', $type);
        return $q->first();
    }

    protected function generateDump(string $fullPath): void
    {
        $connection = config('database.connections.mysql');
        $host = $connection['host'] ?? '127.0.0.1';
        $port = $connection['port'] ?? 3306;
        $database = $connection['database'];
        $username = $connection['username'];
        $password = $connection['password'] ?? '';

        $mysqldump = $this->findMysqldumpBinary();

        if ($mysqldump && $database) {
            $passArg = $password !== '' ? " -p" . escapeshellarg($password) : "";
            $cmd = sprintf(
                '%s -h %s -P %s -u %s%s %s --single-transaction --routines --triggers > %s 2>&1',
                escapeshellarg($mysqldump),
                escapeshellarg($host),
                escapeshellarg((string) $port),
                escapeshellarg($username),
                $passArg,
                escapeshellarg($database),
                escapeshellarg($fullPath)
            );
            @exec($cmd, $out, $code);
            if ($code === 0 && file_exists($fullPath) && filesize($fullPath) > 0) {
                return;
            }
        }

        $this->generateFallbackDump($fullPath);
    }

    protected function generateFallbackDump(string $fullPath): void
    {
        $tables = DB::select('SHOW TABLES');
        $content = "-- MAWA SaaS fallback backup generated at " . now() . "\n";
        $content .= "-- Database: " . config('database.connections.mysql.database') . "\n\n";
        foreach ($tables as $row) {
            $vals = array_values((array) $row);
            $table = $vals[0];
            $create = DB::selectOne("SHOW CREATE TABLE `$table`");
            $createArr = (array) $create;
            $sql = $createArr['Create Table'] ?? $createArr['create table'] ?? '';
            $content .= $sql . ";\n\n";
        }
        $migrations = DB::table('migrations')->pluck('migration')->all();
        $content .= "-- MIGRATIONS: " . implode(',', $migrations) . "\n";
        file_put_contents($fullPath, $content);
    }

    protected function audit(string $action, SystemBackup $backup): void
    {
        try {
            \App\Models\AuditLog::create([
                'institute_id' => 0,
                'user_type' => 'system',
                'user_id' => auth()->id() ?? 0,
                'action' => $action,
                'module' => 'backup',
                'record_id' => $backup->id,
                'old_values' => null,
                'new_values' => json_encode(['filename' => $backup->filename, 'type' => $backup->type]),
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => substr((string) request()->userAgent(), 0, 255),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // audit is best-effort
        }
    }
}

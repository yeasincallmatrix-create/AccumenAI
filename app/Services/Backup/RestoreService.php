<?php

namespace App\Services\Backup;

use App\Mail\EmailOtpMail;
use App\Models\Backup;
use App\Models\BackupManifest;
use App\Models\RestoreLog;
use App\Models\RestoreToken;
use App\Models\TenantDriveConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RestoreService
{
    public function __construct(
        private EncryptionService $encryption,
        private \App\Contracts\DriveStorageInterface $drive,
        private BackupService $backup,
        private BackupChunkService $chunkService,
        private ManifestService $manifestService
    ) {}

    /**
     * Generate OTP and send to owner email.
     */
    public function requestOtp(int $tenantId, int $backupId, int $userId): void
    {
        // Rate limit: 3 per 15 min
        $recent = RestoreToken::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('created_at', '>', now()->subMinutes(15))
            ->count();

        if ($recent >= 3) {
            throw new \RuntimeException('Too many restore requests. Try again in 15 minutes.');
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        RestoreToken::create([
            'tenant_id'  => $tenantId,
            'backup_id'  => $backupId,
            'user_id'    => $userId,
            'token_hash' => hash('sha256', $otp),
            'expires_at' => now()->addMinutes(10),
        ]);

        // Deliver via the existing queued OTP mail (same path as login OTP).
        $user = auth()->user();
        if ($user && !empty($user->email)) {
            try {
                Mail::to($user->email)->queue(new EmailOtpMail($otp, $this->maskEmail($user->email)));
            } catch (\Throwable $e) {
                Log::warning('restore_otp_mail_failed', ['error' => $e->getMessage()]);
            }
        }

        Log::info('Restore OTP generated', [
            'tenant_id'  => $tenantId,
            'user_id'    => $userId,
            'otp_length' => strlen($otp),
        ]);
    }

    /**
     * Verify OTP and perform restore.
     *
     * F4: backups with a manifest row restore via the content-addressed
     * chunk pipeline; legacy backups (no manifest) fall back to the
     * single-file .enc path.
     */
    public function verifyAndRestore(int $tenantId, int $backupId, int $userId, string $otp): RestoreLog
    {
        $token = RestoreToken::where('tenant_id', $tenantId)
            ->where('backup_id', $backupId)
            ->where('user_id', $userId)
            ->active()
            ->latest()
            ->first();

        if (!$token) {
            throw new \RuntimeException('No active restore token. Request a new OTP.');
        }

        if (!hash_equals($token->token_hash, hash('sha256', $otp))) {
            throw new \RuntimeException('Invalid OTP');
        }

        $token->update(['used_at' => now()]);

        $backup = Backup::where('tenant_id', $tenantId)->findOrFail($backupId);

        $log = RestoreLog::create([
            'tenant_id'  => $tenantId,
            'backup_id'  => $backupId,
            'user_id'    => $userId,
            'mode'       => 'merge',
            'status'     => 'pending',
        ]);

        $manifest = BackupManifest::where('backup_id', $backup->id)->first();

        try {
            $affected = $manifest
                ? $this->restoreFromManifest($backup, $manifest, $log, $tenantId)
                : $this->restoreLegacy($backup, $log, $tenantId);
        } catch (\Throwable $e) {
            $log->update([
                'status'           => 'failed',
                'records_affected' => ['error' => $e->getMessage()],
            ]);
            throw $e;
        }

        $log->update([
            'status'              => 'completed',
            'records_affected'    => $affected,
            'rollback_expires_at' => now()->addHours(24),
            'completed_at'        => now(),
        ]);

        return $log;
    }

    /**
     * Phase 2A: manifest-driven restore.
     * 1. Verify manifest self-checksum (tamper gate)
     * 2. Rollback snapshot BEFORE restore
     * 3. Per table: download chunks → decrypt → verify SHA256 → merge
     */
    private function restoreFromManifest(
        Backup $backup,
        BackupManifest $manifest,
        RestoreLog $log,
        int $tenantId
    ): array {
        $manifestArr = json_decode($manifest->manifest_json, true);

        if (!is_array($manifestArr) || !$this->manifestService->verifyChecksum($manifestArr)) {
            throw new \RuntimeException('Manifest checksum invalid — refusing restore');
        }

        $conn = TenantDriveConnection::where('tenant_id', $tenantId)
            ->whereNull('revoked_at')
            ->first();

        if (!$conn) {
            throw new \RuntimeException(
                'Google Drive connection not found for this backup. Reconnect Drive first.'
            );
        }

        // Rollback snapshot BEFORE restore
        $rollbackPath = $this->createRollbackSnapshot($tenantId);
        $log->update(['rollback_path' => $rollbackPath]);

        $affected = [];

        foreach (($manifestArr['tables'] ?? []) as $table => $info) {
            // Download + decrypt + verify + merge one table
            $json = $this->chunkService->downloadTableChunks($manifest, $table, $conn);
            $rows = json_decode($json, true);

            if (!is_array($rows)) {
                $affected[$table] = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
                continue;
            }

            $affected[$table] = $this->mergeTable($table, $rows, $tenantId);
        }

        Log::info('Manifest restore completed', [
            'tenant_id' => $tenantId,
            'backup_id' => $backup->id,
            'tables'    => count($affected),
        ]);

        return $affected;
    }

    /**
     * F4 legacy fallback: single-file .enc restore (pre-2A backups).
     * Drive is source of truth when destination === 'drive'.
     */
    private function restoreLegacy(Backup $backup, RestoreLog $log, int $tenantId): array
    {
        $workDir = storage_path("app/restore-work/{$log->id}");
        $tarPath = "{$workDir}.tar.gz";
        $downloadedEnc = null;

        try {
            // Resolve the ciphertext — Drive is the source of truth
            // once destination === 'drive' (local .enc was deleted after upload).
            if ($backup->destination === 'drive' && $backup->drive_file_id) {
                $driveConn = TenantDriveConnection::where('tenant_id', $tenantId)
                    ->whereNull('revoked_at')
                    ->first();

                if (!$driveConn) {
                    throw new \RuntimeException(
                        'Google Drive connection not found for this backup. Reconnect Drive first.'
                    );
                }

                $encPath = storage_path("app/restore-work/{$log->id}.enc");
                @mkdir(dirname($encPath), 0755, true);

                $this->drive->downloadBackup($driveConn, $backup->drive_file_id, $encPath);
                $downloadedEnc = $encPath;
            } else {
                $encPath = storage_path("app/backups/{$backup->filename}");
                if (!file_exists($encPath)) {
                    throw new \RuntimeException('Backup file not found locally or on Drive');
                }
            }

            // Rollback snapshot BEFORE restore
            $rollbackPath = $this->createRollbackSnapshot($tenantId);
            $log->update(['rollback_path' => $rollbackPath]);

            // Decrypt with tamper detection
            @mkdir($workDir, 0755, true);

            $this->encryption->decryptFile($encPath, $tarPath, $tenantId, $backup->file_hmac);

            // Extract + restore with MERGE semantics
            $affected = $this->restoreFromTar($tarPath, $tenantId);

            @unlink($tarPath);
            if ($downloadedEnc !== null) {
                @unlink($downloadedEnc);
            }
            $this->rmrf($workDir);

            return $affected;
        } catch (\Throwable $e) {
            @unlink($tarPath);
            if ($downloadedEnc !== null) {
                @unlink($downloadedEnc);
            }
            $this->rmrf($workDir);
            throw $e;
        }
    }

    /**
     * Snapshot all tenant data BEFORE a restore so it can be rolled back
     * (rollback application = Phase 2; file expires after 24h).
     * Streams per-table JSON into a single .json.gz (no full-table loads).
     */
    private function createRollbackSnapshot(int $tenantId): string
    {
        $dir = storage_path('app/rollback-snapshots');
        @mkdir($dir, 0755, true);

        $filename = "rollback-tenant-{$tenantId}-" . now()->format('Ymd-His') . '.json.gz';
        $path = "{$dir}/{$filename}";

        $tables = $this->backup->getTenantTables(); // [table => tenant column]
        $tableCount = 0;

        $final = gzopen($path, 'wb');
        if ($final === false) {
            throw new \RuntimeException("Cannot create rollback snapshot: {$path}");
        }
        gzwrite($final, json_encode([
            'meta' => [
                'tenant_id'   => $tenantId,
                'created_at'  => now()->toIso8601String(),
                'table_count' => count($tables),
            ],
        ]));

        foreach ($tables as $table => $column) {
            // Stream write per table to avoid memory issues
            $tempFile = "{$dir}/tmp-{$table}.json";
            $fp = fopen($tempFile, 'wb');
            if ($fp === false) {
                continue;
            }
            fwrite($fp, '[');

            $first = true;
            $query = DB::table($table)->where($column, $tenantId);
            // PK-aware ordering (shared with backup export) — chunk() requires orderBy
            foreach ($this->backup->primaryKeyColumns($table) as $key) {
                $query->orderBy($key);
            }
            $query->chunk(500, function ($rows) use ($fp, &$first) {
                foreach ($rows as $row) {
                    if (!$first) fwrite($fp, ',');
                    fwrite($fp, json_encode((array) $row));
                    $first = false;
                }
            });

            fwrite($fp, ']');
            fclose($fp);

            gzwrite($final, "\n---TABLE:{$table}---\n");
            gzwrite($final, file_get_contents($tempFile));
            @unlink($tempFile);
            $tableCount++;
        }

        gzclose($final);

        if ($tableCount === 0) {
            @unlink($path);
            throw new \RuntimeException('Rollback snapshot failed: no tenant tables exported');
        }

        return $path;
    }

    /**
     * Extract backup and apply rows with MERGE semantics:
     * INSERT new + UPDATE existing — never DELETE.
     * Per-row error handling (log + skip) so one bad row can't abort.
     */
    private function restoreFromTar(string $tarPath, int $tenantId): array
    {
        $workDir = storage_path('app/restore-work/' . uniqid('r', true));
        @mkdir($workDir, 0755, true);

        $affected = [];

        try {
            // Extract tar.gz
            $phar = new \PharData($tarPath);
            $phar->extractTo($workDir, null, true);

            // Process each table JSON
            foreach (glob("{$workDir}/*.json") ?: [] as $jsonFile) {
                $table = basename($jsonFile, '.json');
                if ($table === '_metadata') continue;

                $rows = json_decode(file_get_contents($jsonFile), true);
                if (!is_array($rows)) {
                    $affected[$table] = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
                    continue;
                }

                $affected[$table] = $this->mergeTable($table, $rows, $tenantId);
            }
        } finally {
            // Cleanup extraction dir (success or failure)
            $this->rmrf($workDir);
        }

        return $affected;
    }

    /**
     * MERGE one table's rows: INSERT new + UPDATE existing — never DELETE.
     * Shared by legacy tar restore and manifest chunk restore.
     */
    private function mergeTable(string $table, array $rows, int $tenantId): array
    {
        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($table, $rows, $tenantId, &$inserted, &$updated, &$skipped) {
            foreach ($rows as $row) {
                $rowArr = (array) $row;

                // Skip if no primary key
                if (!isset($rowArr['id'])) {
                    $skipped++;
                    continue;
                }

                // Ensure tenant scoping
                if (isset($rowArr['institute_id'])) {
                    $rowArr['institute_id'] = $tenantId;
                }

                $exists = DB::table($table)->where('id', $rowArr['id'])->exists();

                try {
                    if ($exists) {
                        // UPDATE existing
                        DB::table($table)->where('id', $rowArr['id'])->update($rowArr);
                        $updated++;
                    } else {
                        // INSERT new
                        DB::table($table)->insert($rowArr);
                        $inserted++;
                    }
                } catch (\Throwable $e) {
                    // Log + skip individual row (don't abort entire restore)
                    Log::warning("Restore row failed: {$table}.id={$rowArr['id']}", [
                        'error' => $e->getMessage(),
                    ]);
                    $skipped++;
                }
            }
        });

        return [
            'inserted' => $inserted,
            'updated'  => $updated,
            'skipped'  => $skipped,
        ];
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $masked = substr($local, 0, 2) . str_repeat('*', max(strlen($local) - 2, 1));

        return $masked . '@' . $domain;
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getRealPath()) : unlink($f->getRealPath());
        }
        rmdir($dir);
    }
}

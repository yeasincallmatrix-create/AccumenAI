<?php

namespace App\Services\Backup;

use App\Mail\EmailOtpMail;
use App\Models\Backup;
use App\Models\RestoreLog;
use App\Models\RestoreToken;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RestoreService
{
    public function __construct(private EncryptionService $encryption) {}

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

        $workDir = storage_path("app/restore-work/{$log->id}");
        $tarPath = "{$workDir}.tar.gz";

        try {
            $encPath = storage_path("app/backups/{$backup->filename}");
            if (!file_exists($encPath)) {
                throw new \RuntimeException('Backup file not found');
            }

            // Rollback snapshot BEFORE restore
            $rollbackPath = $this->createRollbackSnapshot($tenantId);

            // Decrypt with tamper detection
            @mkdir($workDir, 0755, true);

            $this->encryption->decryptFile($encPath, $tarPath, $tenantId, $backup->file_hmac);

            // Extract + restore with MERGE semantics
            $affected = $this->restoreFromTar($tarPath, $tenantId);

            $log->update([
                'status'           => 'completed',
                'records_affected' => $affected,
                'rollback_path'    => $rollbackPath,
                'completed_at'     => now(),
            ]);

            @unlink($tarPath);
            $this->rmrf($workDir);

        } catch (\Throwable $e) {
            $log->update([
                'status'           => 'failed',
                'records_affected' => ['error' => $e->getMessage()],
            ]);
            @unlink($tarPath);
            $this->rmrf($workDir);
            throw $e;
        }

        return $log;
    }

    private function createRollbackSnapshot(int $tenantId): string
    {
        // TODO: Implement actual snapshot
        // For now, log path placeholder
        return 'rollback-' . now()->format('Ymd-His') . '-tenant-' . $tenantId;
    }

    private function restoreFromTar(string $tarPath, int $tenantId): array
    {
        // TODO: Implement actual restore with MERGE
        // Placeholder returns record counts
        return ['placeholder' => true];
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

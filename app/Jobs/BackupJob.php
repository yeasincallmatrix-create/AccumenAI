<?php

namespace App\Jobs;

use App\Mail\BackupCompleteMail;
use App\Mail\BackupFailedMail;
use App\Models\Backup;
use App\Services\Backup\BackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;   // 1 hour max (DB_QUEUE_RETRY_AFTER=3660 must exceed this)
    public int $tries = 2;
    public int $backoff = 30;

    public function __construct(
        public int $backupId,
        public int $tenantId,
        public int $userId,
        public string $recipientEmail
    ) {}

    public function handle(BackupService $service): void
    {
        $backup = Backup::find($this->backupId);
        if (!$backup) {
            Log::warning('BackupJob: backup not found', ['backup_id' => $this->backupId]);
            return;
        }

        $backup->update([
            'job_id'        => $this->job?->getJobId(),
            'started_at'    => now(),
            'status'        => 'uploading',
            'progress_stage' => 'starting',
        ]);

        try {
            $service->executeBackup($backup, $this->tenantId, $this->userId);

            // Adaptation 6: mail failure must never fail/retry a completed job
            try {
                Mail::to($this->recipientEmail)->queue(new BackupCompleteMail($backup));
            } catch (\Throwable $e) {
                Log::warning('Backup complete mail failed', [
                    'backup_id' => $backup->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('BackupJob failed', [
                'backup_id' => $this->backupId,
                'error'     => $e->getMessage(),
            ]);

            // Phase 2D: keep the lock-specific stage/message when the failure
            // was a lock rejection (user-facing text set in executeBackup).
            $locked = $backup->progress_stage === 'locked';

            $backup->update([
                'status'           => 'failed',
                'error_message'    => $locked ? $backup->error_message : $e->getMessage(),
                'progress_stage'   => $locked ? 'locked' : 'failed',
                'progress_message' => $locked ? $backup->progress_message : 'Backup failed: ' . $e->getMessage(),
            ]);

            try {
                Mail::to($this->recipientEmail)->queue(new BackupFailedMail($backup, $e->getMessage()));
            } catch (\Throwable $mailErr) {
                Log::warning('Backup failed mail failed', [
                    'backup_id' => $backup->id,
                    'error'     => $mailErr->getMessage(),
                ]);
            }

            throw $e;
        }
    }
}

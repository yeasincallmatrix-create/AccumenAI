<?php

namespace App\Jobs;

use App\Mail\RestoreCompleteMail;
use App\Mail\RestoreFailedMail;
use App\Models\RestoreLog;
use App\Services\Backup\RestoreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RestoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;   // 30 min
    public int $tries = 1;

    public function __construct(
        public int $restoreLogId,
        public int $backupId,
        public int $tenantId,
        public int $userId,
        public string $recipientEmail,
        public string $mode = 'merge'
    ) {}

    public function handle(RestoreService $service): void
    {
        $log = RestoreLog::find($this->restoreLogId);
        if (!$log) {
            Log::warning('RestoreJob: restore log not found', ['log_id' => $this->restoreLogId]);
            return;
        }

        $log->update([
            'job_id'         => $this->job?->getJobId(),
            'progress_stage' => 'starting',
        ]);

        try {
            $service->executeRestore($log, $this->backupId, $this->tenantId, $this->userId, $this->mode);

            try {
                Mail::to($this->recipientEmail)->queue(new RestoreCompleteMail($log));
            } catch (\Throwable $e) {
                Log::warning('Restore complete mail failed', [
                    'log_id' => $log->id,
                    'error'  => $e->getMessage(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('RestoreJob failed', [
                'log_id' => $this->restoreLogId,
                'error'  => $e->getMessage(),
            ]);

            // Phase 2D: keep the lock-specific stage/message when the failure
            // was a lock rejection (user-facing text set in executeRestore).
            $locked = $log->progress_stage === 'locked';

            $log->update([
                'status'           => 'failed',
                'error_message'    => $locked ? $log->error_message : $e->getMessage(),
                'progress_stage'   => $locked ? 'locked' : 'failed',
                'progress_message' => $locked ? $log->progress_message : 'Restore failed: ' . $e->getMessage(),
            ]);

            try {
                Mail::to($this->recipientEmail)->queue(new RestoreFailedMail($log, $e->getMessage()));
            } catch (\Throwable $mailErr) {
                Log::warning('Restore failed mail failed', [
                    'log_id' => $log->id,
                    'error'  => $mailErr->getMessage(),
                ]);
            }

            throw $e;
        }
    }
}

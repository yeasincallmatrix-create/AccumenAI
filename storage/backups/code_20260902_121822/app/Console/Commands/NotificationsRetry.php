<?php

namespace App\Console\Commands;

use App\Jobs\SendNotificationJob;
use App\Models\NotificationLog;
use Illuminate\Console\Command;

/**
 * Re-dispatches failed notification logs that have not exhausted their retry
 * budget (retry_count < max_retries). Runs from the scheduler; retried rows are
 * reset to queued before a fresh job is pushed, so a sync queue also benefits.
 */
class NotificationsRetry extends Command
{
    protected $signature = 'notifications:retry {--limit=100 : Maximum logs to retry per run}';

    protected $description = 'Retry failed notification deliveries that still have attempts left';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $logs = NotificationLog::withoutGlobalScope('institute')
            ->where('status', NotificationLog::STATUS_FAILED)
            ->whereColumn('retry_count', '<', 'max_retries')
            ->latest()
            ->limit($limit)
            ->get();

        $requeued = 0;
        foreach ($logs as $log) {
            $log->forceFill([
                'status' => NotificationLog::STATUS_QUEUED,
                'queued_at' => now(),
                'failed_at' => null,
                'error' => null,
            ])->save();

            SendNotificationJob::dispatch($log->id)->onQueue(config('notifications.delivery.queue', 'notifications'));
            $requeued++;
        }

        $this->info("Requeued {$requeued} failed notification(s).");

        return self::SUCCESS;
    }
}

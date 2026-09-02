<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\Notification\Channels\InAppChannel;
use App\Services\Notification\Channels\MailChannel;
use App\Services\Notification\Channels\SmsChannel;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Delivers a single notification-log row on the `notifications` queue.
 *
 * Restores tenant context from the log's institute so scoped reads inside
 * channels (and the model lookup itself) behave exactly as the originating
 * request did. The job catches every failure and records it on the log; the
 * scheduled `notifications:retry` command re-dispatches failed logs.
 */
class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    public $tries = 1;

    public $timeout = 60;

    public function __construct(public int $logId) {}

    public function handle(): void
    {
        $tenantWasEnabled = TenantContext::enabled();
        $tenantId = TenantContext::id();
        $branchWasEnabled = BranchContext::enabled();
        $branchId = BranchContext::id();

        try {
            $this->deliver();
        } finally {
            $this->restoreContext($tenantWasEnabled, $tenantId, $branchWasEnabled, $branchId);
        }
    }

    private function deliver(): void
    {
        $log = NotificationLog::withoutGlobalScope('institute')->find($this->logId);
        if ($log === null || $log->status === NotificationLog::STATUS_SENT) {
            return;
        }

        TenantContext::set($log->institute_id);
        BranchContext::clear();

        $log->forceFill([
            'status' => NotificationLog::STATUS_SENDING,
            'queued_at' => $log->queued_at ?: now(),
        ])->save();

        $data = is_array($log->metadata) ? $log->metadata : [];

        $result = $this->channel($log->channel)->send($log, $data);

        $update = ['status' => $result['status']];
        if ($result['status'] === NotificationLog::STATUS_SENT) {
            $update['sent_at'] = now();
            $update['failed_at'] = null;
            $update['error'] = null;
        } else {
            $update['failed_at'] = now();
            $update['error'] = $result['error'];
        }
        if ($result['provider'] !== null) {
            $update['provider'] = $result['provider'];
        }
        if ($result['provider_message_id'] !== null) {
            $update['provider_message_id'] = $result['provider_message_id'];
        }
        if ($result['provider_response'] !== null) {
            $update['provider_response'] = substr((string) $result['provider_response'], 0, 2000);
        }

        DB::transaction(fn () => $log->forceFill($update)->save());
    }

    private function channel(string $channel): InAppChannel|MailChannel|SmsChannel
    {
        return match ($channel) {
            'in_app' => app(InAppChannel::class),
            'email' => app(MailChannel::class),
            default => app(SmsChannel::class),
        };
    }

    private function restoreContext(bool $tenantWasEnabled, ?int $tenantId, bool $branchWasEnabled, ?int $branchId): void
    {
        if ($tenantWasEnabled && $tenantId !== null) {
            TenantContext::set($tenantId);
        } else {
            TenantContext::clear();
        }

        if ($branchWasEnabled && $branchId !== null) {
            BranchContext::set($branchId);
        } else {
            BranchContext::clear();
        }
    }
}

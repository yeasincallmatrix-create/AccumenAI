<?php

namespace App\Services\Dealership\Api;

use App\Models\Dealership\PushNotification;
use App\Models\Dealership\SalesForce;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Push dispatch layer (database channel only — FCM/APNs deferred).
 * dispatchBatch marks due queued rows as sent; real provider calls
 * plug in here later.
 */
class PushDispatchService
{
    public function queue(
        SalesForce $salesForce,
        string $title,
        string $body,
        array $data = [],
        ?Carbon $when = null
    ): PushNotification {
        return PushNotification::create([
            'institute_id' => $salesForce->institute_id,
            'recipient_sales_force_id' => $salesForce->id,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'status' => 'queued',
            'scheduled_at' => $when,
        ]);
    }

    public function dispatchBatch(int $instituteId, int $limit = 100): int
    {
        $due = PushNotification::withoutGlobalScope('institute')
            ->where('institute_id', $instituteId)
            ->where('status', 'queued')
            ->where(fn ($q) => $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($due as $notification) {
            Log::info('dealership.push.dispatch', [
                'id' => $notification->id,
                'institute_id' => $instituteId,
                'recipient' => $notification->recipient_sales_force_id,
                'title' => $notification->title,
            ]);

            $notification->update(['status' => 'sent', 'sent_at' => now()]);
        }

        return $due->count();
    }
}

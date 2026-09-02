<?php

namespace App\Services\Notification\Channels;

use App\Models\NotificationLog;

/**
 * Contract for a notification delivery channel (in-app / email / SMS).
 *
 * Implementations never throw; they translate failures into a structured
 * result the job writes to the notification log.
 */
interface NotificationChannelContract
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{status: string, provider: string|null, provider_message_id: string|null, provider_response: string|null, error: string|null}
     */
    public function send(NotificationLog $log, array $data = []): array;
}

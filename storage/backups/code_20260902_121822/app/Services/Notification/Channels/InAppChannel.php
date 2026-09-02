<?php

namespace App\Services\Notification\Channels;

use App\Models\Notification;
use App\Models\NotificationLog;

/**
 * Delivers an in-app notification by writing to the existing platform
 * `notifications` table (same table the bell/layout already reads from), then
 * linking the canonical log row back to it via notification_id.
 */
class InAppChannel implements NotificationChannelContract
{
    public function send(NotificationLog $log, array $data = []): array
    {
        // External contacts cannot open the app; nothing to store.
        if (in_array($log->recipient_type, ['external_email', 'external_phone'], true)) {
            return $this->result('skipped');
        }

        if (! in_array($log->recipient_type, ['platform_admin', 'institute_user', 'student'], true)) {
            return $this->result('skipped');
        }

        $notification = Notification::create([
            'scope' => $log->institute_id ? 'institute' : 'user',
            'institute_id' => $log->institute_id,
            'target_user_type' => $log->recipient_type,
            'target_user_id' => $log->recipient_id,
            'category' => mb_substr($log->event, 0, 40),
            'title' => $this->clip($log->subject, (int) config('notifications.delivery.in_app_title_max', 150)),
            'message' => $this->clip($log->body, (int) config('notifications.delivery.in_app_message_max', 500)),
            'link_url' => $data['link'] ?? null,
            'created_by_type' => in_array($data['actor_type'] ?? null, ['platform_admin', 'institute_user', 'system'], true)
                ? $data['actor_type']
                : 'system',
            'created_by_id' => $data['actor_id'] ?? null,
            'created_at' => now(),
        ]);

        $log->update(['notification_id' => $notification->id]);

        return $this->result('sent');
    }

    private function clip(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max - 1)).'…';
    }

    /**
     * @return array{status: string, provider: null, provider_message_id: null, provider_response: null, error: null}
     */
    private function result(string $status): array
    {
        return [
            'status' => $status,
            'provider' => null,
            'provider_message_id' => null,
            'provider_response' => null,
            'error' => null,
        ];
    }
}

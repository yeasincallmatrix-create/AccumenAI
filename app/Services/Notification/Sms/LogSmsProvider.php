<?php

namespace App\Services\Notification\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Default SMS provider used when no real gateway is configured. Records the
 * send to the log channel and returns a fake message id so the delivery
 * pipeline never fails in development or on unconfigured installs.
 */
class LogSmsProvider implements SmsProviderContract
{
    public function send(string $phone, string $message, array $options = []): array
    {
        Log::info('notification.sms', [
            'phone' => $phone,
            'message' => $message,
            'provider' => $options['provider'] ?? 'log',
        ]);

        return [
            'message_id' => 'log-'.uniqid(),
            'raw' => 'logged',
        ];
    }
}

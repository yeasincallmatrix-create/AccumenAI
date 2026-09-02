<?php

namespace App\Services\Notification\Sms;

/**
 * Industry-neutral SMS gateway abstraction. Providers return a message id and
 * the raw gateway response so the notification log can record both.
 */
interface SmsProviderContract
{
    /**
     * @param  array<string, mixed>  $options
     * @return array{message_id: string|null, raw: mixed}
     */
    public function send(string $phone, string $message, array $options = []): array;
}

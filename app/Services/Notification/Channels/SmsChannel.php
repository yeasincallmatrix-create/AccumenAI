<?php

namespace App\Services\Notification\Channels;

use App\Models\InstituteSetting;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Services\Notification\Sms\SmsProviderContract;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Crypt;

/**
 * Delivers a notification via SMS using the registered provider.
 *
 * Provider selection: institute sms_provider → platform sms.provider setting →
 * config default. Any non-registered provider name falls back to the generic
 * HTTP provider so a stored gateway name never crashes the pipeline.
 */
class SmsChannel implements NotificationChannelContract
{
    public function __construct(private readonly Container $container) {}

    public function send(NotificationLog $log, array $data = []): array
    {
        $phone = $log->recipient_contact ?: ($data['phone'] ?? null);
        if (! filled($phone)) {
            return $this->result('skipped');
        }

        // Master kill-switch — when SMS service disabled, never attempt external delivery
        $enabled = Setting::get('sms.enabled', '0');
        if ($enabled !== '1' && $enabled !== 1 && $enabled !== true) {
            // Fallback to log provider so pipeline never throws, but external SMS is suppressed
            return $this->result('skipped', 'log');
        }

        try {
            $providerName = $this->resolveProviderName($log->institute_id);
            $provider = $this->provider($providerName);
            $options = $this->providerOptions($log->institute_id);

            $result = $provider->send($phone, $log->body ?? '', $options);

            return $this->result(
                'sent',
                $providerName,
                $result['message_id'],
                is_string($result['raw']) ? $result['raw'] : null
            );
        } catch (\Throwable $e) {
            return $this->result('failed', $providerName ?? null, null, null, substr($e->getMessage(), 0, 500));
        }
    }

    private function resolveProviderName(?int $instituteId): string
    {
        $stored = null;
        if ($instituteId !== null) {
            $settings = InstituteSetting::query()->where('institute_id', $instituteId)->first();
            if ($settings !== null && filled($settings->sms_provider)) {
                $stored = $settings->sms_provider;
            }
        }

        $stored ??= Setting::get('sms.provider');
        $stored ??= (string) config('notifications.sms.default', 'log');

        $registry = array_keys(config('notifications.sms.providers', []));
        if (! in_array($stored, $registry, true)) {
            return 'http';
        }

        return $stored;
    }

    private function provider(string $name): SmsProviderContract
    {
        $class = config("notifications.sms.providers.{$name}");
        if (! is_string($class) || ! class_exists($class)) {
            $class = config('notifications.sms.providers.http');
        }

        return $this->container->make($class);
    }

    /**
     * @return array{api_key: string, from: string}
     */
    private function providerOptions(?int $instituteId): array
    {
        $apiKey = Setting::get('sms.api_key');
        $apiSecret = Setting::get('sms.api_secret');
        $senderId = Setting::get('sms.sender_id');
        $from = $senderId ?: Setting::get('sms.from');
        $url = Setting::get('sms.api_url', config('notifications.sms.http.url', ''));

        if ($instituteId !== null) {
            $settings = InstituteSetting::query()->where('institute_id', $instituteId)->first();
            if ($settings !== null) {
                if (filled($settings->sms_api_key_enc)) {
                    try {
                        $apiKey = Crypt::decryptString($settings->sms_api_key_enc);
                    } catch (\Throwable) {
                        $apiKey = $settings->sms_api_key_enc;
                    }
                }
            }
        }

        return [
            'api_key' => (string) ($apiKey ?? ''),
            'api_secret' => (string) ($apiSecret ?? ''),
            'from' => (string) ($from ?? ''),
            'url' => (string) ($url ?? ''),
            'sender_id' => (string) ($senderId ?? ''),
        ];
    }

    /**
     * @return array{status: string, provider: string|null, provider_message_id: string|null, provider_response: string|null, error: string|null}
     */
    private function result(string $status, ?string $provider = null, mixed $messageId = null, mixed $response = null, ?string $error = null): array
    {
        return [
            'status' => $status,
            'provider' => $provider,
            'provider_message_id' => $messageId !== null ? (string) $messageId : null,
            'provider_response' => $response,
            'error' => $error,
        ];
    }
}

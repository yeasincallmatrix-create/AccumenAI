<?php

namespace App\Services\Notification\Sms;

use App\Models\Setting;
use App\Support\IdentityConfig;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Generic HTTP SMS gateway provider.
 *
 * The endpoint, HTTP method and field mapping are configured in
 * config/notifications.php -> sms.http. The api key and from number are passed
 * in $options (resolved from institute / platform settings) so no gateway is
 * hard-coded into the engine.
 */
class HttpSmsProvider implements SmsProviderContract
{
    public function send(string $phone, string $message, array $options = []): array
    {
        $config = config('notifications.sms.http', []);
        // Precedence: Setting sms.api_url → options → config env
        $settingUrl = Setting::get('sms.api_url');
        $url = (string) ($options['url'] ?? ($settingUrl ?: ($config['url'] ?? '')));
        $settingMethod = Setting::get('sms.http_method');
        $method = strtolower((string) ($options['method'] ?? ($settingMethod ?: ($config['method'] ?? 'post'))));

        if (! filled($url)) {
            throw new RuntimeException('SMS HTTP gateway is not configured (notifications.sms.http.url).');
        }

        $fields = $config['fields'] ?? [];
        $payload = [];
        foreach ($fields as $gatewayField => $payloadKey) {
            $value = match ($payloadKey) {
                'to' => $phone,
                'message' => $message,
                'api_key' => $options['api_key'] ?? '',
                'api_secret' => $options['api_secret'] ?? '',
                'from' => $options['from'] ?? '',
                default => $options[$payloadKey] ?? '',
            };
            if (filled($value)) {
                $payload[$gatewayField] = $value;
            }
        }
        // Optional passthrough for credentials that are stored but not in the static field map
        // Added non-blocking: if provider expects additional keys (e.g. BulkSMS BD liapacity secret), they can be
        // supplied via generic fields or as documented providerOptions without changing engine
        if (filled($options['api_secret'] ?? null) && ! collect($fields)->contains(fn ($v) => $v === 'api_secret')) {
            $payload['api_secret'] = $options['api_secret'];
        }

        $client = Http::timeout(15);
        $response = $method === 'get'
            ? $client->get($url, $payload)
            : $client->post($url, $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                'SMS gateway responded '.$response->status().': '.substr($response->body(), 0, 300)
            );
        }

        $messageId = null;
        $path = $config['response_message_id_path'] ?? '';
        if (filled($path)) {
            $messageId = data_get($response->json(), $path);
        }

        return [
            'message_id' => $messageId !== null ? (string) $messageId : null,
            'raw' => substr($response->body(), 0, 2000),
        ];
    }
}

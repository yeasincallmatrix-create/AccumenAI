<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformAuditLog;
use App\Models\Setting;
use App\Services\Notification\Sms\HttpSmsProvider;
use App\Services\Notification\Sms\LogSmsProvider;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class PlatformSettingsController extends Controller
{
    private function viewData(): array
    {
        return [
            // General
            'appName' => Setting::get('app.name', config('app.name')),
            'appShortName' => Setting::get('app.short_name', 'AccumenAI'),
            'appUrl' => Setting::get('app.url', config('app.url')),
            'timezone' => Setting::get('app.timezone', 'Asia/Dhaka'),
            'country' => Setting::get('app.country', 'BD'),
            'currency' => Setting::get('app.currency', 'BDT'),
            'language' => Setting::get('app.language', 'en'),
            'dateFormat' => Setting::get('app.date_format', 'd M Y'),
            'timeFormat' => Setting::get('app.time_format', 'H:i'),
            'pagination' => Setting::get('app.pagination', '15'),
            'contactEmail' => Setting::get('app.contact_email', ''),
            'supportPhone' => Setting::get('app.support_phone', ''),
            'supportUrl' => Setting::get('app.support_url', ''),
            // Email
            'smtpHost' => Setting::get('smtp.host', ''),
            'smtpPort' => Setting::get('smtp.port', '587'),
            'smtpEncryption' => Setting::get('smtp.encryption', 'tls'),
            'smtpUsername' => Setting::get('smtp.username', ''),
            'smtpFromAddress' => Setting::get('smtp.from_address', config('mail.from.address')),
            'smtpFromName' => Setting::get('smtp.from_name', config('mail.from.name')),
            'smtpReplyName' => Setting::get('smtp.reply_to_name', ''),
            'smtpReplyEmail' => Setting::get('smtp.reply_to_email', ''),
            'smtpEnabled' => Setting::get('smtp.enabled', '1'),
            'smtpQueue' => Setting::get('smtp.queue', '1'),
            'smtpRetry' => Setting::get('smtp.retry_count', '3'),
            'smtpTimeout' => Setting::get('smtp.timeout', '30'),
            'smtpPasswordMasked' => PlatformSettingsService::masked('smtp.password'),
            'smtpConfigured' => filled(Setting::get('smtp.host')),
            // SMS
            'sms' => [
                'provider' => Setting::get('sms.provider', 'log'),
                'type' => Setting::get('sms.type', 'log'),
                'api_url' => Setting::get('sms.api_url', ''),
                'http_method' => Setting::get('sms.http_method', 'POST'),
                'sender_id' => Setting::get('sms.sender_id', ''),
                'sender_name' => Setting::get('sms.sender_name', ''),
                'auth_type' => Setting::get('sms.auth_type', 'none'),
                'message_param' => Setting::get('sms.message_param', 'message'),
                'phone_param' => Setting::get('sms.phone_param', 'to'),
                'success_condition' => Setting::get('sms.success_condition', ''),
                'enabled' => Setting::get('sms.enabled', '0'),
                'api_key_masked' => PlatformSettingsService::masked('sms.api_key'),
                'api_secret_masked' => PlatformSettingsService::masked('sms.api_secret'),
                'password_masked' => PlatformSettingsService::masked('sms.password'),
                'status_label' => (Setting::get('sms.enabled', '0') === '1' ? 'ENABLED' : 'DISABLED'),
                'provider_status' => $this->smsProviderStatus(),
                'is_configured' => filled(Setting::get('sms.api_url')) && in_array(Setting::get('sms.provider', 'log'), ['log', 'http'], true) && (Setting::get('sms.provider', 'log') === 'log' || filled(Setting::get('sms.api_url'))),
            ],
            // OTP
            'otp' => PlatformSettingsService::otpSettings(),
            // 2FA
            'twofa' => PlatformSettingsService::twoFactorSettings(),
            // Login Security
            'loginSec' => PlatformSettingsService::loginSecuritySettings(),
            // Queue
            'queueDriver' => config('queue.default'),
            'queuePending' => $this->queueCounts()['pending'],
            'queueFailed' => $this->queueCounts()['failed'],
            'queueLastJob' => $this->queueCounts()['last_job'],
            'queueLastFailed' => $this->queueCounts()['last_failed'],
            // Storage
            'storageDisk' => Setting::get('storage.disk', config('filesystems.default', 'public')),
            'storageMaxKb' => Setting::get('storage.max_size_kb', '10240'),
            'storageAllowedImages' => Setting::get('storage.allowed_images', 'jpeg,png,webp,gif'),
            'storageAllowedDocs' => Setting::get('storage.allowed_docs', 'pdf,doc,docx,xls,xlsx'),
            'storageResize' => Setting::get('storage.resize', '1'),
            'storageWebp' => Setting::get('storage.webp', '0'),
            'storageThumb' => Setting::get('storage.thumb', '1'),
            // Maps
            'mapsEnabled' => Setting::get('maps.enabled', '0'),
            'mapsGeocoding' => Setting::get('maps.geocoding', '0'),
            'mapsPlaces' => Setting::get('maps.places', '0'),
            'mapsMap' => Setting::get('maps.map', '0'),
            'mapsCountry' => Setting::get('maps.default_country', 'BD'),
            'mapsLat' => Setting::get('maps.default_lat', '23.8103'),
            'mapsLng' => Setting::get('maps.default_lng', '90.4125'),
            'mapsKeyMasked' => PlatformSettingsService::masked('maps.api_key'),
            // Notifications
            'notifEmail' => Setting::get('notifications.email_enabled', '1'),
            'notifSms' => Setting::get('notifications.sms_enabled', '1'),
            'notifQueue' => Setting::get('notifications.queue', '1'),
            'notifRetry' => Setting::get('notifications.retry', '3'),
            // Payment
            'payProvider' => Setting::get('payment.provider', ''),
            'payMode' => Setting::get('payment.mode', 'sandbox'),
            'payCurrency' => Setting::get('payment.currency', 'BDT'),
            'payEnabled' => Setting::get('payment.enabled', '0'),
            'payDefault' => Setting::get('payment.default_gateway', ''),
            'payKeyMasked' => PlatformSettingsService::masked('payment.api_key'),
            'paySecretMasked' => PlatformSettingsService::masked('payment.api_secret'),
            // AI - per-provider with generic fallback
            'aiEnabled' => Setting::get('ai.enabled', '0'),
            'aiProvider' => $aiProviderTmp = Setting::get('ai.provider', 'openai'),
            'aiModel' => Setting::get("ai.model_{$aiProviderTmp}") ?? Setting::get('ai.model', 'gpt-4o-mini'),
            'aiBaseUrl' => Setting::get("ai.base_url_{$aiProviderTmp}") ?? Setting::get('ai.base_url', 'https://api.openai.com/v1'),
            'aiKeyMasked' => ($tmpMask = PlatformSettingsService::masked("ai.api_key_{$aiProviderTmp}")) !== 'NOT CONFIGURED' ? $tmpMask : PlatformSettingsService::masked('ai.api_key'),
            // Branding
            'brandName' => Setting::get('brand.name', config('app.name')),
            'brandFooter' => Setting::get('brand.footer', ''),
            'brandPrimary' => Setting::get('brand.primary', ''),
            'brandSecondary' => Setting::get('brand.secondary', ''),
            'brandLogo' => Setting::get('brand.logo', ''),
            'brandFavicon' => Setting::get('brand.favicon', ''),
            // Maintenance
            'maintEnabled' => Setting::get('app.maintenance', '0'),
            'maintMessage' => Setting::get('app.maintenance_message', ''),
            'maintAllowAdmin' => Setting::get('app.maintenance_allow_admin', '1'),
            // Webhook/API
            'apiEnabled' => Setting::get('api.enabled', '0'),
            'apiBase' => Setting::get('api.base_url', ''),
            'webhookUrl' => Setting::get('webhook.url', ''),
            'webhookRetry' => Setting::get('webhook.retry', '3'),
            'webhookTimeout' => Setting::get('webhook.timeout', '30'),
            'webhookSecretMasked' => PlatformSettingsService::masked('webhook.secret'),
            // Messaging
            'whatsappStatus' => Setting::get('whatsapp.enabled', '0') === '1' ? 'Configured' : 'NOT CONFIGURED',
        ];
    }

    private function smsProviderStatus(): array
    {
        $provider = Setting::get('sms.provider', 'log');
        $enabled = Setting::get('sms.enabled', '0');
        $apiUrl = Setting::get('sms.api_url', '');
        $hasUrl = filled($apiUrl);
        $isHttps = str_starts_with($apiUrl, 'https://');

        $configState = match (true) {
            ! filled($apiUrl) && $provider === 'http' => 'Not Configured — API URL missing',
            $provider === 'log' => 'Configured (Log / Development)',
            filled($apiUrl) && $isHttps => 'Configuration Complete',
            filled($apiUrl) && ! $isHttps => 'Configuration Warning — HTTP (not HTTPS)',
            default => 'Not Configured',
        };

        $status = match (true) {
            $enabled !== '1' => 'DISABLED',
            $provider === 'log' => 'LOG (Development)',
            ! filled($apiUrl) => 'Configuration Error',
            $isHttps => 'HTTP (HTTPS)',
            default => 'HTTP',
        };

        return [
            'provider' => strtoupper($provider),
            'provider_raw' => $provider,
            'enabled_label' => $enabled === '1' ? 'ENABLED' : 'DISABLED',
            'config_state' => $configState,
            'status' => $status,
            'connection' => 'Not Tested',
            'delivery' => 'Not Tested',
            'is_https' => $isHttps,
            'has_url' => $hasUrl,
        ];
    }

    private function queueCounts(): array
    {
        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();
            $last = DB::table('jobs')->orderByDesc('created_at')->first();
            $lastFailed = DB::table('failed_jobs')->orderByDesc('failed_at')->first();
            return [
                'pending' => $pending,
                'failed' => $failed,
                'last_job' => $last?->created_at ? (string) $last->created_at : '—',
                'last_failed' => $lastFailed?->failed_at ? (string) $lastFailed->failed_at : '—',
            ];
        } catch (\Throwable) {
            return ['pending' => 0, 'failed' => 0, 'last_job' => '—', 'last_failed' => '—'];
        }
    }

    public function index(Request $request): View
    {
        return view('admin.platform-settings.index', $this->viewData());
    }

    // ── General
    public function updateGeneral(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:100'],
            'app_short_name' => ['nullable', 'string', 'max:20'],
            'app_url' => ['required', 'url', 'max:255'],
            'app_timezone' => ['required', 'string', 'max:50'],
            'app_country' => ['nullable', 'string', 'max:5'],
            'app_currency' => ['nullable', 'string', 'max:10'],
            'app_language' => ['required', 'in:en,bn,auto'],
            'app_date_format' => ['required', 'string', 'max:20'],
            'app_time_format' => ['required', 'string', 'max:20'],
            'app_pagination' => ['required', 'integer', 'min:5', 'max:100'],
            'app_contact_email' => ['nullable', 'email', 'max:255'],
            'app_support_phone' => ['nullable', 'string', 'max:30'],
            'app_support_url' => ['nullable', 'url', 'max:255'],
        ]);
        foreach (['app.name' => 'app_name', 'app.short_name' => 'app_short_name', 'app.url' => 'app_url', 'app.timezone' => 'app_timezone', 'app.country' => 'app_country', 'app.currency' => 'app_currency', 'app.language' => 'app_language', 'app.date_format' => 'app_date_format', 'app.time_format' => 'app_time_format', 'app.pagination' => 'app_pagination', 'app.contact_email' => 'app_contact_email', 'app.support_phone' => 'app_support_phone', 'app.support_url' => 'app_support_url'] as $k => $f) {
            PlatformSettingsService::set($k, $data[$f] ?? '', 'general');
        }
        return back()->with('status', 'General settings saved.');
    }

    // ── Email
    public function updateEmail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['required', 'in:none,tls,ssl'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:500'],
            'smtp_from_address' => ['required', 'email', 'max:255'],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
            'smtp_reply_to_name' => ['nullable', 'string', 'max:255'],
            'smtp_reply_to_email' => ['nullable', 'email', 'max:255'],
            'smtp_enabled' => ['nullable', 'in:0,1'],
            'smtp_queue' => ['nullable', 'in:0,1'],
            'smtp_retry_count' => ['required', 'integer', 'min:0', 'max:10'],
            'smtp_timeout' => ['required', 'integer', 'min:5', 'max:120'],
        ]);
        Setting::set('smtp.host', $data['smtp_host'] ?? '');
        Setting::set('smtp.port', (string) $data['smtp_port']);
        Setting::set('smtp.encryption', $data['smtp_encryption']);
        Setting::set('smtp.username', $data['smtp_username'] ?? '');
        if (filled($data['smtp_password'] ?? null) && $data['smtp_password'] !== '••••••••') {
            Setting::set('smtp.password', $data['smtp_password']);
            PlatformAuditLog::record('email', 'smtp.password', 'credential_changed');
        }
        Setting::set('smtp.from_address', $data['smtp_from_address']);
        Setting::set('smtp.from_name', $data['smtp_from_name'] ?? '');
        Setting::set('smtp.reply_to_name', $data['smtp_reply_to_name'] ?? '');
        Setting::set('smtp.reply_to_email', $data['smtp_reply_to_email'] ?? '');
        Setting::set('smtp.enabled', $data['smtp_enabled'] ?? '1');
        Setting::set('smtp.queue', $data['smtp_queue'] ?? '1');
        Setting::set('smtp.retry_count', (string) $data['smtp_retry_count']);
        Setting::set('smtp.timeout', (string) $data['smtp_timeout']);
        PlatformAuditLog::record('email', 'smtp.host', 'updated');
        return back()->with('status', 'Email settings saved.');
    }

    public function testEmail(Request $request): RedirectResponse
    {
        $data = $request->validate(['test_email' => ['required', 'email']]);
        $host = Setting::get('smtp.host', '');
        if (! filled($host)) {
            return back()->withErrors(['smtp_test' => 'SMTP not configured. Save settings first.']);
        }
        $enc = Setting::get('smtp.encryption', 'tls');
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => (int) Setting::get('smtp.port', 587),
            'mail.mailers.smtp.username' => Setting::get('smtp.username', ''),
            'mail.mailers.smtp.password' => Setting::get('smtp.password', ''),
            'mail.mailers.smtp.encryption' => $enc === 'none' ? null : $enc,
            'mail.from.address' => Setting::get('smtp.from_address', config('mail.from.address')),
            'mail.from.name' => Setting::get('smtp.from_name', config('mail.from.name')),
        ]);
        try {
            Mail::raw('Test email from '.config('app.name').' — SMTP verification.', function ($m) use ($data) {
                $m->to($data['test_email'])->subject('Test Email — '.config('app.name'));
            });
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            // sanitize: never expose password
            $msg = str_replace((string) Setting::get('smtp.password', ''), '***', $msg);
            return back()->withErrors(['smtp_test' => 'Failed: '.substr($msg, 0, 300)]);
        }
        PlatformAuditLog::record('email', 'smtp.test', 'test_sent', ['to' => $data['test_email']]);
        return back()->with('status', 'Test email sent to '.$data['test_email']);
    }

    // ── SMS
    public function updateSms(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sms_provider' => ['required', 'in:log,http'],
            'sms_type' => ['required', 'in:log,http'],
            'sms_api_url' => ['nullable', 'url', 'max:500'],
            'sms_http_method' => ['required', 'in:GET,POST'],
            'sms_api_key' => ['nullable', 'string', 'max:500'],
            'sms_api_secret' => ['nullable', 'string', 'max:500'],
            'sms_username' => ['nullable', 'string', 'max:255'],
            'sms_password' => ['nullable', 'string', 'max:500'],
            'sms_sender_id' => ['nullable', 'string', 'max:50'],
            'sms_sender_name' => ['nullable', 'string', 'max:100'],
            'sms_auth_type' => ['required', 'in:none,basic,bearer,apikey'],
            'sms_message_param' => ['required', 'string', 'max:50'],
            'sms_phone_param' => ['required', 'string', 'max:50'],
            'sms_success_condition' => ['nullable', 'string', 'max:255'],
            'sms_enabled' => ['nullable', 'in:0,1'],
        ]);

        // Production-safe validation: cannot enable incomplete HTTP configuration
        $wantEnabled = ($data['sms_enabled'] ?? '0') === '1';
        if ($wantEnabled) {
            $provider = $data['sms_provider'];
            if ($provider === 'http') {
                if (! filled($data['sms_api_url'])) {
                    return back()->withErrors(['sms_enabled' => 'Cannot enable SMS: API URL is missing. Select Log provider or provide a valid HTTPS URL.'])->withInput();
                }
                // HTTPS preferred for production — reject plain http on enable (allow http only for local/testing via manual warning)
                $url = $data['sms_api_url'];
                if (! str_starts_with($url, 'https://')) {
                    return back()->withErrors(['sms_api_url' => 'API URL must use HTTPS for production. Use https:// or disable SMS.'])->withInput();
                }
                // Credentials: at least one of api_key / api_secret / password required for http provider
                $hasCred = filled($data['sms_api_key'] ?? null) || filled(Setting::get('sms.api_key')) || filled($data['sms_api_secret'] ?? null) || filled(Setting::get('sms.api_secret')) || filled($data['sms_password'] ?? null) || filled(Setting::get('sms.password'));
                if (! $hasCred && ! filled($data['sms_api_key'] ?? null) && ! filled(Setting::get('sms.api_key'))) {
                    // Allow log-like http with api_key empty only if provider explicitly allows — but warn
                    // Keep soft: require api_key for http when enabled
                    if (! filled($data['sms_api_key'] ?? null) && ! filled(Setting::get('sms.api_key'))) {
                        return back()->withErrors(['sms_api_key' => 'Cannot enable SMS: API credential is missing.'])->withInput();
                    }
                }
                if (! filled($data['sms_sender_id'] ?? null) && ! filled(Setting::get('sms.sender_id'))) {
                    // Soft warning — some providers allow empty sender, keep as error only if strict
                    // Allow — but provider status will show incomplete
                }
            }
        }

        Setting::set('sms.provider', $data['sms_provider']);
        Setting::set('sms.type', $data['sms_type']);
        Setting::set('sms.api_url', $data['sms_api_url'] ?? '');
        Setting::set('sms.http_method', strtoupper($data['sms_http_method']));
        if (filled($data['sms_api_key'] ?? null) && $data['sms_api_key'] !== '••••••••') {
            Setting::set('sms.api_key', $data['sms_api_key']);
            PlatformAuditLog::record('sms', 'sms.api_key', 'credential_changed');
        }
        if (filled($data['sms_api_secret'] ?? null) && $data['sms_api_secret'] !== '••••••••') {
            Setting::set('sms.api_secret', $data['sms_api_secret']);
            PlatformAuditLog::record('sms', 'sms.api_secret', 'credential_changed');
        }
        Setting::set('sms.username', $data['sms_username'] ?? '');
        if (filled($data['sms_password'] ?? null) && $data['sms_password'] !== '••••••••') {
            Setting::set('sms.password', $data['sms_password']);
            PlatformAuditLog::record('sms', 'sms.password', 'credential_changed');
        }
        Setting::set('sms.sender_id', $data['sms_sender_id'] ?? '');
        Setting::set('sms.sender_name', $data['sms_sender_name'] ?? '');
        Setting::set('sms.auth_type', $data['sms_auth_type']);
        Setting::set('sms.message_param', $data['sms_message_param']);
        Setting::set('sms.phone_param', $data['sms_phone_param']);
        Setting::set('sms.success_condition', $data['sms_success_condition'] ?? '');
        // Disabled keeps credentials encrypted for re-enable (do not delete)
        Setting::set('sms.enabled', $data['sms_enabled'] ?? '0');
        PlatformAuditLog::record('sms', 'sms.provider', 'updated');
        return back()->with('status', 'SMS settings saved.');
    }

    public function testSmsConnection(Request $request): RedirectResponse
    {
        // Lightweight connectivity check — does NOT send OTP/SMS, only validates URL reachable
        $provider = Setting::get('sms.provider', 'log');
        if ($provider === 'log') {
            return back()->with('status', 'Provider connection: LOG — no external call needed. Configuration is local.');
        }
        $apiUrl = Setting::get('sms.api_url', '');
        if (! filled($apiUrl)) {
            return back()->withErrors(['sms_test' => 'Provider connection failed. Please verify your provider configuration — API URL missing.']);
        }
        if (! str_starts_with($apiUrl, 'https://') && ! str_starts_with($apiUrl, 'http://')) {
            return back()->withErrors(['sms_test' => 'Provider connection failed. Please verify your provider configuration — invalid URL.']);
        }
        try {
            // Safe HEAD/ping — 5s timeout, no credentials in URL, verify TLS
            $resp = Http::timeout(5)->withOptions(['verify' => true])->head($apiUrl);
            // Any 2xx-4xx means host reachable (auth may be 401 without credentials — still reachable)
            if ($resp->status() >= 200 && $resp->status() < 500) {
                PlatformAuditLog::record('sms', 'sms.test', 'connection_verified');
                return back()->with('status', 'Provider connection successful.');
            }
            throw new \RuntimeException('HTTP '.$resp->status());
        } catch (\Throwable $e) {
            $msg = substr($e->getMessage(), 0, 200);
            foreach (['sms.api_key', 'sms.api_secret', 'sms.password'] as $sk) {
                $msg = str_replace((string) Setting::get($sk, ''), '***', $msg);
            }
            \Illuminate\Support\Facades\Log::warning('sms.connection_test_failed', ['error' => $msg, 'url' => substr($apiUrl, 0, 80)]);
            return back()->withErrors(['sms_test' => 'Provider connection failed. Please verify your provider configuration.']);
        }
    }

    public function testSms(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'test_phone' => ['required', 'string', 'max:20', 'regex:/^\+[1-9]\d{7,14}$/'],
            'test_message' => ['required', 'string', 'max:500'],
            'confirm_send' => ['required', 'accepted'],
        ], [
            'test_phone.regex' => 'Test phone must be E.164 format, e.g. +88017XXXXXXXX.',
            'confirm_send.accepted' => 'Please confirm that this will send a real SMS and may consume credits.',
        ]);
        // Validate E.164 via normalizer for stricter check
        $normalized = \App\Support\PhoneNormalizer::toE164($data['test_phone']);
        if ($normalized === null) {
            return back()->withErrors(['sms_test' => 'Invalid phone number format. Use E.164 e.g. +88017XXXXXXXX.']);
        }
        // Never create real OTP — fixed test content
        $testMessage = 'Accumen AI test SMS. Your SMS provider configuration is working. ['.now()->format('H:i').']';

        $provider = Setting::get('sms.provider', 'log');
        if ($provider === 'log') {
            try {
                app(LogSmsProvider::class)->send($normalized, $testMessage);
                PlatformAuditLog::record('sms', 'sms.test', 'test_sent');
                return back()->with('status', 'Test SMS logged (Log provider active). Check logs.');
            } catch (\Throwable $e) {
                return back()->withErrors(['sms_test' => substr($e->getMessage(), 0, 300)]);
            }
        }
        if (! filled(Setting::get('sms.api_url'))) {
            return back()->withErrors(['sms_test' => 'PROVIDER NOT CONFIGURED — API URL missing.']);
        }
        try {
            $result = app(HttpSmsProvider::class)->send($normalized, $testMessage);
            PlatformAuditLog::record('sms', 'sms.test', 'test_sent');
            return back()->with('status', 'Test SMS sent. Provider ID: '.($result['message_id'] ?? '—'));
        } catch (\Throwable $e) {
            $msg = substr($e->getMessage(), 0, 300);
            // strip secrets — never expose in browser
            foreach (['sms.api_key', 'sms.api_secret', 'sms.password'] as $sk) {
                $msg = str_replace((string) Setting::get($sk, ''), '***', $msg);
            }
            \Illuminate\Support\Facades\Log::warning('sms.test_send_failed', ['error' => $msg, 'phone' => substr($normalized, 0, 4).'***']);
            return back()->withErrors(['sms_test' => 'SMS provider request failed. Please verify the provider configuration.']);
        }
    }

    // ── OTP
    public function updateOtp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email_otp_enabled' => ['required', 'in:0,1'],
            'email_otp_length' => ['required', 'integer', 'min:4', 'max:8'],
            'email_otp_expiry' => ['required', 'integer', 'min:1', 'max:60'],
            'email_otp_max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'email_otp_resend_cooldown' => ['required', 'integer', 'min:10', 'max:600'],
            'email_otp_max_resend' => ['required', 'integer', 'min:1', 'max:10'],
            'sms_otp_enabled' => ['required', 'in:0,1'],
            'sms_otp_length' => ['required', 'integer', 'min:4', 'max:8'],
            'sms_otp_expiry' => ['required', 'integer', 'min:1', 'max:60'],
            'sms_otp_max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'sms_otp_resend_cooldown' => ['required', 'integer', 'min:10', 'max:600'],
            'sms_otp_max_resend' => ['required', 'integer', 'min:1', 'max:10'],
        ]);
        foreach ($data as $k => $v) {
            // Preserve email_otp / sms_otp prefix: email_otp_enabled → email_otp.enabled
            $key = preg_replace('/^(email_otp|sms_otp)_/', '$1.', $k);
            Setting::set($key, (string) $v);
        }
        PlatformAuditLog::record('otp', 'otp.settings', 'updated');
        return back()->with('status', 'OTP settings saved.');
    }

    // ── 2FA
    public function updateTwoFactor(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'allow_totp' => ['required', 'in:0,1'],
            'allow_email' => ['required', 'in:0,1'],
            'allow_sms' => ['required', 'in:0,1'],
            'preferred' => ['required', 'in:totp,sms,email'],
            'allow_user_change' => ['required', 'in:0,1'],
            'require_verified_email' => ['required', 'in:0,1'],
            'require_verified_phone' => ['required', 'in:0,1'],
            'max_failed' => ['required', 'integer', 'min:1', 'max:20'],
            'challenge_expiry' => ['required', 'integer', 'min:1', 'max:60'],
        ]);
        Setting::set('2fa.allow_totp', $data['allow_totp']);
        Setting::set('2fa.allow_email', $data['allow_email']);
        Setting::set('2fa.allow_sms', $data['allow_sms']);
        Setting::set('2fa.preferred', $data['preferred']);
        Setting::set('2fa.allow_user_change', $data['allow_user_change']);
        Setting::set('2fa.require_verified_email', $data['require_verified_email']);
        Setting::set('2fa.require_verified_phone', $data['require_verified_phone']);
        Setting::set('2fa.max_failed', (string) $data['max_failed']);
        Setting::set('2fa.challenge_expiry', (string) $data['challenge_expiry']);
        PlatformAuditLog::record('security', '2fa.settings', 'updated');
        return back()->with('status', '2FA settings saved.');
    }

    // ── Login Security
    public function updateLoginSecurity(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login_max_attempts' => ['required', 'integer', 'min:3', 'max:20'],
            'login_lockout' => ['required', 'integer', 'min:1', 'max:120'],
            'login_session' => ['required', 'integer', 'min:15', 'max:1440'],
            'login_remember' => ['required', 'in:0,1'],
            'login_2fa_lifetime' => ['required', 'integer', 'min:1', 'max:30'],
            'password_min' => ['required', 'integer', 'min:8', 'max:32'],
        ]);
        Setting::set('login.max_attempts', (string) $data['login_max_attempts']);
        Setting::set('login.lockout_duration', (string) $data['login_lockout']);
        Setting::set('login.session_lifetime', (string) $data['login_session']);
        Setting::set('login.remember_me', $data['login_remember']);
        Setting::set('login.2fa_challenge_lifetime', (string) $data['login_2fa_lifetime']);
        Setting::set('password.min_length', (string) $data['password_min']);
        PlatformAuditLog::record('security', 'login.settings', 'updated');
        return back()->with('status', 'Login security saved.');
    }

    // ── Queue health
    public function queueHealth(Request $request): RedirectResponse
    {
        $checks = [];
        try { DB::table('jobs')->limit(1)->get(); $checks[] = 'Database queue reachable: OK'; } catch (\Throwable $e) { $checks[] = 'Database queue reachable: FAILED'; }
        try { DB::table('failed_jobs')->limit(1)->get(); $checks[] = 'Failed jobs table: OK'; } catch (\Throwable) { $checks[] = 'Failed jobs table: MISSING'; }
        $checks[] = 'Queue driver: '.config('queue.default');
        $checks[] = 'Default queue: '.config('queue.connections.database.queue', 'default');
        $checks[] = 'Delivery queue: '.config('notifications.delivery.queue', 'notifications');
        return back()->with('status', implode(' | ', $checks));
    }

    // ── Payment
    public function updatePayment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'payment_provider' => ['nullable', 'string', 'max:100'],
            'payment_mode' => ['required', 'in:sandbox,live'],
            'payment_currency' => ['required', 'string', 'max:10'],
            'payment_enabled' => ['required', 'in:0,1'],
            'payment_api_key' => ['nullable', 'string', 'max:500'],
            'payment_api_secret' => ['nullable', 'string', 'max:500'],
        ]);
        Setting::set('payment.provider', $data['payment_provider'] ?? '');
        Setting::set('payment.mode', $data['payment_mode']);
        Setting::set('payment.currency', $data['payment_currency']);
        Setting::set('payment.enabled', $data['payment_enabled']);
        if (filled($data['payment_api_key'] ?? null) && $data['payment_api_key'] !== '••••••••') {
            Setting::set('payment.api_key', $data['payment_api_key']);
            PlatformAuditLog::record('payment', 'payment.api_key', 'credential_changed');
        }
        if (filled($data['payment_api_secret'] ?? null) && $data['payment_api_secret'] !== '••••••••') {
            Setting::set('payment.api_secret', $data['payment_api_secret']);
            PlatformAuditLog::record('payment', 'payment.api_secret', 'credential_changed');
        }
        PlatformAuditLog::record('payment', 'payment.provider', 'updated');
        return back()->with('status', 'Payment settings saved.');
    }

    // ── Storage
    public function updateStorage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'storage_disk' => ['required', 'in:local,public,s3'],
            'storage_max' => ['required', 'integer', 'min:100', 'max:102400'],
            'storage_resize' => ['required', 'in:0,1'],
            'storage_webp' => ['required', 'in:0,1'],
            'storage_thumb' => ['required', 'in:0,1'],
        ]);
        Setting::set('storage.disk', $data['storage_disk']);
        Setting::set('storage.max_size_kb', (string) $data['storage_max']);
        Setting::set('storage.resize', $data['storage_resize']);
        Setting::set('storage.webp', $data['storage_webp']);
        Setting::set('storage.thumb', $data['storage_thumb']);
        PlatformAuditLog::record('storage', 'storage.disk', 'updated');
        return back()->with('status', 'Storage settings saved.');
    }

    // ── Maps
    public function updateMaps(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'maps_enabled' => ['required', 'in:0,1'],
            'maps_geocoding' => ['required', 'in:0,1'],
            'maps_places' => ['required', 'in:0,1'],
            'maps_lat' => ['required', 'numeric', 'between:-90,90'],
            'maps_lng' => ['required', 'numeric', 'between:-180,180'],
            'maps_api_key' => ['nullable', 'string', 'max:500'],
        ]);
        Setting::set('maps.enabled', $data['maps_enabled']);
        Setting::set('maps.geocoding', $data['maps_geocoding']);
        Setting::set('maps.places', $data['maps_places']);
        Setting::set('maps.default_lat', (string) $data['maps_lat']);
        Setting::set('maps.default_lng', (string) $data['maps_lng']);
        if (filled($data['maps_api_key'] ?? null) && $data['maps_api_key'] !== '••••••••') {
            Setting::set('maps.api_key', $data['maps_api_key']);
            PlatformAuditLog::record('maps', 'maps.api_key', 'credential_changed');
        }
        PlatformAuditLog::record('maps', 'maps.enabled', 'updated');
        return back()->with('status', 'Maps settings saved.');
    }

    // ── Notifications
    public function updateNotifications(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'notif_email' => ['required', 'in:0,1'],
            'notif_sms' => ['required', 'in:0,1'],
            'notif_queue' => ['required', 'in:0,1'],
            'notif_retry' => ['required', 'integer', 'min:0', 'max:10'],
        ]);
        Setting::set('notifications.email_enabled', $data['notif_email']);
        Setting::set('notifications.sms_enabled', $data['notif_sms']);
        Setting::set('notifications.queue', $data['notif_queue']);
        Setting::set('notifications.retry', (string) $data['notif_retry']);
        PlatformAuditLog::record('notifications', 'notifications.settings', 'updated');
        return back()->with('status', 'Notification settings saved.');
    }

    // ── AI - multiple providers (openai,anthropic,gemini,groq,custom)
    public function updateAi(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ai_enabled' => ['required', 'in:0,1'],
            'ai_provider' => ['required', 'in:openai,anthropic,gemini,groq,custom'],
            'ai_model' => ['required', 'string', 'max:100'],
            'ai_base_url' => ['nullable', 'url', 'max:255'],
            'ai_api_key' => ['nullable', 'string', 'max:1000'],
        ]);
        $provider = $data['ai_provider'];
        Setting::set('ai.enabled', $data['ai_enabled']);
        Setting::set('ai.provider', $provider);
        // generic fallback for backwards compat
        Setting::set('ai.model', $data['ai_model']);
        Setting::set('ai.base_url', $data['ai_base_url'] ?? '');
        // per-provider storage
        Setting::set("ai.model_{$provider}", $data['ai_model']);
        Setting::set("ai.base_url_{$provider}", $data['ai_base_url'] ?? '');
        $apiKeyKey = "ai.api_key_{$provider}";
        if (filled($data['ai_api_key'] ?? null) && $data['ai_api_key'] !== '••••••••') {
            Setting::set($apiKeyKey, $data['ai_api_key']);
            // also keep generic for legacy consumers
            Setting::set('ai.api_key', $data['ai_api_key']);
            PlatformAuditLog::record('ai', $apiKeyKey, 'credential_changed');
        }
        PlatformAuditLog::record('ai', 'ai.provider', 'updated');
        return back()->with('status', 'AI settings saved.');
    }

    // ── API/Webhook
    public function updateApi(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'api_enabled' => ['required', 'in:0,1'],
            'webhook_url' => ['nullable', 'url', 'max:500'],
            'webhook_secret' => ['nullable', 'string', 'max:500'],
            'webhook_retry' => ['required', 'integer', 'min:0', 'max:10'],
            'webhook_timeout' => ['required', 'integer', 'min:5', 'max:120'],
        ]);
        Setting::set('api.enabled', $data['api_enabled']);
        Setting::set('webhook.url', $data['webhook_url'] ?? '');
        if (filled($data['webhook_secret'] ?? null) && $data['webhook_secret'] !== '••••••••') {
            Setting::set('webhook.secret', $data['webhook_secret']);
            PlatformAuditLog::record('api', 'webhook.secret', 'credential_changed');
        }
        Setting::set('webhook.retry', (string) $data['webhook_retry']);
        Setting::set('webhook.timeout', (string) $data['webhook_timeout']);
        PlatformAuditLog::record('api', 'api.enabled', 'updated');
        return back()->with('status', 'API settings saved.');
    }

    // ── Branding
    public function updateBranding(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'brand_name' => ['required', 'string', 'max:100'],
            'brand_footer' => ['nullable', 'string', 'max:500'],
            'brand_primary' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'brand_secondary' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'brand_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,gif', 'max:2048'],
            'brand_favicon' => ['nullable', 'image', 'mimes:png,jpg,jpeg,ico,webp', 'max:512'],
        ]);
        Setting::set('brand.name', $data['brand_name']);
        Setting::set('brand.footer', $data['brand_footer'] ?? '');
        if (array_key_exists('brand_primary', $data)) {
            Setting::set('brand.primary', $data['brand_primary'] ?? '');
        }
        if (array_key_exists('brand_secondary', $data)) {
            Setting::set('brand.secondary', $data['brand_secondary'] ?? '');
        }
        // Logo upload: validate then store, preserve old until success
        if ($request->hasFile('brand_logo')) {
            $file = $request->file('brand_logo');
            $old = Setting::get('brand.logo', '');
            $path = $file->storeAs('branding', \Illuminate\Support\Str::uuid()->toString().'.'.$file->getClientOriginalExtension(), ['disk' => 'public']);
            if ($path) {
                Setting::set('brand.logo', $path);
                PlatformAuditLog::record('branding', 'brand.logo', 'updated');
                if (filled($old)) {
                    try { \Illuminate\Support\Facades\Storage::disk('public')->delete($old); } catch (\Throwable $e) {}
                }
            }
        }
        if ($request->hasFile('brand_favicon')) {
            $file = $request->file('brand_favicon');
            $old = Setting::get('brand.favicon', '');
            $path = $file->storeAs('branding', \Illuminate\Support\Str::uuid()->toString().'.'.$file->getClientOriginalExtension(), ['disk' => 'public']);
            if ($path) {
                Setting::set('brand.favicon', $path);
                PlatformAuditLog::record('branding', 'brand.favicon', 'updated');
                if (filled($old)) {
                    try { \Illuminate\Support\Facades\Storage::disk('public')->delete($old); } catch (\Throwable $e) {}
                }
            }
        }
        PlatformAuditLog::record('branding', 'brand.name', 'updated');
        return back()->with('status', 'Branding saved.');
    }

    // ── Maintenance
    public function updateMaintenance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'maint_enabled' => ['required', 'in:0,1'],
            'maint_message' => ['nullable', 'string', 'max:500'],
            'maint_allow_admin' => ['required', 'in:0,1'],
        ]);
        Setting::set('app.maintenance', $data['maint_enabled']);
        Setting::set('app.maintenance_message', $data['maint_message'] ?? '');
        Setting::set('app.maintenance_allow_admin', $data['maint_allow_admin']);
        PlatformAuditLog::record('maintenance', 'app.maintenance', 'updated');
        return back()->with('status', 'Maintenance settings saved.');
    }
}

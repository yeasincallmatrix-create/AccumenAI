<?php

namespace App\Services\Notification;

use App\Models\Institute;
use App\Models\InstituteSetting;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/**
 * Resolves the SMTP configuration a notification email should use.
 *
 * Priority: per-institute SMTP (institute_settings.smtp_*) → global platform
 * SMTP (settings.smtp.*) → null (Laravel env default mailer). The per-institute
 * password is stored encrypted (smtp_password_enc) and decrypted on read with a
 * plaintext fallback. Secrets are never echoed back to any form.
 */
class ResolveMailer
{
    /**
     * @return array{host: string, port: int, username: string, password: string, encryption: string|null, from_address: string, from_name: string}|null
     */
    public function resolve(?int $instituteId): ?array
    {
        $settings = $instituteId !== null
            ? InstituteSetting::query()->where('institute_id', $instituteId)->first()
            : null;

        $fromAddress = Setting::get('smtp.from_address', config('mail.from.address'));
        $fromName = Setting::get('smtp.from_name', config('mail.from.name'));

        if ($settings !== null && filled($settings->smtp_host)) {
            return [
                'host' => $settings->smtp_host,
                'port' => (int) ($settings->smtp_port ?: 587),
                'username' => (string) $settings->smtp_username,
                'password' => $this->decrypt($settings->smtp_password_enc),
                'encryption' => $this->normalizeEncryption($settings->smtp_encryption),
                'from_address' => $fromAddress,
                'from_name' => $fromName,
            ];
        }

        $host = Setting::get('smtp.host');
        if (filled($host)) {
            return [
                'host' => $host,
                'port' => (int) Setting::get('smtp.port', 587),
                'username' => (string) Setting::get('smtp.username', ''),
                'password' => (string) Setting::get('smtp.password', ''),
                'encryption' => $this->normalizeEncryption(Setting::get('smtp.encryption', 'tls')),
                'from_address' => $fromAddress,
                'from_name' => $fromName,
            ];
        }

        return null;
    }

    public function normalizeEncryption(mixed $encryption): ?string
    {
        $encryption = strtolower((string) $encryption);

        return in_array($encryption, ['ssl', 'tls'], true) ? $encryption : null;
    }

    private function decrypt(?string $value): string
    {
        if (! filled($value)) {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }
}

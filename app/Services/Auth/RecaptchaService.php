<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Google reCAPTCHA v2 (checkbox) verification for the login forms.
 *
 * The captcha is opt-in: it is enforced only when both RECAPTCHA_SITE_KEY and
 * RECAPTCHA_SECRET_KEY are configured, so local/test environments keep working
 * without keys while production can enable it by filling those two values.
 */
class RecaptchaService
{
    public static function enabled(): bool
    {
        return filled(self::siteKey()) && filled(self::secretKey());
    }

    public static function siteKey(): ?string
    {
        return config('services.recaptcha.site_key');
    }

    public static function secretKey(): ?string
    {
        return config('services.recaptcha.secret_key');
    }

    /**
     * '3' = invisible score-based widget, '2' = legacy checkbox widget.
     */
    public static function version(): string
    {
        return (string) config('services.recaptcha.version', '3') === '2' ? '2' : '3';
    }

    public static function isV3(): bool
    {
        return self::version() === '3';
    }

    /**
     * Verify a reCAPTCHA response token against Google's siteverify endpoint.
     * Fails closed: any transport/protocol problem is treated as a failure.
     */
    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (! self::enabled()) {
            return true;
        }

        if (! is_string($token) || trim($token) === '') {
            return false;
        }

        try {
            $payload = array_filter([
                'secret' => self::secretKey(),
                'response' => $token,
                'remoteip' => $remoteIp,
            ], static fn ($value) => $value !== null && $value !== '');

            $response = Http::timeout((float) config('services.recaptcha.timeout', 5))
                ->asForm()
                ->post((string) config('services.recaptcha.verify_url'), $payload);

            if (! $response->successful()) {
                report(new \RuntimeException('reCAPTCHA siteverify returned HTTP '.$response->status()));

                return false;
            }

            if (! (bool) ($response->json('success') ?? false)) {
                return false;
            }

            // v3 only: reject low-trust (bot) scores. v2 responses carry no score.
            if (self::isV3()) {
                $score = $response->json('score');
                if ($score === null || (float) $score < (float) config('services.recaptcha.min_score', 0.5)) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Enforce the captcha for the current login request.
     *
     * @throws ValidationException when the token is missing or rejected
     */
    public function assertValid(Request $request): void
    {
        if (! self::enabled()) {
            return;
        }

        $token = $request->input('g-recaptcha-response');

        if (! is_string($token) || trim($token) === '') {
            throw ValidationException::withMessages([
                'captcha' => __('auth.captcha_missing'),
            ]);
        }

        if ($this->verify($token, $request->ip())) {
            return;
        }

        throw ValidationException::withMessages([
            'captcha' => __('auth.captcha_failed'),
        ]);
    }
}

<?php

namespace App\Services\Geo;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Detect which country a guest is browsing from.
 *
 * Resolution order (first hit wins):
 *   1. CDN/proxy header (CF-IPCountry behind Cloudflare, X-Country-Code
 *      behind a custom proxy) — no network call, authoritative.
 *   2. Session cookie from an earlier successful detection.
 *   3. IP geolocation HTTP lookup — cached for `visitor_geo.cache_minutes`,
 *      skipped for private/reserved IPs and during tests/console.
 *   4. `visitor_geo.fallback` (Bangladesh by default).
 *
 * The winning code is memoised on the current request so rendering the
 * landing page (home route + pricing block) only ever resolves once.
 */
class VisitorCountryResolver
{
    public const SESSION_KEY = 'visitor_country';

    private const ATTR_KEY = 'visitor_country_resolved';

    public function resolve(): string
    {
        $request = request();
        if ($request instanceof Request && $request->attributes->has(self::ATTR_KEY)) {
            return (string) $request->attributes->get(self::ATTR_KEY);
        }

        $code = $this->normalize($this->fromHeaders($request))
            ?? $this->normalize($this->fromSession())
            ?? $this->normalize($this->fromIp($request));

        $code ??= $this->normalize(config('visitor_geo.fallback')) ?? 'BD';

        if ($request instanceof Request) {
            $request->attributes->set(self::ATTR_KEY, $code);
        }
        $this->remember($code);

        return $code;
    }

    private function fromHeaders(?Request $request): ?string
    {
        if (! $request instanceof Request) {
            return null;
        }

        foreach ((array) config('visitor_geo.headers', []) as $header) {
            $code = $this->normalize($request->header((string) $header));
            if ($code !== null) {
                return $code;
            }
        }

        return null;
    }

    private function fromSession(): ?string
    {
        if ($this->isCli()) {
            return null;
        }

        return session(self::SESSION_KEY);
    }

    private function fromIp(?Request $request): ?string
    {
        if (! (bool) config('visitor_geo.enabled', true)) {
            return null;
        }

        // Never hit the network from tests or the CLI.
        if ($this->isCli() || app()->runningUnitTests()) {
            return null;
        }

        $ip = $this->normalizeIp($request?->ip());
        if ($ip === null || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        $cacheKey = 'visitor_country:'.md5($ip);
        $cached = Cache::get($cacheKey, false);
        if ($cached !== false) {
            return $cached === '' ? null : (string) $cached;
        }

        $code = $this->lookup($ip);

        $minutes = $code !== null
            ? (int) config('visitor_geo.cache_minutes', 10080)
            : (int) config('visitor_geo.failure_cache_minutes', 60);
        Cache::put($cacheKey, $code ?? '', max(1, $minutes) * 60);

        return $code;
    }

    private function lookup(string $ip): ?string
    {
        $template = (string) config('visitor_geo.url', '');
        if ($template === '') {
            return null;
        }

        $url = str_replace('{ip}', rawurlencode($ip), $template);

        try {
            $response = Http::timeout((float) config('visitor_geo.timeout', 1.5))->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $json = $response->json();
        if (is_array($json)) {
            return $this->normalize($json['country_code'] ?? $json['countryCode'] ?? $json['country'] ?? null);
        }

        return $this->normalize($response->body());
    }

    private function remember(string $code): void
    {
        if ($this->isCli()) {
            return;
        }

        session([self::SESSION_KEY => $code]);
    }

    /**
     * Real CLI (artisan/queue) — feature tests also run on the cli SAPI but
     * must keep using the session, so they are excluded here.
     */
    private function isCli(): bool
    {
        return app()->runningInConsole() && ! app()->runningUnitTests();
    }

    private function normalize(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = reset($value) ?: null;
        }
        if (! is_string($value)) {
            return null;
        }

        $code = strtoupper(trim($value));
        if (! preg_match('/^[A-Z]{2}$/', $code)) {
            return null;
        }

        // CF-IPCountry uses XX/T1 for unknown tor traffic; ZZ is unused.
        return in_array($code, ['XX', 'T1', 'ZZ'], true) ? null : $code;
    }

    private function normalizeIp(?string $ip): ?string
    {
        if (! is_string($ip) || $ip === '') {
            return null;
        }

        // Apache/PHP can report IPv4-mapped IPv6 addresses.
        if (str_starts_with($ip, '::ffff:')) {
            $ip = substr($ip, 7);
        }

        return $ip;
    }
}

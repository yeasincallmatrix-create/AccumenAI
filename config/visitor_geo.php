<?php

/*
|--------------------------------------------------------------------------
| Visitor geolocation
|--------------------------------------------------------------------------
|
| Used to detect which country a guest is browsing from so the landing
| page can show the right market (Bangladesh ৳ vs Global $) and resolve
| the country-specific home page.
|
| Resolution order:
|   1. CDN/proxy header (CF-IPCountry behind Cloudflare, X-Country-Code
|      behind a custom proxy) — no network call.
|   2. Session cookie from a previous successful lookup.
|   3. IP geolocation HTTP lookup (cached, skipped for private IPs and
|      during tests/console).
|   4. Fallback country below.
|
*/
return [

    'enabled' => (bool) env('VISITOR_GEO_ENABLED', true),

    // Header names checked (in order) for a CDN-provided ISO2 country.
    'headers' => ['CF-IPCountry', 'Cloudflare-IPCountry', 'X-Country-Code'],

    // {ip} is replaced with the visitor IP. Must return the ISO2 code
    // either as plain text or inside a JSON payload.
    'url' => env('VISITOR_GEO_URL', 'https://ipapi.co/{ip}/country/'),

    'timeout' => (float) env('VISITOR_GEO_TIMEOUT', 1.5),

    // Successful lookups are cached this long (minutes) — 7 days.
    'cache_minutes' => (int) env('VISITOR_GEO_CACHE_MINUTES', 10080),

    // Failed lookups are negatively cached this long (minutes).
    'failure_cache_minutes' => (int) env('VISITOR_GEO_FAILURE_CACHE_MINUTES', 60),

    // Used when no header, cache entry or IP lookup yields a country.
    'fallback' => env('VISITOR_GEO_FALLBACK', 'BD'),

];

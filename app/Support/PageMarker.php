<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Route;
use RuntimeException;

/**
 * Temporary DEV tool — assigns a unique 2-3 digit number to every page and
 * every popup (modal). The key -> number mapping is persisted to
 * storage/app/page-markers.json so running `grep`/read against that file lets
 * you resolve any badge number back to its route name / modal id instantly.
 *
 * Global on/off switch is the platform-wide `dev.page_marker_enabled` setting
 * ('1' = on, default). The toggle button lives in the admin navbar
 * (layouts/admin.blade.php) and posts to the `dev.page-marker.toggle` route.
 *
 * DELETE once development is done: remove this class, the `dev/page-marker`
 * and `dev/page-marker/toggle` routes (routes/web.php),
 * `partials/page_marker.blade.php`, the toggle button + both layout includes,
 * and storage/app/page-markers.json.
 */
final class PageMarker
{
    private const STORAGE = 'page-markers.json';

    private const SETTING_KEY = 'dev.page_marker_enabled';

    private const PAGE_START = 10;

    private const POPUP_START = 100;

    private static ?bool $enabled = null;

    private static ?array $registry = null;

    /**
     * Whether the Page Marker system is switched on ('1' by default — the
     * toggle route flips Setting::set dev.page_marker_enabled to '0'/'1').
     */
    public static function enabled(): bool
    {
        if (self::$enabled !== null) {
            return self::$enabled;
        }

        try {
            self::$enabled = Setting::get(self::SETTING_KEY, '1') !== '0';
        } catch (\Throwable) {
            self::$enabled = true;
        }

        return self::$enabled;
    }

    /**
     * Flip the global switch. Returns the new state.
     */
    public static function toggle(): bool
    {
        $enabled = ! self::enabled();

        Setting::set(self::SETTING_KEY, $enabled ? '1' : '0');

        return self::$enabled = $enabled;
    }

    /**
     * Number for the current page. If no explicit key is given the current
     * route name (or URI / path as fallback) is used.
     */
    public static function page(?string $key = null): int
    {
        if ($key === null) {
            $key = self::currentPageKey();
        }

        return self::assign('page', $key, self::PAGE_START);
    }

    /**
     * Number for a popup keyed by its modal DOM id.
     */
    public static function popup(string $key): int
    {
        return self::assign('popup', $key, self::POPUP_START);
    }

    /**
     * Resolve the persisted key behind a badge number so a page number can be
     * traced back to its route name / modal id. Returns null if unknown.
     */
    public static function keyFor(int $number): ?string
    {
        foreach (self::registry() as $key => $value) {
            if ((int) $value === $number) {
                return $key;
            }
        }

        return null;
    }

    public static function registry(): array
    {
        if (self::$registry !== null) {
            return self::$registry;
        }

        try {
            $path = self::path();
            $raw = is_file($path) ? (string) file_get_contents($path) : '{}';
            $decoded = json_decode($raw, true);

            self::$registry = is_array($decoded) ? $decoded : [];
        } catch (RuntimeException) {
            self::$registry = [];
        }

        return self::$registry;
    }

    private static function currentPageKey(): string
    {
        $route = Route::getCurrentRoute();

        if ($route) {
            if ($name = $route->getName()) {
                return $name;
            }

            return $route->uri();
        }

        return request()->path() ?: 'root';
    }

    private static function assign(string $prefix, string $key, int $start): int
    {
        $registryKey = $prefix.':'.$key;

        if (isset(self::registry()[$registryKey])) {
            return (int) self::registry()[$registryKey];
        }

        $number = self::nextAvailableNumber(self::registry(), $prefix, $start);

        self::$registry[$registryKey] = $number;
        self::save();

        return $number;
    }

    private static function nextAvailableNumber(array $registry, string $prefix, int $start): int
    {
        $used = [];

        foreach ($registry as $key => $number) {
            if (str_starts_with($key, $prefix.':')) {
                $used[(int) $number] = true;
            }
        }

        for ($candidate = $start; $candidate <= 999; $candidate++) {
            if (! isset($used[$candidate])) {
                return $candidate;
            }
        }

        throw new RuntimeException("PageMarker: exhausted numbers for '{$prefix}'.");
    }

    private static function save(): void
    {
        try {
            $path = self::path();

            if (! is_dir(dirname($path))) {
                throw new RuntimeException('PageMarker storage directory missing.');
            }

            file_put_contents(
                $path,
                json_encode(self::$registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                LOCK_EX
            );
        } catch (RuntimeException) {
            // Registry stays in memory only until process exit.
        }
    }

    private static function path(): string
    {
        return storage_path('app/'.self::STORAGE);
    }
}

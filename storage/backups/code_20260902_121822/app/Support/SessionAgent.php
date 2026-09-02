<?php

namespace App\Support;

/**
 * Lightweight user-agent parser used to render the active sessions list
 * (browser + platform labels). Depends on a fresh Laravel session record.
 */
final class SessionAgent
{
    public static function fromUserAgent(?string $userAgent): array
    {
        $agent = (string) $userAgent;

        $platform = 'Unknown';

        if (stripos($agent, 'Windows') !== false) {
            $platform = 'Windows';
        } elseif (stripos($agent, 'iPhone') !== false || stripos($agent, 'iPad') !== false) {
            $platform = 'iOS';
        } elseif (stripos($agent, 'Android') !== false) {
            $platform = 'Android';
        } elseif (stripos($agent, 'Linux') !== false) {
            $platform = 'Linux';
        } elseif (stripos($agent, 'Macintosh') !== false || stripos($agent, 'Mac OS X') !== false) {
            $platform = 'macOS';
        } elseif ($agent === '') {
            $platform = 'Unknown';
        }

        $browser = 'Unknown';

        if (stripos($agent, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (stripos($agent, 'SamsungBrowser') !== false) {
            $browser = 'Samsung Internet';
        } elseif (stripos($agent, 'Opera') !== false || stripos($agent, 'OPR') !== false) {
            $browser = 'Opera';
        } elseif (stripos($agent, 'Trident') !== false || stripos($agent, 'MSIE') !== false) {
            $browser = 'Internet Explorer';
        } elseif (stripos($agent, 'Edg') !== false) {
            $browser = 'Edge';
        } elseif (stripos($agent, 'Chrome') !== false) {
            $browser = 'Chrome';
        } elseif (stripos($agent, 'Safari') !== false) {
            $browser = 'Safari';
        }

        return ['platform' => $platform, 'browser' => $browser];
    }
}

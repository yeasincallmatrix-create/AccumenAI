<?php

use App\Models\InstituteSetting;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

if (! function_exists('qr_svg')) {
    /**
     * Render a QR code as an inline SVG string (pure PHP, no GD required).
     */
    function qr_svg(string $data, int $scale = 6): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'scale' => $scale,
            'margin' => 1,
            'eccLevel' => EccLevel::M,
        ]);

        return (new QRCode($options))->render($data);
    }
}

if (! function_exists('mawa_lang_files')) {
    /**
     * Load the raw translation array for a given locale.
     */
    function mawa_lang_files(string $locale): array
    {
        static $cache = [];
        $locale = $locale === 'bn' ? 'bn' : 'en';
        if (! isset($cache[$locale])) {
            $file = base_path('lang/mawa'.DIRECTORY_SEPARATOR."{$locale}.php");
            $cache[$locale] = is_file($file) ? (array) require $file : [];
        }

        return $cache[$locale];
    }
}

if (! function_exists('mawa_translate')) {
    /**
     * Resolve "a.b.c" against a nested array. Returns null when missing.
     */
    function mawa_translate(array $items, string $key)
    {
        if ($key === '' || $key === null) {
            return null;
        }
        $value = $items;
        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return is_string($value) ? $value : null;
    }
}

if (! function_exists('mawa_current_lang')) {
    /**
     * Active locale: ?lang= query (persisted in session) → session →
     * authenticated user's saved preference → institute setting → Accept-Language → default 'en'.
     */
    function mawa_current_lang(): string
    {
        $candidate = static function ($value) {
            return is_string($value) && in_array($value, ['en', 'bn'], true) ? $value : null;
        };

        // Session first (set by the ?lang= switcher / middleware).
        if (Session::has('mawa_lang')) {
            $lang = $candidate(Session::get('mawa_lang'));
            if ($lang !== null) {
                return $lang;
            }
        }

        // API: Accept-Language header (bn / en) when no session.
        if (! Session::has('mawa_lang') && request()->expectsJson()) {
            $header = strtolower((string) request()->header('Accept-Language'));
            if (str_starts_with($header, 'bn')) {
                return 'bn';
            }
            if (str_starts_with($header, 'en')) {
                return 'en';
            }
        }

        // Fall back to the authenticated user's saved language preference.
        if (! Session::has('mawa_lang')) {
            $user = Auth::guard('institute_user')->check()
                ? Auth::guard('institute_user')->user()
                : (Auth::guard('guardian')->check()
                    ? Auth::guard('guardian')->user()
                    : Auth::user());
            if ($user !== null) {
                $lang = $candidate($user->preferred_language);
                if ($lang !== null) {
                    return $lang;
                }
            }
        }

        // Fall back to the institute's saved language preference.
        $guard = null;
        $user = null;
        if (! Session::has('mawa_lang')) {
            $guard = Auth::guard('institute_user');
            $user = $guard->check() ? $guard->user() : null;
            if ($user !== null && ! empty($user->institute_id)) {
                $lang = $candidate(
                    InstituteSetting::query()
                        ->where('institute_id', $user->institute_id)
                        ->value('language')
                );
                if ($lang !== null) {
                    return $lang;
                }
            }
        }

        return 'en';
    }
}

if (! function_exists('mawa_lang')) {
    /**
     * Translate a dotted key into the active language. Optional :placeholder
     * replacement, e.g. mawa_lang('messages.restored_ok', ['X' => 'Name']).
     */
    function mawa_lang(string $key, array $replace = []): string
    {
        $locale = mawa_current_lang();
        $text = mawa_translate(mawa_lang_files($locale), $key);

        if ($text === null) {
            // Fall back to English so the UI never shows a raw key.
            $text = mawa_translate(mawa_lang_files('en'), $key);
        }
        if ($text === null) {
            return $key;
        }

        $keys = array_keys($replace);
        usort($keys, fn ($a, $b) => strlen((string) $b) - strlen((string) $a));
        foreach ($keys as $search) {
            $text = str_replace(':'.(string) $search, (string) $replace[$search], $text);
        }

        return mawa_fix_mojibake((string) $text);
    }
}

if (! function_exists('mawa_e')) {
    /**
     * Translated string for use inside Blade {{ }} output. The {{ }} echo
     * marker performs the HTML escaping, so no escaping happens here —
     * escaping twice would render '&' as literal "&amp;" in the browser.
     */
    function mawa_e(string $key, array $replace = []): string
    {
        return (string) mawa_lang($key, $replace);
    }
}

if (! function_exists('mawa_lang_direction')) {
    /**
     * Document direction for the current locale ('ltr' / 'rtl').
     */
    function mawa_lang_direction(?string $locale = null): string
    {
        return 'ltr';
    }
}

if (! function_exists('mawa_fix_mojibake')) {
    /**
     * Repair text corrupted by double UTF-8 encoding (mojibake).
     */
    function mawa_fix_mojibake($text)
    {
        if ($text === null || $text === '') {
            return $text;
        }

        static $map = null;
        if ($map === null) {
            $map = [
                "\xC3\xA2\xE2\x82\xAC\xC2\xA2" => "\xE2\x80\xA2",
                "\xC3\xA2\xE2\x82\xAC\xE2\x80\x9D" => "\xE2\x80\x94",
                "\xC3\xA2\xE2\x82\xAC\xE2\x80\x9C" => "\xE2\x80\x93",
                "\xC3\xA2\xE2\x82\xAC\xC2\xA6" => "\xE2\x80\xA6",
                "\xC3\xA2\xE2\x82\xAC\xE2\x80\x99" => "\xE2\x80\x99",
                "\xC3\x82\xC2\xB7" => "\xC2\xB7",
                "\xC3\x82\xC2\xA0" => "\xC2\xA0",
                "\xC3\x83\xC2\xA9" => "\xC3\xA9",
                "\xC3\x83\xC2\xA8" => "\xC3\xA8",
                "\xC3\x83\xC2\xAB" => "\xC3\xAB",
                "\xC3\x83\xC2\xBC" => "\xC3\xBC",
                "\xC3\x83\xC2\xB1" => "\xC3\xB1",
                "\xC3\x83\xC2\xB6" => "\xC3\xB6",
                "\xC3\x83\xC2\xA4" => "\xC3\xA4",
                "\xC3\x83\xC2\xA3" => "\xC3\xA3",
                "\xC3\x83\xC2\xA0" => "\xC3\xA0",
            ];
        }

        return strtr($text, $map);
    }
}

if (! function_exists('mawa_currency_symbol')) {
    /**
     * Native currency symbol for a given country. Defaults to Bangladeshi Taka.
     */
    function mawa_currency_symbol(?string $country = null): string
    {
        static $currencies = [
            'Bangladesh' => "\xE0\xA7\xB3", // ৳
            'India' => "\xE2\x82\xB9",      // ₹
            'Pakistan' => "\xE2\x82\xA8",   // ₨
            'Sri Lanka' => "\xE2\x82\xA8",  // ₨
            'Nepal' => "\xE2\x82\xA8",      // ₨
            'Bhutan' => "\xE2\x82\xA8",     // ₨
            'Maldives' => 'Rf',
            'Afghanistan' => "\xE2\x80\x8B\xD8\xA7\xD9\x81\u{200B}", // ؋
            'Myanmar' => 'K',
            'Thailand' => "\xE0\xB8\xBF",   // ฿
            'Vietnam' => "\xE2\x82\xAB",    // ₫
            'Laos' => "\xE2\x82\xAD",       // ₭
            'Cambodia' => "\xE2\x9F\xB6",   // ៛
            'Malaysia' => 'RM',
            'Singapore' => 'S$',
            'Indonesia' => 'Rp',
            'Philippines' => "\xE2\x82\xB1", // ₱
            'Brunei' => 'B$',
            'United States' => '$',
            'United Kingdom' => "\xC2\xA3", // £
            'Eurozone' => "\xE2\x82\xAC",   // €
            'Japan' => "\xC2\xA5",          // ¥
            'China' => "\xC2\xA5",          // ¥
            'Australia' => 'A$',
            'Canada' => 'C$',
            'Saudi Arabia' => "\xD8\xB1.\xD8\xB3", // ر.س
            'United Arab Emirates' => "\xD8\xAF.\xD8\xA5", // د.إ
            'Qatar' => "\xD8\xB1.\xD9\x82", // ر.ق
            'Kuwait' => "\xD9\x83.\xD8\xAF", // ك.د
            'Oman' => "\xD8\xB1.\xD8\xB9",  // ر.ع
            'Bahrain' => "\xD8\xAF.\xD8\xA8", // د.ب
            'Jordan' => "\xD8\xAF.\xD8\xA3", // د.أ
            'Turkey' => "\xE2\x82\xBA",     // ₺
            'Russia' => "\xE2\x82\xBD",     // ₽
            'Egypt' => "\xC2\xA3",          // £E
            'South Africa' => 'R',
            'Kenya' => 'KSh',
            'Nigeria' => "\xE2\x82\xA6",    // ₦
            'Ethiopia' => 'Br',
        ];

        $country = trim((string) $country);

        return $currencies[$country] ?? $currencies['Bangladesh'];
    }
}

if (! function_exists('mawa_country_flag')) {
    /**
     * Flag image URL for a given country. Falls back to a blank placeholder.
     */
    function mawa_country_flag(?string $country = null): string
    {
        static $iso2 = [
            'Afghanistan' => 'AF', 'Albania' => 'AL', 'Algeria' => 'DZ', 'Angola' => 'AO',
            'Argentina' => 'AR', 'Armenia' => 'AM', 'Australia' => 'AU', 'Austria' => 'AT',
            'Azerbaijan' => 'AZ', 'Bahamas' => 'BS', 'Bahrain' => 'BH', 'Bangladesh' => 'BD',
            'Barbados' => 'BB', 'Belarus' => 'BY', 'Belgium' => 'BE', 'Belize' => 'BZ',
            'Benin' => 'BJ', 'Bhutan' => 'BT', 'Bolivia' => 'BO', 'Bosnia and Herzegovina' => 'BA',
            'Botswana' => 'BW', 'Brazil' => 'BR', 'Brunei' => 'BN', 'Bulgaria' => 'BG',
            'Burkina Faso' => 'BF', 'Burundi' => 'BI', 'Cambodia' => 'KH', 'Cameroon' => 'CM',
            'Canada' => 'CA', 'Cape Verde' => 'CV', 'Central African Republic' => 'CF', 'Chad' => 'TD',
            'Chile' => 'CL', 'China' => 'CN', 'Colombia' => 'CO', 'Comoros' => 'KM',
            'Congo' => 'CG', 'Costa Rica' => 'CR', 'Croatia' => 'HR', 'Cuba' => 'CU',
            'Cyprus' => 'CY', 'Czech Republic' => 'CZ', 'Denmark' => 'DK', 'Djibouti' => 'DJ',
            'Dominican Republic' => 'DO', 'DR Congo' => 'CD', 'Ecuador' => 'EC', 'Egypt' => 'EG',
            'El Salvador' => 'SV', 'Equatorial Guinea' => 'GQ', 'Eritrea' => 'ER', 'Estonia' => 'EE',
            'Eswatini' => 'SZ', 'Ethiopia' => 'ET', 'Fiji' => 'FJ', 'Finland' => 'FI',
            'France' => 'FR', 'Gabon' => 'GA', 'Gambia' => 'GM', 'Georgia' => 'GE',
            'Germany' => 'DE', 'Ghana' => 'GH', 'Greece' => 'GR', 'Greenland' => 'GL',
            'Guatemala' => 'GT', 'Guinea' => 'GN', 'Guinea-Bissau' => 'GW', 'Guyana' => 'GY',
            'Haiti' => 'HT', 'Honduras' => 'HN', 'Hong Kong' => 'HK', 'Hungary' => 'HU',
            'Iceland' => 'IS', 'India' => 'IN', 'Indonesia' => 'ID', 'Iran' => 'IR',
            'Iraq' => 'IQ', 'Ireland' => 'IE', 'Israel' => 'IL', 'Italy' => 'IT',
            'Ivory Coast' => 'CI', 'Jamaica' => 'JM', 'Japan' => 'JP', 'Jordan' => 'JO',
            'Kazakhstan' => 'KZ', 'Kenya' => 'KE', 'Kosovo' => 'XK', 'Kuwait' => 'KW',
            'Kyrgyzstan' => 'KG', 'Laos' => 'LA', 'Latvia' => 'LV', 'Lebanon' => 'LB',
            'Lesotho' => 'LS', 'Liberia' => 'LR', 'Libya' => 'LY', 'Lithuania' => 'LT',
            'Luxembourg' => 'LU', 'Macau' => 'MO', 'Madagascar' => 'MG', 'Malawi' => 'MW',
            'Malaysia' => 'MY', 'Maldives' => 'MV', 'Mali' => 'ML', 'Mauritania' => 'MR',
            'Mauritius' => 'MU', 'Mexico' => 'MX', 'Moldova' => 'MD', 'Mongolia' => 'MN',
            'Montenegro' => 'ME', 'Morocco' => 'MA', 'Mozambique' => 'MZ', 'Myanmar' => 'MM',
            'Namibia' => 'NA', 'Nepal' => 'NP', 'Netherlands' => 'NL', 'New Zealand' => 'NZ',
            'Nicaragua' => 'NI', 'Niger' => 'NE', 'Nigeria' => 'NG', 'North Korea' => 'KP',
            'North Macedonia' => 'MK', 'Norway' => 'NO', 'Oman' => 'OM', 'Pakistan' => 'PK',
            'Palestine' => 'PS', 'Panama' => 'PA', 'Papua New Guinea' => 'PG', 'Paraguay' => 'PY',
            'Peru' => 'PE', 'Philippines' => 'PH', 'Poland' => 'PL', 'Portugal' => 'PT',
            'Puerto Rico' => 'PR', 'Qatar' => 'QA', 'Romania' => 'RO', 'Russia' => 'RU',
            'Rwanda' => 'RW', 'Samoa' => 'WS', 'Saudi Arabia' => 'SA', 'Senegal' => 'SN',
            'Serbia' => 'RS', 'Seychelles' => 'SC', 'Sierra Leone' => 'SL', 'Singapore' => 'SG',
            'Slovakia' => 'SK', 'Slovenia' => 'SI', 'Solomon Islands' => 'SB', 'Somalia' => 'SO',
            'South Africa' => 'ZA', 'South Korea' => 'KR', 'South Sudan' => 'SS', 'Spain' => 'ES',
            'Sri Lanka' => 'LK', 'Sudan' => 'SD', 'Suriname' => 'SR', 'Sweden' => 'SE',
            'Switzerland' => 'CH', 'Syria' => 'SY', 'Taiwan' => 'TW', 'Tajikistan' => 'TJ',
            'Tanzania' => 'TZ', 'Thailand' => 'TH', 'Timor-Leste' => 'TL', 'Togo' => 'TG',
            'Tonga' => 'TO', 'Trinidad and Tobago' => 'TT', 'Tunisia' => 'TN', 'Turkey' => 'TR',
            'Turkmenistan' => 'TM', 'Uganda' => 'UG', 'Ukraine' => 'UA', 'United Arab Emirates' => 'AE',
            'United Kingdom' => 'GB', 'United States' => 'US', 'Uruguay' => 'UY', 'Uzbekistan' => 'UZ',
            'Vanuatu' => 'VU', 'Venezuela' => 'VE', 'Vietnam' => 'VN', 'Yemen' => 'YE',
            'Zambia' => 'ZM', 'Zimbabwe' => 'ZW',
        ];

        $country = trim((string) $country);

        $code = $iso2[$country] ?? null;

        return $code
            ? 'https://flagcdn.com/w40/'.strtolower($code).'.png'
            : 'https://flagcdn.com/w40/xx.png';
    }
}

if (! function_exists('mawa_hex_to_rgb')) {
    /**
     * Convert a hex color to a "r, g, b" string usable in CSS rgb() vars.
     */
    function mawa_hex_to_rgb(string $hex): string
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            $hex = '0D6EFD';
        }

        return implode(', ', [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ]);
    }
}

if (! function_exists('mawa_darken_hex')) {
    /**
     * Darken a hex color by a factor (0-1, smaller = darker).
     */
    function mawa_darken_hex(string $hex, float $factor = 0.85): string
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            $hex = '0D6EFD';
        }

        $out = '';
        foreach (str_split($hex, 2) as $part) {
            $out .= str_pad(dechex((int) round(hexdec($part) * $factor)), 2, '0', STR_PAD_LEFT);
        }

        return '#'.$out;
    }
}

if (! function_exists('generate_platform_uid')) {
    /**
     * Generate a unique 6-character alphanumeric UPPERCASE UID.
     * Unique across entire platform (users, institute_users, etc.).
     * Uses cryptographically secure random_int().
     */
    function generate_platform_uid(int $length = 6): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $charLen = strlen($chars);
        $maxAttempts = 100;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $uid = '';
            for ($i = 0; $i < $length; $i++) {
                $uid .= $chars[random_int(0, $charLen - 1)];
            }

            if (! platform_uid_exists($uid)) {
                return $uid;
            }
        }

        throw new RuntimeException('Unable to generate unique platform UID after ' . $maxAttempts . ' attempts');
    }
}

if (! function_exists('platform_uid_exists')) {
    /**
     * Check if a UID already exists in any platform table that holds a uid column.
     */
    function platform_uid_exists(string $uid): bool
    {
        // Check users table (primary)
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('users') && \Illuminate\Support\Facades\Schema::hasColumn('users', 'uid')) {
                if (\Illuminate\Support\Facades\DB::table('users')->where('uid', $uid)->exists()) {
                    return true;
                }
            }
        } catch (\Throwable $e) {}

        // Check other platform tables that may have uid
        $tables = ['institute_users', 'institution_user', 'guardians', 'platform_admins', 'platform_staffs', 'students'];

        foreach ($tables as $table) {
            try {
                if (! \Illuminate\Support\Facades\Schema::hasTable($table) || ! \Illuminate\Support\Facades\Schema::hasColumn($table, 'uid')) {
                    continue;
                }
                if (\Illuminate\Support\Facades\DB::table($table)->where('uid', $uid)->exists()) {
                    return true;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return false;
    }
}

if (! function_exists('generate_uid')) {
    /**
     * Alias for generate_platform_uid — 6-char alphanumeric uppercase.
     */
    function generate_uid(int $length = 6): string
    {
        return generate_platform_uid($length);
    }
}

if (! function_exists('generate_user_uid')) {
    /**
     * Alias for generate_platform_uid for User context.
     */
    function generate_user_uid(int $length = 6): string
    {
        return generate_platform_uid($length);
    }
}

if (! function_exists('generateUid')) {
    /**
     * Generate a 10-character UID: 6 alphanumeric + 4 numeric.
     * Last two numeric digits: tens digit cannot be 0 (i.e., between 10–99).
     * Spec uses rand(), we use random_int() for cryptographic safety where available.
     */
    function generateUid(): string
    {
        $alphanumeric = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $alphanLen = strlen($alphanumeric);
        $firstTwo = str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
        $lastTwo = str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT); // tens digit 1–9

        $uid = '';
        for ($i = 0; $i < 6; $i++) {
            $uid .= $alphanumeric[random_int(0, $alphanLen - 1)];
        }
        $uid .= $firstTwo . $lastTwo;
        return $uid;
    }
}

if (! function_exists('generateUniqueUid')) {
    /**
     * Generic UID generator that works for any table.
     * Generates a 10-character UID (spec) unique within the given table/column.
     * Backward compatible: if caller passes legacy $length = 6 as third arg, honor it.
     * Spec signature: generateUniqueUid($table, $column='uid', $maxAttempts=100)
     * Legacy signature: generateUniqueUid($table, $column='uid', $length=6)
     */
    function generateUniqueUid($table, $column = 'uid', $maxAttemptsOrLength = 100, $length = null)
    {
        // Detect legacy call where third argument is intended as length (6)
        $maxAttempts = 100;
        $useTenChar = true;
        $legacyLength = null;

        if (func_num_args() === 3 && is_int($maxAttemptsOrLength) && $maxAttemptsOrLength <= 10 && $maxAttemptsOrLength > 0) {
            // Heuristic: if value <=10 and the table's uid column is still 6-char legacy, treat as length
            // But spec wants 10-char for users/institutes regardless. For backward compat, if length explicitly passed, honor it.
            if (in_array($table, ['users', 'institutes'], true) && $maxAttemptsOrLength === 6) {
                // Caller explicitly asked for 6 — but new spec says 10. Keep 10 for those tables.
                $useTenChar = true;
                $maxAttempts = 100;
            } else {
                $legacyLength = $maxAttemptsOrLength;
                $useTenChar = false;
                $maxAttempts = 100;
            }
        } elseif (is_int($maxAttemptsOrLength) && $maxAttemptsOrLength > 10) {
            $maxAttempts = $maxAttemptsOrLength;
            if ($length !== null && is_int($length)) {
                $legacyLength = $length;
                $useTenChar = false;
            }
        } elseif ($length !== null) {
            $legacyLength = (int) $length;
            $useTenChar = false;
            $maxAttempts = is_int($maxAttemptsOrLength) ? $maxAttemptsOrLength : 100;
        }

        // Legacy path: generate old 6-char uid if explicitly requested via length param
        if ($legacyLength !== null && ! $useTenChar) {
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $charLen = strlen($characters);
            for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
                $uid = '';
                for ($i = 0; $i < $legacyLength; $i++) {
                    $uid .= $characters[random_int(0, $charLen - 1)];
                }
                try {
                    if (! \Illuminate\Support\Facades\Schema::hasTable($table) || ! \Illuminate\Support\Facades\Schema::hasColumn($table, $column)) {
                        return $uid;
                    }
                    if (! \Illuminate\Support\Facades\DB::table($table)->where($column, $uid)->exists()) {
                        return $uid;
                    }
                } catch (\Throwable $e) {
                    return $uid;
                }
            }
            throw new RuntimeException('Unable to generate unique UID for ' . $table . '.' . $column . ' after ' . $maxAttempts . ' attempts');
        }

        // Spec path: 10-char UID via generateUid()
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $uid = function_exists('generateUid') ? generateUid() : generateUidFallbackTen();
            try {
                if (! \Illuminate\Support\Facades\Schema::hasTable($table) || ! \Illuminate\Support\Facades\Schema::hasColumn($table, $column)) {
                    return $uid;
                }
                if (! \Illuminate\Support\Facades\DB::table($table)->where($column, $uid)->exists()) {
                    return $uid;
                }
            } catch (\Throwable $e) {
                return $uid;
            }
        }
        throw new RuntimeException('Unable to generate unique UID for ' . $table . '.' . $column . ' after ' . $maxAttempts . ' attempts');
    }

    if (! function_exists('generateUidFallbackTen')) {
        function generateUidFallbackTen(): string
        {
            $alphanumeric = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $firstTwo = str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
            $lastTwo = str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT);
            $uid = '';
            for ($i = 0; $i < 6; $i++) {
                $uid .= $alphanumeric[random_int(0, strlen($alphanumeric) - 1)];
            }
            return $uid . $firstTwo . $lastTwo;
        }
    }
}

if (! function_exists('generateInstituteStudentId')) {
    /**
     * Generate a 6-digit random student ID unique within an institute.
     * Uses cryptographically secure random_int() and checks existence
     * via DB::table('students') where institute_id + student_id.
     * Retries until unique or throws after 100 attempts (exhaustion guard).
     */
    function generateInstituteStudentId($instituteId): string
    {
        $instituteId = (int) $instituteId;
        $attempts = 0;
        do {
            $studentId = str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            $exists = false;
            try {
                $exists = \Illuminate\Support\Facades\DB::table('students')
                    ->where('institute_id', $instituteId)
                    ->where('student_id', $studentId)
                    ->exists();
            } catch (\Throwable $e) {
                // If table/column missing during early migration, return generated ID
                $exists = false;
            }
            $attempts++;
            if ($attempts > 100) {
                throw new RuntimeException('Unable to generate unique student_id for institute '.$instituteId.' after 100 attempts');
            }
        } while ($exists);

        return $studentId;
    }
}

if (! function_exists('generateStudentRegNo')) {
    /**
     * Generate a 10-digit student registration number.
     * Format: [Last2 of Institute UID] + [YY] + [MM] + [Random3] + [0]
     * Always exactly 10 digits. Last char fixed '0' to keep length 10.
     */
    function generateStudentRegNo($instituteUid): string
    {
        $lastTwo = substr((string) $instituteUid, -2);
        // Ensure lastTwo is two digits; fallback to 00 if institute uid is short/empty
        if (! preg_match('/^\d{2}$/', $lastTwo)) {
            // If UID ends with non-numeric (legacy 6-char), derive digits from crc or fallback
            $digits = preg_replace('/\D/', '', (string) $instituteUid);
            $lastTwo = strlen($digits) >= 2 ? substr($digits, -2) : str_pad(substr($digits, -2), 2, '0', STR_PAD_LEFT);
            if ($lastTwo === '' || $lastTwo === false) $lastTwo = '00';
            $lastTwo = str_pad($lastTwo, 2, '0', STR_PAD_LEFT);
        }
        $year = date('y');
        $month = date('m');
        $random = str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
        $dayLastDigit = substr(date('j'), -1);

        return $lastTwo . $year . $month . $random . $dayLastDigit;
    }
}

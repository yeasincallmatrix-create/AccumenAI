<?php

namespace App\Support;

/**
 * Normalizes person names to Initial Caps (Title Case) project-wide.
 *
 * Examples: "rahim" -> "Rahim", "RAHIM UDDIN" -> "Rahim Uddin",
 * "md. karim-uddin" -> "Md. Karim-Uddin", "o'brien" -> "O'Brien".
 * Bengali / scripts without case are left untouched (Unicode-safe).
 */
class NameCaser
{
    /**
     * Convert a single name fragment to Initial Caps.
     */
    public static function title(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Collapse whitespace, trim. Keep single spaces only.
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
        if ($value === '') {
            return $value;
        }

        // Lowercase first (multibyte-safe), then title-case each word.
        // mb_convert_case with MB_CASE_TITLE handles hyphen/apostrophe
        // boundaries in a Unicode-safe way for Latin; Bengali chars pass through.
        $lower = mb_strtolower($value, 'UTF-8');
        $titled = mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');

        // Fix possessive / contraction artefacts: "O'Brien" is kept,
        // but "Md." style dots need upper after dot+space (already handled).
        // Ensure letter after "-" and "'" and "." is uppercased even if
        // mb_convert_case missed it on some PHP builds.
        $titled = preg_replace_callback(
            "/(^|[\\s\\-_'‘’\\.\\/\\(])([\\p{Ll}])/u",
            fn ($m) => $m[1].mb_strtoupper($m[2], 'UTF-8'),
            $titled
        ) ?? $titled;

        return $titled;
    }

    /**
     * Whether a request/model key holds a first/last style person name.
     */
    public static function isNameKey(string $key): bool
    {
        $k = strtolower($key);

        return in_array($k, [
            'first_name', 'last_name',
            'middle_name',
            'father_name', 'mother_name',
            'guardian_name',
            'emergency_contact_name',
            'full_name',
        ], true);
    }
}

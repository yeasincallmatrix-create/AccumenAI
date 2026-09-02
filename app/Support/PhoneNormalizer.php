<?php

namespace App\Support;

/**
 * Lightweight phone normalizer without external dependency.
 *
 * Uses CountryCodes as source for dial codes.
 */
class PhoneNormalizer
{
    /**
     * Remove formatting characters (spaces, -, (, )) while preserving a leading +.
     * Does NOT strip other characters - they are preserved for invalid detection.
     */
    public static function strip(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        $hasPlus = str_starts_with($raw, '+');
        // Remove formatting chars: space, -, (, )
        $cleaned = str_replace([' ', '-', '(', ')'], '', $raw);
        // If original had leading +, ensure exactly one leading + and no other + inside
        if ($hasPlus) {
            // Remove all + then prepend one
            $cleaned = ltrim($cleaned, '+');
            // Remove any remaining + inside (invalid but strip for clean)
            $cleaned = str_replace('+', '', $cleaned);
            return '+' . $cleaned;
        }
        // Remove any stray + inside for non-plus numbers (treat as invalid保留 but stripped version removes it?)
        // Keep as is without + for later invalid detection; just remove + inside
        // If raw contained + not at start, we preserve it as is for toE164 to detect invalid
        // For strip() conceptual, we just remove formatting but keep + if at start
        // If there was a + inside, leave it for validation to catch
        if (str_contains($cleaned, '+')) {
            // Keep as is so validator can detect invalid placement
            return $cleaned;
        }

        return $cleaned;
    }

    /**
     * Convert raw input to E.164 normalized form (+ dial code + subscriber).
     * Returns null when input contains invalid characters or cannot be normalized.
     *
     * @param string|null $raw
     */
    public static function toE164(?string $raw, ?string $country = null): ?string
    {
        if ($raw === null) {
            return null;
        }
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $stripped = self::strip($raw);

        // Invalid characters: anything except digits and leading +
        if (! preg_match('/^\+?\d+$/', $stripped)) {
            return null;
        }

        // Has leading +
        if (str_starts_with($stripped, '+')) {
            $digits = substr($stripped, 1);
            if ($digits === '' || ! ctype_digit($digits)) {
                return null;
            }
            // Normalize trunk zero after dial code: +8800XXXXXXXX -> +880XXXXXXXX (BD)
            $matched = CountryCodes::matchPrefix($digits);
            if ($matched !== null) {
                $national = substr($digits, strlen($matched));
                if ($national !== '' && str_starts_with($national, '0')) {
                    $national = ltrim($national, '0');
                    if ($national !== '') {
                        $digits = $matched . $national;
                    }
                }
            }
            return '+' . $digits;
        }

        // No leading +
        $digits = $stripped;
        if ($digits === '' || ! ctype_digit($digits)) {
            return null;
        }

        $code = $country ? CountryCodes::codeFor($country) : null;

        // If digits start with the country dial code, treat as international without +
        if ($code !== null && str_starts_with($digits, $code)) {
            // Strip trunk zero after dial code if present (e.g., 8800...)
            $national = substr($digits, strlen($code));
            if ($national !== '' && str_starts_with($national, '0')) {
                $trimmed = ltrim($national, '0');
                if ($trimmed !== '') {
                    return '+' . $code . $trimmed;
                }
            }
            return '+' . $digits;
        }

        // Try to detect any known dial code prefix only when no country context (to avoid misclassifying national numbers like US 555...)
        if ($code === null) {
            $matched = CountryCodes::matchPrefix($digits);
            if ($matched !== null) {
                if (strlen($digits) > strlen($matched)) {
                    return '+' . $digits;
                }
            }
        }

        // National format - convert using country code
        $dial = $code ?? '880'; // fallback to BD if no country
        // Respect trunk: if national starts with 0, strip leading zeros before prefixing
        $nationalCore = $digits;
        if (str_starts_with($nationalCore, '0')) {
            $nationalCore = ltrim($nationalCore, '0');
            if ($nationalCore === '') {
                return null;
            }
        }

        return '+' . $dial . $nationalCore;
    }

    /**
     * Extract national part (subscriber without dial code) from raw input.
     * For national input, returns digits without formatting.
     * For international input, returns subscriber part after dial code (without leading 0 trunk handling).
     */
    public static function nationalPart(?string $raw, ?string $country = null): string
    {
        if ($raw === null) {
            return '';
        }
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        $stripped = self::strip($raw);
        // Remove leading + for analysis
        $digits = ltrim($stripped, '+');
        $digits = preg_replace('/\D/', '', $digits) ?? '';

        if ($digits === '') {
            return '';
        }

        // If raw was international (had + or starts with dial code), strip dial code
        $code = null;
        if (str_starts_with($stripped, '+')) {
            $code = CountryCodes::matchPrefix($digits);
            if ($code === null && $country) {
                $code = CountryCodes::codeFor($country);
                if (! str_starts_with($digits, $code)) {
                    $code = null;
                }
            }
        } else {
            // Non-plus: check if starts with country code
            if ($country) {
                $cc = CountryCodes::codeFor($country);
                if (str_starts_with($digits, $cc)) {
                    $code = $cc;
                }
                // Do not use generic match when country is known - avoids misclassifying national numbers
            } else {
                $matched = CountryCodes::matchPrefix($digits);
                if ($matched !== null && strlen($digits) > strlen($matched)) {
                    $code = $matched;
                }
            }
        }

        if ($code !== null && str_starts_with($digits, $code)) {
            $national = substr($digits, strlen($code));
            // For countries where national includes trunk 0, re-add trunk 0 for length comparison?
            // Return subscriber without trunk (as stored internationally). Caller can decide.
            // We return subscriber part without dial code.
            return $national;
        }

        // National format: return digits (including trunk 0 if present)
        // But we stripped formatting already, so return digits
        // For consistency, return digits without ltrim
        // However if national started with 0, keep it
        return $digits;
    }

    /**
     * Check if raw contains invalid characters (anything except digits, +, space, -, (, )).
     */
    public static function hasInvalidCharacters(?string $raw): bool
    {
        if ($raw === null || $raw === '') {
            return false;
        }
        return (bool) preg_match('/[^0-9+\s\-\(\)]/', $raw);
    }

    /**
     * Classify raw value for dry-run reporting.
     */
    public static function classify(?string $raw, ?string $country = null): string
    {
        if ($raw === null || trim($raw) === '') {
            return 'EMPTY';
        }
        if (self::hasInvalidCharacters($raw)) {
            return 'INVALID';
        }
        $stripped = self::strip($raw);
        $trimmed = trim($raw);
        $hasFormatting = (bool) preg_match('/[\s\-\(\)]/', $trimmed);
        $normalized = self::toE164($raw, $country);

        if ($normalized === null) {
            return 'INVALID';
        }

        // If already in +E164 and no formatting and matches normalized
        if (str_starts_with($trimmed, '+') && ! $hasFormatting && $trimmed === $normalized) {
            return 'VALID_NORMALIZED';
        }
        if (str_starts_with($trimmed, '+') && $hasFormatting) {
            return 'FORMATTED';
        }
        if (str_starts_with($trimmed, '+')) {
            return 'INTERNATIONAL_FORMAT';
        }
        // Starts with dial code without +
        $digits = preg_replace('/\D/', '', $trimmed) ?? '';
        $code = $country ? CountryCodes::codeFor($country) : null;
        if ($code && str_starts_with($digits, $code)) {
            return 'INTERNATIONAL_FORMAT';
        }
        if ($country === null) {
            $matched = CountryCodes::matchPrefix($digits);
            if ($matched !== null && str_starts_with($digits, $matched) && strlen($digits) > strlen($matched)) {
                return 'INTERNATIONAL_FORMAT';
            }
        }
        if ($hasFormatting) {
            return 'FORMATTED';
        }
        return 'NATIONAL_FORMAT';
    }
}

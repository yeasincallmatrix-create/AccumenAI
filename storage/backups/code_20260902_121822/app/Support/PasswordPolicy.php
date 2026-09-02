<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Single source of truth for password policy.
 * Backend is authoritative; frontend is UX-only.
 */
class PasswordPolicy
{
    /**
     * Return the Password rule object alone (without required/string/confirmed).
     */
    public static function rule(): Password
    {
        $cfg = config('security.password', []);
        $min = (int) ($cfg['min_length'] ?? 8);
        $rule = Password::min($min);

        // mixedCase = at least 1 upper + 1 lower
        if (($cfg['require_uppercase'] ?? true) && ($cfg['require_lowercase'] ?? true)) {
            $rule = $rule->mixedCase();
        } elseif ($cfg['require_uppercase'] ?? true) {
            $rule = $rule->mixedCase();
        } elseif ($cfg['require_lowercase'] ?? true) {
            $rule = $rule->letters();
        }

        if ($cfg['require_number'] ?? true) {
            $rule = $rule->numbers();
        }

        if ($cfg['require_symbol'] ?? true) {
            $rule = $rule->symbols();
        }

        // letters() already implied by mixedCase, but ensure letters required
        if (! ($cfg['require_uppercase'] ?? true) && ! ($cfg['require_lowercase'] ?? true)) {
            $rule = $rule->letters();
        }

        return $rule;
    }

    /**
     * Return Laravel validation rules for a password field (required + confirmed).
     */
    public static function rules(): array
    {
        return ['required', 'string', 'confirmed', self::rule()];
    }

    /**
     * Nullable variant for optional password fields (e.g., teacher create).
     */
    public static function nullableRules(): array
    {
        return ['nullable', 'string', 'confirmed', self::rule()];
    }

    /**
     * Lightweight plain-text check without ValidationException — for service layer.
     * Returns null on pass, error string on fail.
     */
    public static function check(string $plain): ?string
    {
        $cfg = config('security.password', []);
        $min = (int) ($cfg['min_length'] ?? 8);

        if (strlen($plain) < $min) {
            return "Password must be at least {$min} characters.";
        }
        if (($cfg['require_uppercase'] ?? true) && ! preg_match('/[A-Z]/', $plain)) {
            return 'Password must contain at least one uppercase letter.';
        }
        if (($cfg['require_lowercase'] ?? true) && ! preg_match('/[a-z]/', $plain)) {
            return 'Password must contain at least one lowercase letter.';
        }
        if (($cfg['require_number'] ?? true) && ! preg_match('/[0-9]/', $plain)) {
            return 'Password must contain at least one number.';
        }
        if (($cfg['require_symbol'] ?? true) && ! preg_match('/[^A-Za-z0-9]/', $plain)) {
            return 'Password must contain at least one special character.';
        }
        if (trim($plain) === '') {
            return 'Password may not be empty.';
        }
        return null;
    }
}

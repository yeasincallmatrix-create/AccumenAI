<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Guards against corrupted password hashes.
 *
 * A hash whose leading scheme prefix was stripped (e.g. an export/import or
 * binlog round-trip dropping "$2y$...") makes BcryptHasher throw
 * "This password does not use the Bcrypt algorithm", turning a login into a
 * 500. Every login path that touches a stored hash should run it through
 * looksValid() / safeCheck() instead of calling Hash::check() directly.
 */
class PasswordHash
{
    public const STATUS_VALID = 'valid';
    public const STATUS_EMPTY = 'empty';
    public const STATUS_UNSUPPORTED = 'unsupported';
    public const STATUS_MALFORMED = 'malformed';

    /**
     * Does the stored value look like a real bcrypt/argon hash?
     */
    public static function looksValid(string $hash): bool
    {
        return self::classify($hash) === self::STATUS_VALID;
    }

    /**
     * Classify a stored hash into one of four buckets.
     *
     * - valid:        correct prefix and correct length
     * - empty:        null/empty string
     * - unsupported:  no recognised $2y$ / $argon2* prefix
     * - malformed:    recognised prefix but wrong length (truncated, etc.)
     */
    public static function classify(string $hash): string
    {
        if ($hash === '') {
            return self::STATUS_EMPTY;
        }

        if (! preg_match('#^\$(2[abxy]|argon2(id|i)?)\$#i', $hash)) {
            return self::STATUS_UNSUPPORTED;
        }

        if (str_starts_with($hash, '$argon')) {
            return strlen($hash) >= 80 ? self::STATUS_VALID : self::STATUS_MALFORMED;
        }

        // bcrypt: $2y$<cost>$<22-salt><31-hash> -> exactly 60 chars
        return strlen($hash) === 60 ? self::STATUS_VALID : self::STATUS_MALFORMED;
    }

    /**
     * Hash::check() that never throws on a corrupt hash; returns false instead.
     */
    public static function safeCheck(string $password, string $hash): bool
    {
        try {
            return Hash::check($password, $hash);
        } catch (RuntimeException) {
            return false;
        }
    }
}

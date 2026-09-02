<?php

namespace App\Support;

class EmailNormalizer
{
    public static function normalize(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }
        $email = trim($email);
        if ($email === '') {
            return null;
        }
        // Lowercase entire email (local and domain) per spec: "lowercase where appropriate"
        // We do full lowercase for uniqueness, preserve original not needed
        return strtolower($email);
    }

    public static function isValid(?string $email): bool
    {
        if ($email === null) return false;
        $n = self::normalize($email);
        return $n !== null && filter_var($n, FILTER_VALIDATE_EMAIL) !== false;
    }
}

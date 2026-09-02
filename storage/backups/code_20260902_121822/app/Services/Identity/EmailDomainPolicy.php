<?php

namespace App\Services\Identity;

class EmailDomainPolicy
{
    public static function allowedDomains(): array
    {
        $domains = config('identity.allowed_email_domains', []);
        if (empty($domains)) {
            return [];
        }
        return array_map(fn($d) => strtolower(trim($d)), $domains);
    }

    public static function isAllowed(string $email): bool
    {
        $domains = self::allowedDomains();
        if (empty($domains)) {
            return true;
        }
        $parts = explode('@', strtolower(trim($email)));
        if (count($parts) !== 2) {
            return false;
        }
        $domain = $parts[1];
        return in_array($domain, $domains, true);
    }

    public static function validateOrFail(string $email): void
    {
        if (! self::isAllowed($email)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => ['Email domain is not allowed.'],
            ]);
        }
    }
}

<?php

namespace App\Rules;

use App\Support\CountryCodes;
use App\Support\PhoneNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Country-aware phone validation rule.
 *
 * Accepts:
 * - national input when country context is known (e.g. 01786699448 for BD)
 * - international E164 with leading + (e.g. +8801786699448)
 * - international without + when it starts with dial code (e.g. 8801786699448)
 * - formatted input with spaces, -, (, ) (stripped before validation)
 *
 * Validates:
 * - numeric content after normalization
 * - country-aware national length via CountryCodes::nationalLengthFor()
 * - fallback [7,12] for unknown country
 */
class PhoneRule implements ValidationRule
{
    public function __construct(
        private ?string $country = null,
        private bool $allowEmpty = true,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            if ($this->allowEmpty) {
                return;
            }
            $fail('The :attribute field is required.');
            return;
        }

        $raw = (string) $value;

        if (PhoneNormalizer::hasInvalidCharacters($raw)) {
            $fail('The :attribute contains invalid characters. Only digits, +, spaces, - and ( ) are allowed.');
            return;
        }

        $stripped = PhoneNormalizer::strip($raw);
        if (! preg_match('/^\+?\d+$/', $stripped)) {
            $fail('The :attribute must contain only digits and an optional leading +.');
            return;
        }

        $normalized = PhoneNormalizer::toE164($raw, $this->country);
        if ($normalized === null) {
            $fail('The :attribute is not a valid phone number.');
            return;
        }

        // Determine national length to validate
        [$min, $max] = CountryCodes::nationalLengthFor($this->country);
        $example = CountryCodes::phoneExampleFor($this->country);
        $countryLabel = $this->country ?? 'unknown';

        // Extract digits for length check
        $isInternational = str_starts_with($stripped, '+') || $this->isInternationalWithoutPlus($stripped);

        if ($isInternational) {
            $digits = ltrim($stripped, '+');
            $digits = preg_replace('/\D/', '', $digits) ?? '';
            // Detect dial code to get subscriber length
            $code = CountryCodes::matchPrefix($digits);
            if ($code === null && $this->country) {
                $cc = CountryCodes::codeFor($this->country);
                if (str_starts_with($digits, $cc)) {
                    $code = $cc;
                }
            }
            if ($code !== null && str_starts_with($digits, $code)) {
                $subscriberLen = strlen($digits) - strlen($code);
                // For national numbers that include trunk 0, international is one digit shorter.
                // Accept both subscriber == expected and subscriber == expected-1
                $valid = false;
                if ($subscriberLen >= $min && $subscriberLen <= $max) {
                    $valid = true;
                }
                if ($subscriberLen >= $min - 1 && $subscriberLen <= $max - 1) {
                    $valid = true;
                }
                // Fallback: if country has fixed length, also accept subscriber exactly min-1
                if (! $valid) {
                    $rangeLabel = $min === $max ? "{$max} digits" : "{$min}–{$max} digits";
                    $fail("The :attribute must be {$rangeLabel} (national) for {$countryLabel}. Example: {$example}. Got {$subscriberLen} subscriber digits.");
                }
                return;
            }
            // No code detected but international flag set - fallback to total digits check
            $len = strlen($digits);
            // E164 total digits typically 7-15, but we use national fallback
            if ($len < 7 || $len > 15) {
                $fail("The :attribute must be a valid international number (7-15 digits). Example: {$example}.");
            }
            return;
        }

        // National format
        $digits = preg_replace('/\D/', '', $stripped) ?? '';
        $len = strlen($digits);

        if ($len < $min || $len > $max) {
            $rangeLabel = $min === $max ? "{$max} digits" : "{$min}–{$max} digits";
            $fail("The :attribute must be {$rangeLabel} for {$countryLabel}. Example: {$example}. Got {$len} digits.");
        }
    }

    private function isInternationalWithoutPlus(string $stripped): bool
    {
        if (str_starts_with($stripped, '+')) {
            return true;
        }
        $digits = preg_replace('/\D/', '', $stripped) ?? '';
        if ($this->country) {
            $cc = CountryCodes::codeFor($this->country);
            if (str_starts_with($digits, $cc) && strlen($digits) > strlen($cc)) {
                return true;
            }
            return false;
        }
        $matched = CountryCodes::matchPrefix($digits);
        if ($matched === null || strlen($matched) < 2) {
            return false;
        }
        return strlen($digits) > strlen($matched) && str_starts_with($digits, $matched);
    }

    /**
     * For older Rule interface compatibility.
     */
    public function passes($attribute, $value): bool
    {
        $failed = false;
        $this->validate($attribute, $value, function () use (&$failed) {
            $failed = true;
        });
        return ! $failed;
    }

    public function message(): string
    {
        [$min, $max] = CountryCodes::nationalLengthFor($this->country);
        $range = $min === $max ? "{$max} digits" : "{$min}–{$max} digits";
        $country = $this->country ?? 'unknown';
        $example = CountryCodes::phoneExampleFor($this->country);
        return "The :attribute must be {$range} for {$country}. Example: {$example}.";
    }
}

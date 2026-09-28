<?php

namespace App\Support;

/**
 * Currency formatting (manual symbol map — intl extension NOT
 * available in this environment, so NumberFormatter is avoided).
 */
class CurrencyFormatter
{
    private const SYMBOLS = [
        'BDT' => '৳',
        'INR' => '₹',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'AED' => 'AED',
        'SAR' => 'SAR',
        'QAR' => 'QAR',
        'KWD' => 'KWD',
        'BHD' => 'BHD',
        'OMR' => 'OMR',
        'PKR' => 'PKR',
        'LKR' => 'LKR',
        'NPR' => 'NPR',
        'BTN' => 'BTN',
        'MVR' => 'MVR',
        'MYR' => 'MYR',
        'THB' => 'THB',
        'IDR' => 'IDR',
        'PHP' => 'PHP',
        'VND' => 'VND',
        'CAD' => 'CAD',
        'AUD' => 'AUD',
        'SGD' => 'SGD',
        'PLN' => 'PLN',
        'SEK' => 'SEK',
        'DKK' => 'DKK',
        'CZK' => 'CZK',
        'RON' => 'RON',
        'HUF' => 'HUF',
    ];

    public static function format(float $amount, string $currencyCode, ?string $locale = null): string
    {
        $code = strtoupper(trim($currencyCode));
        $number = number_format($amount, 2);

        $symbol = self::SYMBOLS[$code] ?? null;
        if ($symbol === null) {
            return "{$code} {$number}";
        }

        // Single-glyph symbols prefix tightly; ISO codes use a space.
        if (mb_strlen($symbol) === 1) {
            return "{$symbol} {$number}";
        }

        return "{$symbol} {$number}";
    }
}

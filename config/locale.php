<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Locale defaults (final fallback values)
    |--------------------------------------------------------------------------
    |
    | These are the FINAL fallbacks when country-specific data is
    | unavailable. Country-specific values come from the `countries`
    | DB table via CountryConfigResolver.
    |
    | Authority note (B107): The `countries` DB table is the source of
    | truth for country data. config/countries.php is a legacy display
    | list with no ISO mapping — do NOT extend it; use the resolver.
    |
    | Values below preserve today's behavior (BD-first platform).
    */

    'phone' => [
        'default_country_code' => env('LOCALE_PHONE_DEFAULT', '880'),
        'default_iso2'         => env('LOCALE_PHONE_ISO2', 'BD'),
    ],

    'currency' => [
        'default_code'   => env('LOCALE_CURRENCY_DEFAULT', 'BDT'),
        'default_symbol' => env('LOCALE_CURRENCY_SYMBOL', '৳'),
    ],

    'date' => [
        'default_format_key' => env('LOCALE_DATE_FORMAT', 'dmy'),
        'timezone'           => env('LOCALE_TIMEZONE', 'Asia/Dhaka'),
    ],

    'country' => [
        'default_iso2' => env('LOCALE_COUNTRY_DEFAULT', 'BD'),
    ],
];

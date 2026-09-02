<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email provider policy
    |--------------------------------------------------------------------------
    | Allowed email domains for registration / change. Null or empty means
    | all domains allowed. Comma-separated env variable IDENTITY_ALLOWED_EMAIL_DOMAINS
    */
    'allowed_email_domains' => array_filter(array_map('trim', explode(',', env('IDENTITY_ALLOWED_EMAIL_DOMAINS', env('ALLOWED_EMAIL_DOMAINS', ''))))),

    /*
    |--------------------------------------------------------------------------
    | Phone OTP
    |--------------------------------------------------------------------------
    */
    'phone_otp' => [
        'length' => 6,
        'expires_minutes' => 10,
        'max_attempts' => 5,
        'resend_throttle_seconds' => 60,
        'max_sends_per_hour' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Email change
    |--------------------------------------------------------------------------
    */
    'email_change' => [
        'expires_minutes' => 60,
        'throttle_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Phone password recovery
    |--------------------------------------------------------------------------
    */
    'phone_password_reset' => [
        'length' => 6,
        'expires_minutes' => 10,
        'max_attempts' => 5,
        'resend_throttle_seconds' => 60,
        'max_sends_per_hour' => 5,
        'verified_ttl_minutes' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Email OTP (E18)
    |--------------------------------------------------------------------------
    */
    'email_otp' => [
        'length' => 6,
        'expires_minutes' => 15,
        'max_attempts' => 5,
        'resend_throttle_seconds' => 60,
        'max_sends_per_hour' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-factor methods (E18)
    |--------------------------------------------------------------------------
    */
    'two_factor' => [
        'preferred_methods' => ['totp', 'sms', 'email'],
    ],
];

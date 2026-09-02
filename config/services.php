<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'bkash' => [
        'app_key' => env('BKASH_APP_KEY'),
        'app_secret' => env('BKASH_APP_SECRET'),
        'username' => env('BKASH_USERNAME'),
        'password' => env('BKASH_PASSWORD'),
        'base_url' => env('BKASH_BASE_URL', 'https://tokenized.sandbox.bka.sh'),
        'sandbox' => env('BKASH_SANDBOX', true),
        'callback_url' => env('BKASH_CALLBACK_URL', env('APP_URL').'/saas/callback'),
        'webhook_secret' => env('BKASH_WEBHOOK_SECRET'),
    ],

    'ocr' => [
        'max_file_size' => env('OCR_MAX_FILE_SIZE', 20480), // in KB, 20 MB universal for all tenants
    ],

    'tesseract' => [
        'path' => env('TESSERACT_PATH', 'C:/Program Files/Tesseract-OCR/tesseract.exe'),
    ],

];

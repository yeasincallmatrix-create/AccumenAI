<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Global geography import settings
    |--------------------------------------------------------------------------
    |
    | Geography is GLOBAL SHARED REFERENCE DATA — it lives in the shared
    | countries / administrative_levels / administrative_units tables and is
    | never duplicated per institute/tenant.
    */

    'import' => [

        // Records processed per database transaction (chunk).
        'chunk_size' => (int) env('GEO_IMPORT_CHUNK_SIZE', 1000),

        // Max records processed per HTTP request when the admin UI runs an
        // import in resumable chunks (avoids long-running requests).
        'records_per_request' => (int) env('GEO_IMPORT_RECORDS_PER_REQUEST', 2000),

        // Config directory used by the CLI importer where data packages live.
        'package_root' => env('GEO_PACKAGE_ROOT', database_path('geo')),

        // Allowed upload extensions (server-side, never trust the client MIME).
        'allowed_extensions' => ['jsonl', 'json', 'csv', 'ndjson'],

        // Max uploaded file size in kilobytes.
        'max_file_kb' => (int) env('GEO_IMPORT_MAX_FILE_KB', 102400),
    ],

];

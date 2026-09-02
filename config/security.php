<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Password Policy
    |--------------------------------------------------------------------------
    | Central deterministic policy. Do NOT make tenant-configurable unless
    | the architecture already supports it safely.
    */
    'password' => [
        'min_length' => 8,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_number' => true,
        'require_symbol' => true,
    ],
];

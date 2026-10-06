<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OPD Queue Display
    |--------------------------------------------------------------------------
    | Registered settings used by the multi-doctor OPD queue display board.
    | The resolved value is read at runtime from the key/value `settings`
    | table (see \App\Models\Setting) and falls back to `default`.
    */
    'queue_display' => [
        'patient_name_format' => [
            'key' => 'medical.queue_display.patient_name_format',
            'default' => 'first_name',
            'options' => [
                'serial_only' => 'Serial number only (hide name)',
                'first_name' => 'First name only',
                'last_name' => 'Last name only',
                'first_last' => 'First name + last name',
                'last_first' => 'Last name + first name',
                'full' => 'Full name (as recorded)',
            ],
        ],
        'max_doctors' => 2,
        'refresh_seconds' => 30,
        'up_next_limit' => 5,
    ],

    'dosage_forms' => [
        'Tablet' => 'Tablet',
        'Capsule' => 'Capsule',
        'Syrup' => 'Syrup',
        'Suspension' => 'Suspension',
        'Injection' => 'Injection',
        'Drops' => 'Drops',
        'Inhaler' => 'Inhaler',
        'Cream' => 'Cream',
        'Ointment' => 'Ointment',
        'Gel' => 'Gel',
        'Spray' => 'Spray',
        'Suppository' => 'Suppository',
        'Sachet' => 'Sachet',
        'Powder' => 'Powder',
        'Solution' => 'Solution',
        'Lotion' => 'Lotion',
        'Patch' => 'Patch',
    ],
];

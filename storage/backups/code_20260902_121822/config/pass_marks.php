<?php

/**
 * P2-6 — Country-Specific Pass Marks Defaults.
 * Percentage of full_mark used when pass_mark is not explicitly provided.
 * Keys are ISO2 country codes; 'global' is fallback.
 * Component type is inferred from component name (practical vs theory).
 */
return [
    // Bangladesh: 33% theory, 40% practical
    'BD' => [
        'default' => 33,
        'theory' => 33,
        'practical' => 40,
        'components' => [
            'practical' => 40,
            'viva' => 40,
            'lab' => 40,
        ],
    ],

    // United States: 60% flat
    'US' => [
        'default' => 60,
    ],

    // United Kingdom (GB): 40% flat (UK alias)
    'GB' => [
        'default' => 40,
    ],
    'UK' => [
        'default' => 40,
    ],

    // India: 33% flat
    'IN' => [
        'default' => 33,
        'theory' => 33,
        'practical' => 40,
        'mcq' => 33,
    ],

    // Australia: 50% flat
    'AU' => [
        'default' => 50,
        'theory' => 50,
        'practical' => 50,
        'mcq' => 50,
    ],

    // Canada: 50% flat
    'CA' => [
        'default' => 50,
        'theory' => 50,
        'practical' => 50,
        'mcq' => 50,
    ],

    // Global fallback (spec default: 40%)
    'global' => [
        'default' => 40,
        'theory' => 40,
        'practical' => 40,
        'mcq' => 40,
    ],
    // Alias per spec: 'default' key used as fallback country
    'default' => [
        'theory' => 40,
        'practical' => 40,
        'mcq' => 40,
    ],
];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auto-assign PREMIUM package to test institutes
    |--------------------------------------------------------------------------
    |
    | When enabled (default true), any Institute::create() with
    | empty(package_id) during tests gets automatically assigned
    | the PREMIUM package. This preserves the behavior of most
    | legacy tests but causes silent conversion for tests that
    | explicitly want package_id = null.
    |
    | Set to false in a test's setUp to preserve explicit null:
    |   config(['testing.auto_premium' => false]);
    |
    */
    'auto_premium' => env('TESTING_AUTO_PREMIUM', true),

];

<?php

return [
    'revenue_mode' => env('MEDICAL_BILLING_REVENUE_MODE', 'accrual'),
    'auto_post'    => (bool) env('MEDICAL_BILLING_AUTO_POST', true),
];

// TODO (scalability): when institutes > 100, add institute_id column
// to settings table for proper indexing.
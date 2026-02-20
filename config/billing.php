<?php

return [
    'trial_days' => env('BILLING_TRIAL_DAYS', 14),
    'trial_plan' => env('BILLING_TRIAL_PLAN', 'professional'),
    'currency' => 'PHP',
    'minimum_employees' => [
        'starter' => 5,
        'professional' => 10,
        'enterprise' => 25,
    ],
];

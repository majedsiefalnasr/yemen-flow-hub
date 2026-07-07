<?php

return [
    'allow_role_switch' => env('APP_DEMO_ROLE_SWITCH', false),
    'allowed_environments' => ['local', 'staging', 'testing'],
    'seed_demo_data' => env('DEMO_SEED_DATA', true),
    'allowed_seed_environments' => ['local', 'staging', 'testing'],
    'seed_size' => env('DEMO_SEED_SIZE', 'minimal'),
];

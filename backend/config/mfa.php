<?php

return [
    'enabled' => env('MFA_ENABLED', false),
    'otp_ttl_seconds' => (int) env('OTP_TTL_SECONDS', 600),
    'max_attempts' => (int) env('MFA_MAX_ATTEMPTS', 5),
];

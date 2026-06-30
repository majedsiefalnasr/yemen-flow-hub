<?php

return [
    'forgot_message' => 'If this email exists, a recovery code has been sent.',
    'otp_ttl_seconds' => (int) env('PASSWORD_RESET_OTP_TTL_SECONDS', 600),
    'max_attempts' => (int) env('PASSWORD_RESET_MAX_ATTEMPTS', 5),
];

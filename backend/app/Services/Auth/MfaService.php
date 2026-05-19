<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;

class MfaService
{
    private const CODE_LENGTH = 6;

    private function cacheKey(string $email): string
    {
        return 'mfa_otp:' . strtolower($email);
    }

    public function generate(string $email): string
    {
        $ttlSeconds = (int) config('mfa.otp_ttl_seconds', 600);
        $code = str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
        Cache::put($this->cacheKey($email), $code, now()->addSeconds($ttlSeconds));
        return $code;
    }

    public function verify(string $email, string $code): bool
    {
        $key = $this->cacheKey($email);
        $stored = Cache::get($key);

        if ($stored === null || $stored !== $code) {
            return false;
        }

        Cache::forget($key);
        return true;
    }

    public function hasPending(string $email): bool
    {
        return Cache::has($this->cacheKey($email));
    }
}

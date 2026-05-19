<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MfaService
{
    private const CODE_LENGTH = 6;

    private function cacheKey(string $email): string
    {
        return 'mfa_otp:' . strtolower($email);
    }

    public function generate(string $email): string
    {
        $ttlSeconds = max(1, (int) config('mfa.otp_ttl_seconds', 600));
        $code = str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);

        Cache::put($this->cacheKey($email), [
            'otp' => $code,
            'challenge_id' => (string) Str::uuid(),
            'attempts' => 0,
            'expires_at' => now()->addSeconds($ttlSeconds)->timestamp,
        ], now()->addSeconds($ttlSeconds));

        return $code;
    }

    public function getChallengeId(string $email): ?string
    {
        $challenge = Cache::get($this->cacheKey($email));

        if (!is_array($challenge)) {
            return null;
        }

        $challengeId = $challenge['challenge_id'] ?? null;
        return is_string($challengeId) && $challengeId !== '' ? $challengeId : null;
    }

    public function verify(string $email, string $code, string $challengeId): bool
    {
        $key = $this->cacheKey($email);
        $challenge = Cache::get($key);

        if (!is_array($challenge)) {
            return false;
        }

        $storedChallengeId = $challenge['challenge_id'] ?? null;
        $storedCode = $challenge['otp'] ?? null;
        $attempts = (int) ($challenge['attempts'] ?? 0);
        $expiresAt = (int) ($challenge['expires_at'] ?? 0);
        $now = now()->timestamp;

        if (!is_string($storedChallengeId) || $storedChallengeId === '' || !is_string($storedCode) || $storedCode === '') {
            Cache::forget($key);
            return false;
        }

        if ($expiresAt <= $now) {
            Cache::forget($key);
            return false;
        }

        if (!hash_equals($storedChallengeId, $challengeId)) {
            return false;
        }

        $maxAttempts = max(1, (int) config('mfa.max_attempts', 5));

        if ($attempts >= $maxAttempts) {
            Cache::forget($key);
            return false;
        }

        if (!hash_equals($storedCode, $code)) {
            $challenge['attempts'] = $attempts + 1;

            if ($challenge['attempts'] >= $maxAttempts) {
                Cache::forget($key);
                return false;
            }

            $remainingSeconds = max(1, $expiresAt - $now);
            Cache::put($key, $challenge, now()->addSeconds($remainingSeconds));
            return false;
        }

        Cache::forget($key);
        return true;
    }

    public function hasPending(string $email): bool
    {
        $challenge = Cache::get($this->cacheKey($email));

        if (!is_array($challenge)) {
            return false;
        }

        $expiresAt = (int) ($challenge['expires_at'] ?? 0);
        if ($expiresAt <= now()->timestamp) {
            Cache::forget($this->cacheKey($email));
            return false;
        }

        return true;
    }
}

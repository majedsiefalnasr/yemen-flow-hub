<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class MfaService
{
    private const CODE_LENGTH = 6;

    public function hasTotpConfigured(User $user): bool
    {
        return (bool) ($user->totp_enabled || filled($user->totp_secret));
    }

    private function cacheKey(string $email): string
    {
        return 'mfa_otp:' . strtolower($email);
    }

    private function totpSetupKey(string $email): string
    {
        return 'mfa_totp_setup:' . strtolower($email);
    }

    // ── Random OTP (legacy / email-based fallback) ────────────────────────────

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

    /**
     * Verify a login OTP/TOTP code.
     * If the user has TOTP enabled, verifies against their stored TOTP secret.
     * Otherwise falls back to the cached random OTP.
     */
    public function verify(string $email, string $code, string $challengeId): bool
    {
        $user = User::where('email', strtolower($email))->first();

        if ($user && $this->hasTotpConfigured($user) && filled($user->totp_secret)) {
            return $this->verifyTotp($user->totp_secret, $code);
        }

        return $this->verifyRandomOtp($email, $code, $challengeId);
    }

    private function verifyRandomOtp(string $email, string $code, string $challengeId): bool
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

        if (!is_string($storedChallengeId) || $storedChallengeId === ''
            || !is_string($storedCode) || $storedCode === '') {
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

    // ── TOTP (Authenticator app) ──────────────────────────────────────────────

    /**
     * Generate a new TOTP secret for setup, store it temporarily in cache.
     * Returns the secret string.
     */
    public function generateTotpSecret(string $email): string
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        Cache::put($this->totpSetupKey($email), $secret, now()->addMinutes(10));

        return $secret;
    }

    /**
     * Build the otpauth:// provisioning URI (used by the frontend to render the QR code).
     */
    public function getTotpProvisioningUri(string $email, string $secret): string
    {
        $google2fa = new Google2FA();
        $issuer = config('app.name', 'YemenFlowHub');

        return $google2fa->getQRCodeUrl($issuer, $email, $secret);
    }

    /**
     * Verify a TOTP code against a given secret.
     * Allows a ±1 period window (30 s) to handle clock skew.
     */
    public function verifyTotp(string $secret, string $code): bool
    {
        $google2fa = new Google2FA();
        return (bool) $google2fa->verifyKey($secret, $code, 1);
    }

    /**
     * Verify the setup confirmation code against the pending secret in cache.
     * Returns the secret string on success, null on failure.
     */
    public function verifyTotpSetup(string $email, string $code): ?string
    {
        $secret = Cache::get($this->totpSetupKey($email));

        if (!is_string($secret) || $secret === '') {
            return null;
        }

        if (!$this->verifyTotp($secret, $code)) {
            return null;
        }

        Cache::forget($this->totpSetupKey($email));

        return $secret;
    }
}

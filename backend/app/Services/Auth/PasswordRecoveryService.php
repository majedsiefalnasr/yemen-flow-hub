<?php

namespace App\Services\Auth;

use App\Enums\AuditAction;
use App\Mail\PasswordRecoveryOtpMail;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class PasswordRecoveryService
{
    private const CODE_LENGTH = 6;

    public function __construct(private readonly AuditService $auditService) {}

    public function genericMessage(): string
    {
        return (string) config('account_recovery.forgot_message');
    }

    public function request(string $email): void
    {
        $email = $this->normalizeEmail($email);
        $user = User::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();

        if (! $user) {
            return;
        }

        $ttlSeconds = max(60, (int) config('account_recovery.otp_ttl_seconds', 600));
        $otp = $this->generateCode();

        Cache::put($this->cacheKey($email), [
            'otp_hash' => Hash::make($otp),
            'attempts' => 0,
            'expires_at' => now()->addSeconds($ttlSeconds)->timestamp,
        ], now()->addSeconds($ttlSeconds));

        Mail::to($user->email)->send(
            new PasswordRecoveryOtpMail($otp, (int) ceil($ttlSeconds / 60))
        );
    }

    public function verify(string $email, string $otp): bool
    {
        return $this->resolveValidUser($email, $otp, false) instanceof User;
    }

    public function reset(string $email, string $otp, string $password): bool
    {
        $user = $this->resolveValidUser($email, $otp, true);

        if (! $user) {
            return false;
        }

        $user->forceFill([
            'password' => Hash::make($password),
            'must_change_password' => false,
            'temporary_password_set_at' => null,
            'password_changed_at' => now(),
        ])->save();

        $user->tokens()->delete();

        $this->auditService->log(
            AuditAction::PASSWORD_RESET,
            null,
            $user,
            ['mode' => 'email_otp']
        );

        return true;
    }

    private function resolveValidUser(string $email, string $otp, bool $consume): ?User
    {
        $email = $this->normalizeEmail($email);
        $key = $this->cacheKey($email);
        $challenge = Cache::get($key);

        if (! is_array($challenge)) {
            return null;
        }

        if ((int) ($challenge['expires_at'] ?? 0) < now()->timestamp) {
            Cache::forget($key);

            return null;
        }

        if ((int) ($challenge['attempts'] ?? 0) >= $this->maxAttempts()) {
            Cache::forget($key);

            return null;
        }

        $hash = $challenge['otp_hash'] ?? null;
        if (! is_string($hash) || ! Hash::check($otp, $hash)) {
            $challenge['attempts'] = ((int) ($challenge['attempts'] ?? 0)) + 1;
            $this->storeChallengeForRemainingTtl($key, $challenge);

            return null;
        }

        $user = User::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();

        if (! $user) {
            Cache::forget($key);

            return null;
        }

        if ($consume) {
            Cache::forget($key);
        }

        return $user;
    }

    private function storeChallengeForRemainingTtl(string $key, array $challenge): void
    {
        $remainingSeconds = max(1, (int) ($challenge['expires_at'] ?? 0) - now()->timestamp);
        Cache::put($key, $challenge, now()->addSeconds($remainingSeconds));
    }

    private function maxAttempts(): int
    {
        return max(1, (int) config('account_recovery.max_attempts', 5));
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    private function cacheKey(string $email): string
    {
        return 'password_reset_otp:'.sha1($this->normalizeEmail($email));
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}

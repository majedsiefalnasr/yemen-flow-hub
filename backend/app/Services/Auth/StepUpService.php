<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class StepUpService
{
    public function __construct(
        private readonly AuthSecuritySettings $settings,
        private readonly MfaService $mfaService,
    ) {}

    public function hasValidStepUp(User $user): bool
    {
        $verifiedAt = Cache::get($this->cacheKey($user->id));

        if (! is_int($verifiedAt)) {
            return false;
        }

        $windowSeconds = $this->settings->stepUpWindowMinutes() * 60;

        return (now()->timestamp - $verifiedAt) <= $windowSeconds;
    }

    public function recordStepUp(User $user): void
    {
        $windowSeconds = $this->settings->stepUpWindowMinutes() * 60;
        Cache::put($this->cacheKey($user->id), now()->timestamp, now()->addSeconds($windowSeconds));
    }

    public function clearStepUp(User $user): void
    {
        Cache::forget($this->cacheKey($user->id));
    }

    /**
     * Verify TOTP or email OTP for step-up. Records step-up window on success.
     */
    public function verify(User $user, string $code, ?string $challengeId = null): bool
    {
        $verified = false;

        if ($this->mfaService->hasTotpConfigured($user) && filled($user->totp_secret)) {
            if (preg_match('/^[0-9]{6}$/', $code) === 1
                && $this->mfaService->verifyTotp($user->totp_secret, $code)) {
                $verified = true;
            } elseif ($this->mfaService->verifyRecoveryCode($user, $code)) {
                $verified = true;
            }
        } elseif ($challengeId !== null && $this->mfaService->verify($user->email, $code, $challengeId)) {
            $verified = true;
        }

        if ($verified) {
            $this->recordStepUp($user);
        }

        return $verified;
    }

    public function initiateEmailChallenge(User $user): array
    {
        $email = $user->email;
        $result = $this->mfaService->generateOrReuse($email);
        $challengeId = $this->mfaService->getChallengeId($email);

        if ($result['sent'] && ! $this->mfaService->hasTotpConfigured($user)) {
            $ttlMinutes = (int) ceil(config('mfa.otp_ttl_seconds', 600) / 60);
            $this->mfaService->sendOtpEmail($user, $result['otp'], $ttlMinutes);
        }

        return [
            'challenge_id' => $challengeId,
            'method' => $this->mfaService->hasTotpConfigured($user) ? 'totp' : 'email',
        ];
    }

    private function cacheKey(int $userId): string
    {
        return 'step_up_verified:'.$userId;
    }
}

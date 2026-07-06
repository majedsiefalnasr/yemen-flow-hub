<?php

namespace App\Services\Auth;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class TrustedDeviceService
{
    public function __construct(
        private readonly AuthSecuritySettings $settings,
    ) {}

    public function cookieName(): string
    {
        return (string) config('auth_security.trusted_device_cookie', 'trusted_device');
    }

    public function findValid(User $user, Request $request): ?TrustedDevice
    {
        $raw = $request->cookie($this->cookieName());
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $hash = hash('sha256', $raw);
        $device = TrustedDevice::query()
            ->where('user_id', $user->id)
            ->where('token_hash', $hash)
            ->where('expires_at', '>', now())
            ->first();

        if (! $device) {
            return null;
        }

        if (! $this->environmentMatches($device, $request)) {
            $device->delete();

            return null;
        }

        $device->forceFill(['last_used_at' => now()])->save();

        return $device;
    }

    public function issue(User $user, Request $request): \Symfony\Component\HttpFoundation\Cookie
    {
        $raw = Str::random(64);
        $ttlHours = $this->settings->trustedDeviceTtlHours();

        TrustedDevice::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $raw),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'ip_address' => $this->ipClass((string) $request->ip()),
            'expires_at' => now()->addHours($ttlHours),
            'last_used_at' => now(),
        ]);

        return Cookie::make(
            $this->cookieName(),
            $raw,
            $ttlHours * 60,
            '/',
            null,
            (bool) config('session.secure', false),
            true,
            false,
            'lax'
        );
    }

    public function forgetCookie(): \Symfony\Component\HttpFoundation\Cookie
    {
        return Cookie::forget($this->cookieName());
    }

    public function revokeAll(User $user): void
    {
        TrustedDevice::query()->where('user_id', $user->id)->delete();
    }

    public function revokeFromRequest(User $user, Request $request): void
    {
        $raw = $request->cookie($this->cookieName());
        if (! is_string($raw) || $raw === '') {
            return;
        }

        TrustedDevice::query()
            ->where('user_id', $user->id)
            ->where('token_hash', hash('sha256', $raw))
            ->delete();
    }

    private function environmentMatches(TrustedDevice $device, Request $request): bool
    {
        $storedAgent = (string) ($device->user_agent ?? '');
        $currentAgent = Str::limit((string) $request->userAgent(), 500, '');

        if ($storedAgent !== '' && $storedAgent !== $currentAgent) {
            return false;
        }

        $storedIp = (string) ($device->ip_address ?? '');
        $currentIp = $this->ipClass((string) $request->ip());

        return $storedIp === '' || $storedIp === $currentIp;
    }

    private function ipClass(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                return $parts[0].'.'.$parts[1].'.'.$parts[2].'.0';
            }
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);

            return implode(':', array_slice($parts, 0, 4)).'::';
        }

        return $ip;
    }
}

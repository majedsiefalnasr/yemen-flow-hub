<?php

namespace Tests\Feature\Auth;

use App\Enums\AuditAction;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Routing\Middleware\ThrottleRequests::shouldHashKeys(false);
        RateLimiter::clear('|127.0.0.1');
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->disableCookieEncryption();
    }

    protected function tearDown(): void
    {
        \Illuminate\Routing\Middleware\ThrottleRequests::shouldHashKeys(true);
        parent::tearDown();
    }

    private function makeUser(array $attrs = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'Test User',
            'email' => 'hardening@example.com',
            'password' => Hash::make('Password1'),
            'role' => 'BANK_REVIEWER',
            'is_active' => true,
            'bank_id' => null,
        ], $attrs));
    }

    public function test_lockout_triggers_after_five_failures_per_account(): void
    {
        $email = 'locked5@example.com';
        $this->makeUser(['email' => $email]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'wrong'])
                ->assertStatus(422);
        }

        $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'wrong'])
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'ACCOUNT_LOCKED');

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ACCOUNT_LOCKED->value,
        ]);
    }

    public function test_rotating_ips_still_locks_account_after_five_failures(): void
    {
        $email = 'victim5@example.com';
        $this->makeUser(['email' => $email, 'password' => Hash::make('Password1')]);

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->postJson('/api/auth/login', ['email' => $email, 'password' => 'wrong'])
                ->assertStatus(422);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson('/api/auth/login', ['email' => $email, 'password' => 'Password1'])
            ->assertStatus(429);
    }

    public function test_trusted_device_skips_mfa_re_prompt(): void
    {
        config(['mfa.enabled' => true]);
        $this->makeUser(['email' => 'trusted@example.com']);

        $login = $this->withServerVariables(['HTTP_USER_AGENT' => 'TestAgent', 'REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/auth/login', [
                'email' => 'trusted@example.com',
                'password' => 'Password1',
            ])
            ->assertOk()
            ->assertJsonPath('data.requires_mfa', true);

        $challengeId = $login->json('data.challenge_id');
        $challenge = Cache::get('mfa_otp:trusted@example.com');
        $this->assertIsArray($challenge);

        $verify = $this->withServerVariables(['HTTP_USER_AGENT' => 'TestAgent', 'REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/auth/verify-otp', [
                'email' => 'trusted@example.com',
                'otp' => $challenge['otp'],
                'challenge_id' => $challengeId,
                'trust_device' => true,
            ])
            ->assertOk();

        $cookieName = config('auth_security.trusted_device_cookie', 'trusted_device');
        $setCookie = collect($verify->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === $cookieName);
        $this->assertNotNull($setCookie);
        $user = User::query()->where('email', 'trusted@example.com')->firstOrFail();
        $this->assertDatabaseHas('trusted_devices', ['user_id' => $user->id]);

        $rawToken = $setCookie->getValue();
        $this->assertNotEmpty($rawToken);

        $this->withCredentials()
            ->withServerVariables(['HTTP_USER_AGENT' => 'TestAgent', 'REMOTE_ADDR' => '127.0.0.1'])
            ->withUnencryptedCookie($cookieName, $rawToken)
            ->postJson('/api/auth/login', ['email' => 'trusted@example.com', 'password' => 'Password1'])
            ->assertOk()
            ->assertJsonPath('data.requires_mfa', false);
    }

    public function test_concurrent_mfa_challenge_reuses_existing_code(): void
    {
        config(['mfa.enabled' => true]);
        $this->makeUser(['email' => 'mfa@example.com']);

        $first = $this->postJson('/api/auth/login', ['email' => 'mfa@example.com', 'password' => 'Password1']);
        $first->assertOk()->assertJsonPath('data.requires_mfa', true);
        $challengeId = $first->json('data.challenge_id');

        $second = $this->postJson('/api/auth/login', ['email' => 'mfa@example.com', 'password' => 'Password1']);
        $second->assertOk()
            ->assertJsonPath('data.challenge_id', $challengeId)
            ->assertJsonPath('data.challenge_reused', true);
    }

    public function test_password_policy_rejects_blacklisted_password(): void
    {
        $user = $this->makeUser();
        $user->forceFill(['totp_enabled' => true, 'totp_secret' => 'SECRET'])->save();

        $this->actingAs($user)
            ->postJson('/api/profile/change-password', [
                'current_password' => 'Password1',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertStatus(422);
    }

    public function test_step_up_required_for_password_change_without_recent_mfa(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson('/api/profile/change-password', [
                'current_password' => 'Password1',
                'password' => 'Newpass9',
                'password_confirmation' => 'Newpass9',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'STEP_UP_REQUIRED');
    }

    public function test_revoke_all_sessions_clears_trusted_devices(): void
    {
        $user = $this->makeUser();
        TrustedDevice::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'device-token'),
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->postJson('/api/profile/sessions/revoke-all')
            ->assertOk();

        $this->assertDatabaseMissing('trusted_devices', ['user_id' => $user->id]);
    }

    public function test_profile_update_does_not_change_email(): void
    {
        $user = $this->makeUser(['email' => 'original@example.com']);

        $this->actingAs($user)
            ->putJson('/api/profile', [
                'name' => 'Updated Name',
                'email' => 'hacker@example.com',
            ])
            ->assertOk();

        $user->refresh();
        $this->assertEquals('original@example.com', $user->email);
    }

    public function test_disable_with_password_endpoint_removed(): void
    {
        $user = $this->makeUser(['totp_enabled' => true, 'totp_secret' => 'X']);

        $this->actingAs($user)
            ->postJson('/api/profile/mfa/disable-with-password', ['password' => 'Password1'])
            ->assertStatus(404);
    }

    public function test_profile_mfa_required_uses_explicit_default(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->getJson('/api/profile');

        $response->assertOk()
            ->assertJsonPath('data.mfa_required', false);
    }
}

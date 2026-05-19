<?php

namespace Tests\Feature\Auth;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\User;
use App\Services\Auth\MfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable key hashing so we can clear the throttle key by its predictable value.
        // In tests the domain is empty, so the signature is '|127.0.0.1'.
        ThrottleRequests::shouldHashKeys(false);
        RateLimiter::clear('|127.0.0.1');
    }

    protected function tearDown(): void
    {
        ThrottleRequests::shouldHashKeys(true);
        parent::tearDown();
    }

    private function makeUser(array $attrs = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::BANK_REVIEWER->value,
            'is_active' => true,
            'bank_id' => null,
        ], $attrs));
    }

    private function makeBank(): Bank
    {
        return Bank::query()->create([
            'name' => 'بنك تجريبي',
            'code' => 'TST',
            'is_active' => true,
        ]);
    }

    // --- AC-1: IP rate limit ---

    public function test_login_allows_5_attempts_per_minute(): void
    {
        $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrong',
            ]);
            $this->assertNotEquals(429, $response->status(), "Request {$i} should not be rate-limited yet");
        }
    }

    public function test_login_returns_429_on_6th_attempt_per_ip(): void
    {
        $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'test@example.com', 'password' => 'wrong']);
        }

        $response = $this->postJson('/api/auth/login', ['email' => 'test@example.com', 'password' => 'wrong']);

        $response->assertStatus(429);
        $response->assertJsonPath('success', false);
    }

    // --- AC-2: Account lockout ---

    public function test_account_locks_after_10_consecutive_email_failures(): void
    {
        $email = 'lockme@example.com';
        $failKey = 'login_fail:' . $email;
        $this->makeUser(['email' => $email]);

        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit($failKey, 15 * 60);
        }

        $response = $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'wrong']);

        $response->assertStatus(403);
        $response->assertJsonPath('error_code', 'ACCOUNT_LOCKED');
    }

    public function test_account_lockout_returns_correct_shape(): void
    {
        $email = 'locked@example.com';
        $failKey = 'login_fail:' . $email;
        $this->makeUser(['email' => $email]);

        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit($failKey, 15 * 60);
        }

        $response = $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'any']);

        $response->assertStatus(403);
        $response->assertJsonStructure(['success', 'message', 'error_code']);
        $this->assertEquals('ACCOUNT_LOCKED', $response->json('error_code'));
    }

    // --- AC-3: Failed login audit logging ---

    public function test_wrong_credentials_logs_login_failed_audit_entry(): void
    {
        $this->makeUser();

        $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong_password',
        ]);

        $log = AuditLog::query()
            ->where('action', AuditAction::LOGIN_FAILED->value)
            ->first();

        $this->assertNotNull($log, 'LOGIN_FAILED audit entry should be created');
        $this->assertNull($log->user_id, 'Failed login audit entry must have user_id = NULL');
        $this->assertEquals('WRONG_CREDENTIALS', $log->metadata['reason']);
        $this->assertEquals('test@example.com', $log->metadata['email']);
    }

    public function test_inactive_account_logs_login_failed_audit_entry(): void
    {
        $this->makeUser(['is_active' => false]);

        $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $log = AuditLog::query()
            ->where('action', AuditAction::LOGIN_FAILED->value)
            ->first();

        $this->assertNotNull($log);
        $this->assertNull($log->user_id);
        $this->assertEquals('INACTIVE', $log->metadata['reason']);
    }

    public function test_locked_account_logs_login_failed_audit_entry(): void
    {
        $email = 'logme@example.com';
        $failKey = 'login_fail:' . $email;
        $this->makeUser(['email' => $email]);

        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit($failKey, 15 * 60);
        }

        $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'any']);

        $log = AuditLog::query()
            ->where('action', AuditAction::LOGIN_FAILED->value)
            ->where('metadata->reason', 'LOCKED')
            ->first();

        $this->assertNotNull($log);
        $this->assertNull($log->user_id);
    }

    // --- AC-3 + Decision 3: Inactive account does NOT increment lockout counter ---

    public function test_inactive_account_does_not_increment_lockout_counter(): void
    {
        $email = 'inactive@example.com';
        $failKey = 'login_fail:' . $email;
        $this->makeUser(['email' => $email, 'is_active' => false]);

        $this->assertEquals(0, RateLimiter::attempts($failKey));

        $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'password']);

        $this->assertEquals(0, RateLimiter::attempts($failKey), 'Inactive account must not increment the lockout counter');
    }

    // --- AC-4: Success clears failure counter ---

    public function test_successful_login_clears_failure_counter(): void
    {
        $email = 'clearme@example.com';
        $failKey = 'login_fail:' . $email;
        $this->makeUser(['email' => $email]);

        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($failKey, 15 * 60);
        }

        $this->assertEquals(5, RateLimiter::attempts($failKey));

        $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'password']);

        $this->assertEquals(0, RateLimiter::attempts($failKey), 'Failure counter must be cleared after successful login');
    }

    // --- AC-5: /me response has bilingual bank fields ---

    public function test_me_includes_bilingual_bank_fields_for_bank_user(): void
    {
        $bank = $this->makeBank();
        $user = $this->makeUser([
            'email' => 'bankuser@example.com',
            'bank_id' => $bank->id,
            'role' => UserRole::BANK_REVIEWER->value,
        ]);

        $response = $this->actingAs($user)->getJson('/api/auth/me');

        $response->assertStatus(200);
        $response->assertJsonPath('data.bank_name', 'بنك تجريبي');
    }

    public function test_me_returns_null_bank_fields_for_cby_user(): void
    {
        $user = $this->makeUser([
            'email' => 'cby@example.com',
            'role' => UserRole::CBY_ADMIN->value,
            'bank_id' => null,
        ]);

        $response = $this->actingAs($user)->getJson('/api/auth/me');

        $response->assertStatus(200);
        $response->assertJsonPath('data.bank_name', null);
    }

    public function test_me_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
    }

    // --- AC-5 + Login response shape ---

    public function test_successful_login_returns_user_with_bank_name(): void
    {
        $bank = $this->makeBank();
        $this->makeUser([
            'bank_id' => $bank->id,
            'role' => UserRole::DATA_ENTRY->value,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.user.bank_name', 'بنك تجريبي');
    }

    public function test_successful_login_logs_login_audit_entry(): void
    {
        $this->makeUser();

        $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $log = AuditLog::query()
            ->where('action', AuditAction::LOGIN->value)
            ->first();

        $this->assertNotNull($log);
        $this->assertNotNull($log->user_id);
    }

    // --- AC-6: Logout (Bearer mode) ---

    public function test_logout_returns_200_and_logs_logout_action(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/auth/logout');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $log = AuditLog::query()
            ->where('action', AuditAction::LOGOUT->value)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($user->id, $log->user_id);
    }

    public function test_logout_revokes_bearer_token(): void
    {
        $user = $this->makeUser();
        $tokenResult = $user->createToken('test');
        $token = $tokenResult->plainTextToken;
        $tokenId = $tokenResult->accessToken->id;

        $this->withToken($token)->postJson('/api/auth/logout');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    // --- AC-8: Logout (cookie/session mode) ---

    public function test_logout_invalidates_cookie_session(): void
    {
        $user = $this->makeUser();

        // Use session-based auth (cookie mode)
        $response = $this->actingAs($user, 'web')->postJson('/api/auth/logout');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // After session invalidation the web guard's auth key is absent from the new session
        $webSessionKey = 'login_web_' . sha1(\Illuminate\Auth\SessionGuard::class);
        $response->assertSessionMissing($webSessionKey);
    }

    public function test_logout_returns_401_when_unauthenticated(): void
    {
        $this->postJson('/api/auth/logout')->assertStatus(401);
    }

    // --- Inactive account HTTP 403 ---

    public function test_inactive_account_returns_403(): void
    {
        $this->makeUser(['is_active' => false]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403);
    }

    // --- MFA: login returns requires_mfa when MFA enabled ---

    public function test_login_returns_requires_mfa_when_mfa_enabled(): void
    {
        config(['mfa.enabled' => true]);
        $this->makeUser();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.requires_mfa', true);
        $response->assertJsonPath('data.email', 'test@example.com');
        $this->assertNotEmpty($response->json('data.challenge_id'));
        $response->assertJsonMissing(['token']);
    }

    public function test_login_does_not_return_requires_mfa_when_mfa_disabled(): void
    {
        config(['mfa.enabled' => false]);
        $this->makeUser();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.requires_mfa', false);
    }

    // --- MFA: verify-otp success ---

    public function test_verify_otp_completes_login_with_correct_code(): void
    {
        $this->makeUser();
        $mfa = new MfaService();
        $code = $mfa->generate('test@example.com');
        $challengeId = $mfa->getChallengeId('test@example.com');

        $response = $this->postJson('/api/auth/verify-otp', [
            'email' => 'test@example.com',
            'otp' => $code,
            'challenge_id' => $challengeId,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.requires_mfa', false);
    }

    // --- MFA: verify-otp invalid code ---

    public function test_verify_otp_returns_422_for_wrong_code(): void
    {
        $this->makeUser();
        $mfa = new MfaService();
        $mfa->generate('test@example.com');
        $challengeId = $mfa->getChallengeId('test@example.com');

        $response = $this->postJson('/api/auth/verify-otp', [
            'email' => 'test@example.com',
            'otp' => '000000',
            'challenge_id' => $challengeId,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['otp']);
    }

    // --- MFA: verify-otp expired (no pending OTP) ---

    public function test_verify_otp_returns_422_when_no_pending_otp(): void
    {
        $this->makeUser();
        Cache::forget('mfa_otp:test@example.com');

        $response = $this->postJson('/api/auth/verify-otp', [
            'email' => 'test@example.com',
            'otp' => '123456',
            'challenge_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['otp']);
    }

    // --- MFA: verify-otp clears OTP after successful use ---

    public function test_verify_otp_is_single_use(): void
    {
        $this->makeUser();
        $mfa = new MfaService();
        $code = $mfa->generate('test@example.com');
        $challengeId = $mfa->getChallengeId('test@example.com');

        $this->postJson('/api/auth/verify-otp', [
            'email' => 'test@example.com',
            'otp' => $code,
            'challenge_id' => $challengeId,
        ]);

        // Second use of same code should fail
        $response = $this->postJson('/api/auth/verify-otp', [
            'email' => 'test@example.com',
            'otp' => $code,
            'challenge_id' => $challengeId,
        ]);

        $response->assertStatus(422);
    }

    // --- MFA: verify-otp throttled ---

    public function test_verify_otp_is_throttled(): void
    {
        $this->makeUser();
        $mfa = new MfaService();
        $mfa->generate('test@example.com');
        $challengeId = $mfa->getChallengeId('test@example.com');

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/verify-otp', [
                'email' => 'test@example.com',
                'otp' => '000000',
                'challenge_id' => $challengeId,
            ]);
        }

        $response = $this->postJson('/api/auth/verify-otp', [
            'email' => 'test@example.com',
            'otp' => '000000',
            'challenge_id' => $challengeId,
        ]);
        $response->assertStatus(429);
    }
}

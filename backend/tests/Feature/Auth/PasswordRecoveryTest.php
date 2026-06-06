<?php

namespace Tests\Feature\Auth;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Jobs\SendEmailDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'Recovery User',
            'email' => 'recovery@example.com',
            'password' => Hash::make('Password123'),
            'role' => UserRole::CBY_ADMIN->value,
            'bank_id' => null,
            'is_active' => true,
        ], $attrs));
    }

    public function test_existing_email_receives_recovery_code_with_generic_response(): void
    {
        Queue::fake();
        $user = $this->makeUser();

        $response = $this->postJson('/api/auth/password/forgot', [
            'email' => 'recovery@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'If this email exists, a recovery code has been sent.');

        $this->assertDatabaseHas('email_deliveries', [
            'notification_type' => NotificationType::PASSWORD_RESET->value,
            'recipient_user_id' => $user->id,
            'recipient_email' => $user->email,
        ]);
        Queue::assertPushed(SendEmailDelivery::class);
    }

    public function test_unknown_email_returns_same_generic_response_without_email(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/auth/password/forgot', [
            'email' => 'missing@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'If this email exists, a recovery code has been sent.');

        $this->assertDatabaseCount('email_deliveries', 0);
        Queue::assertNothingPushed();
    }

    public function test_password_reset_with_valid_otp_changes_password_and_preserves_mfa_pin(): void
    {
        Queue::fake();
        $user = $this->makeUser([
            'mfa_enabled' => true,
            'totp_enabled' => true,
            'totp_secret' => 'JBSWY3DPEHPK3PXP',
            'pin_enabled' => true,
            'pin_code_hash' => Hash::make('125812'),
        ]);

        $this->postJson('/api/auth/password/forgot', ['email' => $user->email])->assertOk();
        $otp = $this->sentOtp();

        $this->postJson('/api/auth/password/reset', [
            'email' => $user->email,
            'otp' => $otp,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertTrue($user->mfa_enabled);
        $this->assertTrue($user->totp_enabled);
        $this->assertSame('JBSWY3DPEHPK3PXP', $user->totp_secret);
        $this->assertTrue($user->pin_enabled);
        $this->assertNotNull($user->pin_code_hash);
    }

    public function test_invalid_otp_fails_safely(): void
    {
        Queue::fake();
        $user = $this->makeUser();

        $this->postJson('/api/auth/password/forgot', ['email' => $user->email])->assertOk();

        $this->postJson('/api/auth/password/reset', [
            'email' => $user->email,
            'otp' => '000000',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['otp']);
    }

    public function test_otp_expires(): void
    {
        Queue::fake();
        $user = $this->makeUser();

        $this->postJson('/api/auth/password/forgot', ['email' => $user->email])->assertOk();
        $otp = $this->sentOtp();

        $this->travel(11)->minutes();

        $this->postJson('/api/auth/password/reset', [
            'email' => $user->email,
            'otp' => $otp,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['otp']);
    }

    public function test_otp_cannot_be_reused(): void
    {
        Queue::fake();
        $user = $this->makeUser();

        $this->postJson('/api/auth/password/forgot', ['email' => $user->email])->assertOk();
        $otp = $this->sentOtp();

        $payload = [
            'email' => $user->email,
            'otp' => $otp,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ];

        $this->postJson('/api/auth/password/reset', $payload)->assertOk();
        $this->postJson('/api/auth/password/reset', $payload)->assertUnprocessable()
            ->assertJsonValidationErrors(['otp']);
    }

    private function sentOtp(): string
    {
        $otp = null;

        Queue::assertPushed(SendEmailDelivery::class, function (SendEmailDelivery $job) use (&$otp): bool {
            if (preg_match('/\b(\d{6})\b/', (string) $job->renderedBody, $matches) !== 1) {
                return false;
            }

            $otp = $matches[1];

            return true;
        });

        $this->assertIsString($otp);

        return $otp;
    }
}

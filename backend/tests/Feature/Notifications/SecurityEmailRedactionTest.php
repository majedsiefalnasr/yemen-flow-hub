<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Jobs\SendEmailDelivery;
use App\Models\EmailDelivery;
use App\Models\User;
use App\Services\Auth\MfaService;
use App\Services\Auth\PasswordRecoveryService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class SecurityEmailRedactionTest extends TestCase
{
    use RefreshDatabase;

    private const MASK = '••••••';

    public function test_mfa_email_uses_outbox_with_masked_snapshot_and_no_database_notification(): void
    {
        Queue::fake();
        $logs = $this->captureLogs();

        $user = $this->makeUser('mfa@example.com');
        $mfa = app(MfaService::class);
        $otp = $mfa->generate($user->email);
        $issuanceId = $mfa->getIssuanceId($user->email);

        $this->assertIsString($issuanceId);

        $mfa->sendOtpEmail($user, $otp, 10);
        $mfa->sendOtpEmail($user, $otp, 10);

        $delivery = EmailDelivery::query()->sole();

        $this->assertSame(NotificationType::MFA_OTP->value, $delivery->notification_type);
        $this->assertSame("MFA_OTP:{$issuanceId}", $delivery->event_id);
        $this->assertSame($user->id, $delivery->recipient_user_id);
        $this->assertStringContainsString(self::MASK, (string) $delivery->rendered_body);
        $this->assertStringNotContainsString($otp, (string) $delivery->rendered_subject);
        $this->assertStringNotContainsString($otp, (string) $delivery->rendered_body);
        $this->assertDatabaseCount('notifications', 0);

        Queue::assertPushed(SendEmailDelivery::class, 1);

        foreach ($logs as $line) {
            $this->assertStringNotContainsString($otp, (string) $line);
        }
    }

    public function test_redacted_email_job_sends_live_code_but_keeps_outbox_masked(): void
    {
        Queue::fake();

        $user = $this->makeUser('live-send@example.com');
        $mfa = app(MfaService::class);
        $otp = $mfa->generate($user->email);
        $mfa->sendOtpEmail($user, $otp, 10);

        $delivery = EmailDelivery::query()->sole();
        $job = Queue::pushed(SendEmailDelivery::class)->first();

        $this->assertInstanceOf(SendEmailDelivery::class, $job);
        $this->assertInstanceOf(ShouldBeEncrypted::class, $job);
        $this->assertStringContainsString($otp, (string) $job->renderedBody);

        Mail::shouldReceive('html')
            ->once()
            ->withArgs(function (string $html) use ($otp): bool {
                $this->assertStringContainsString($otp, $html);
                $this->assertStringNotContainsString(self::MASK, $html);

                return true;
            });

        $job->handle(app('App\\Services\\Notifications\\EmailDeliveryService'));

        $stored = $delivery->fresh();
        $this->assertStringContainsString(self::MASK, (string) $stored->rendered_body);
        $this->assertStringNotContainsString($otp, (string) $stored->rendered_body);
    }

    public function test_redacted_email_job_fails_closed_without_live_payload(): void
    {
        Mail::shouldReceive('html')->never();

        $service = app('App\\Services\\Notifications\\EmailDeliveryService');
        $user = $this->makeUser('masked-replay@example.com');
        $delivery = $service->reserve(
            NotificationType::MFA_OTP,
            'MFA_OTP:missing-live-payload',
            $user->id,
            $user->email,
            'mail'
        );
        $service->finalize($delivery, 'رمز التحقق', 'رمزك هو '.self::MASK);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Redacted email delivery requires a live encrypted payload.');

        (new SendEmailDelivery($delivery->id))->handle($service);
    }

    public function test_password_reset_email_redacts_token_url_and_recovery_secret_shapes(): void
    {
        $service = app('App\\Services\\Notifications\\EmailDeliveryService');
        $user = $this->makeUser('reset-redaction@example.com');
        $delivery = $service->reserve(
            NotificationType::PASSWORD_RESET,
            'PASSWORD_RESET:issuance-redaction',
            $user->id,
            $user->email,
            'mail'
        );

        $token = 'reset-token-abcdefghijklmnopqrstuvwxyz123456';
        $signature = 'signedsignatureabcdefghijklmnopqrstuvwxyz1234567890';
        $recoverySecret = 'BACKUP-CODE-1234567890';
        $signedUrl = "https://app.example.test/password/reset?token={$token}&signature={$signature}";
        $leakyBody = "رمز الاستعادة 482913 {$signedUrl} reset token: {$token} recovery secret: {$recoverySecret}";

        $service->finalize($delivery, "Reset {$token}", $leakyBody);

        $stored = $delivery->fresh();
        $snapshot = (string) $stored->rendered_subject.' '.(string) $stored->rendered_body;

        $this->assertStringContainsString(self::MASK, $snapshot);
        $this->assertStringNotContainsString('482913', $snapshot);
        $this->assertStringNotContainsString($token, $snapshot);
        $this->assertStringNotContainsString($signature, $snapshot);
        $this->assertStringNotContainsString($signedUrl, $snapshot);
        $this->assertStringNotContainsString($recoverySecret, $snapshot);
    }

    public function test_password_reset_email_uses_stable_issuance_id_and_resolved_user_id(): void
    {
        Queue::fake();

        $user = $this->makeUser('recovery@example.com');

        app(PasswordRecoveryService::class)->request($user->email);
        $firstChallenge = Cache::get('password_reset_otp:'.sha1($user->email));
        $firstIssuanceId = $firstChallenge['issuance_id'] ?? null;

        app(PasswordRecoveryService::class)->request($user->email);
        $secondChallenge = Cache::get('password_reset_otp:'.sha1($user->email));
        $secondIssuanceId = $secondChallenge['issuance_id'] ?? null;

        $this->assertIsString($firstIssuanceId);
        $this->assertIsString($secondIssuanceId);
        $this->assertNotSame($firstIssuanceId, $secondIssuanceId);
        $this->assertDatabaseHas('email_deliveries', [
            'notification_type' => NotificationType::PASSWORD_RESET->value,
            'event_id' => "PASSWORD_RESET:{$firstIssuanceId}",
            'recipient_user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('email_deliveries', [
            'notification_type' => NotificationType::PASSWORD_RESET->value,
            'event_id' => "PASSWORD_RESET:{$secondIssuanceId}",
            'recipient_user_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('email_deliveries', [
            'notification_type' => NotificationType::PASSWORD_RESET->value,
            'recipient_user_id' => null,
        ]);
        $this->assertDatabaseCount('notifications', 0);

        Queue::assertPushed(SendEmailDelivery::class, 2);
    }

    public function test_security_registry_types_are_mail_only_redacted_blade_types(): void
    {
        $registry = app('App\\Services\\Notifications\\NotificationRegistry');

        foreach ([NotificationType::MFA_OTP, NotificationType::PASSWORD_RESET] as $type) {
            $definition = $registry->for($type);

            $this->assertSame(['mail'], $definition['channels']);
            $this->assertSame('redacted', $definition['persist_body']);
            $this->assertSame('blade', $definition['source']);
            $this->assertFalse($definition['admin_editable']);
        }
    }

    private function makeUser(string $email): User
    {
        return User::query()->create([
            'name' => 'Security User',
            'email' => $email,
            'password' => Hash::make('Password123'),
            'bank_id' => null,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function captureLogs(): array
    {
        $logs = [];
        Log::listen(function ($message) use (&$logs): void {
            $logs[] = is_string($message) ? $message : json_encode($message);
        });

        return $logs;
    }
}

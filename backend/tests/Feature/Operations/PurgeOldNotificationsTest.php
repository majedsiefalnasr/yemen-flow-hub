<?php

namespace Tests\Feature\Operations;

use App\Enums\UserRole;
use App\Models\EngineNotification;
use App\Models\NotificationRecipient;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeOldNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GovernanceSeeder::class);

        $sysOrg = Organization::where('code', 'system_administration')->firstOrFail();

        $this->user = User::create([
            'name' => 'User A',
            'email' => 'a@test.cby',
            'password' => bcrypt('password'),
            'role' => UserRole::CBY_ADMIN,
            'organization_id' => $sysOrg->id,
            'is_active' => true,
        ]);
    }

    private function makeRecipient(array $notificationAttrs = [], array $recipientAttrs = []): NotificationRecipient
    {
        $notification = EngineNotification::create(array_merge([
            'type' => 'test',
            'severity' => 'info',
            'title' => 'Test notification',
        ], $notificationAttrs));

        return NotificationRecipient::create(array_merge([
            'notification_id' => $notification->id,
            'user_id' => $this->user->id,
        ], $recipientAttrs));
    }

    public function test_purges_old_read_notification_recipients(): void
    {
        $recipient = $this->makeRecipient(recipientAttrs: [
            'read_at' => now()->subDays(400),
        ]);

        $this->artisan('notifications:purge-old')->assertSuccessful();

        $this->assertDatabaseMissing('notification_recipients', ['id' => $recipient->id]);
    }

    public function test_purges_old_unread_notification_recipients(): void
    {
        $recipient = $this->makeRecipient();
        $recipient->forceFill(['created_at' => now()->subDays(100)])->save();

        $this->artisan('notifications:purge-old')->assertSuccessful();

        $this->assertDatabaseMissing('notification_recipients', ['id' => $recipient->id]);
    }

    public function test_keeps_recent_read_notification_recipients(): void
    {
        $recipient = $this->makeRecipient(recipientAttrs: [
            'read_at' => now()->subDays(30),
        ]);

        $this->artisan('notifications:purge-old')->assertSuccessful();

        $this->assertDatabaseHas('notification_recipients', ['id' => $recipient->id]);
    }

    public function test_idempotent_second_run_deletes_nothing_extra(): void
    {
        $recipient = $this->makeRecipient(recipientAttrs: [
            'read_at' => now()->subDays(400),
        ]);

        $this->artisan('notifications:purge-old')->assertSuccessful();
        $this->assertDatabaseMissing('notification_recipients', ['id' => $recipient->id]);

        $this->artisan('notifications:purge-old')->assertSuccessful();
        $this->assertDatabaseCount('notification_recipients', 0);
    }

    public function test_does_not_purge_security_critical_notifications(): void
    {
        $criticalRecipient = $this->makeRecipient(
            notificationAttrs: ['severity' => 'critical'],
            recipientAttrs: ['read_at' => now()->subDays(400)],
        );

        $securityRecipient = $this->makeRecipient(
            notificationAttrs: ['type' => 'security.login_alert'],
            recipientAttrs: ['read_at' => now()->subDays(400)],
        );

        $this->artisan('notifications:purge-old')->assertSuccessful();

        $this->assertDatabaseHas('notification_recipients', ['id' => $criticalRecipient->id]);
        $this->assertDatabaseHas('notification_recipients', ['id' => $securityRecipient->id]);
    }

    public function test_cascades_orphan_engine_notifications(): void
    {
        $recipient = $this->makeRecipient(recipientAttrs: [
            'read_at' => now()->subDays(400),
        ]);
        $notificationId = $recipient->notification_id;

        $this->artisan('notifications:purge-old')->assertSuccessful();

        $this->assertDatabaseMissing('engine_notifications', ['id' => $notificationId]);
    }

    public function test_records_scheduler_heartbeat(): void
    {
        $this->artisan('notifications:purge-old')->assertSuccessful();

        $this->assertDatabaseHas('scheduler_run_logs', [
            'command' => 'notifications:purge-old',
            'status' => 'success',
        ]);
    }
}

<?php

namespace Tests\Feature\Engine;

use App\Enums\UserRole;
use App\Models\EngineNotification;
use App\Models\NotificationRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Notif User',
            'email' => 'notif@notif.test',
            'password' => bcrypt('pass'),
            'role' => UserRole::DATA_ENTRY,
            'is_active' => true,
        ]);
    }

    private function createNotification(User $user, array $attrs = []): NotificationRecipient
    {
        $notification = EngineNotification::create(array_merge([
            'type' => 'REQUEST_CREATED',
            'severity' => 'info',
            'title' => 'Test Notification',
            'body' => 'A test notification body.',
        ], $attrs));

        return NotificationRecipient::create([
            'notification_id' => $notification->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_notification_inbox_returns_user_notifications(): void
    {
        $this->createNotification($this->user);

        $this->actingAs($this->user)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_unread_count_returns_correct_count(): void
    {
        $this->createNotification($this->user);
        $this->createNotification($this->user);

        $response = $this->actingAs($this->user)->getJson('/api/v1/notifications/unread-count');

        $response->assertOk()
            ->assertJsonPath('data.count', 2);
    }

    public function test_marking_notification_as_read_sets_read_at(): void
    {
        $recipient = $this->createNotification($this->user);

        $this->actingAs($this->user)
            ->postJson("/api/v1/notifications/{$recipient->id}/read")
            ->assertOk();

        $this->assertDatabaseHas('notification_recipients', [
            'id' => $recipient->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertNotNull($recipient->fresh()->read_at);
    }

    public function test_read_all_marks_all_notifications_read(): void
    {
        $this->createNotification($this->user);
        $this->createNotification($this->user);

        $this->actingAs($this->user)->postJson('/api/v1/notifications/read-all')->assertOk();

        // All should now have a read_at
        $unread = NotificationRecipient::where('user_id', $this->user->id)
            ->whereNull('read_at')
            ->count();
        $this->assertEquals(0, $unread);
    }

    public function test_marking_notification_as_unread_clears_read_at(): void
    {
        $recipient = $this->createNotification($this->user);
        $recipient->update(['read_at' => now()]);

        $this->actingAs($this->user)
            ->postJson("/api/v1/notifications/{$recipient->id}/unread")
            ->assertOk();

        $this->assertNull($recipient->fresh()->read_at);
    }

    public function test_archive_notification_sets_archived_at(): void
    {
        $recipient = $this->createNotification($this->user);

        $this->actingAs($this->user)
            ->postJson("/api/v1/notifications/{$recipient->id}/archive")
            ->assertOk();

        $this->assertNotNull($recipient->fresh()->archived_at);
    }

    public function test_archived_notifications_excluded_from_default_inbox(): void
    {
        $visible = $this->createNotification($this->user);
        $archived = $this->createNotification($this->user);
        $archived->update(['archived_at' => now()]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/notifications');
        $response->assertOk()->assertJsonPath('data.meta.total', 1);
    }

    public function test_unread_count_excludes_archived_notifications(): void
    {
        $this->createNotification($this->user);
        $recipient = $this->createNotification($this->user);
        $recipient->update(['archived_at' => now()]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/notifications/unread-count');
        $response->assertOk()->assertJsonPath('data.count', 1);
    }

    public function test_user_cannot_read_another_users_notification(): void
    {
        $otherUser = User::create([
            'name' => 'Other',
            'email' => 'other@notif.test',
            'password' => bcrypt('pass'),
            'role' => UserRole::DATA_ENTRY,
            'is_active' => true,
        ]);
        $recipient = $this->createNotification($otherUser);

        $this->actingAs($this->user)
            ->postJson("/api/v1/notifications/{$recipient->id}/read")
            ->assertForbidden();
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/notifications/unread-count')->assertUnauthorized();
    }
}

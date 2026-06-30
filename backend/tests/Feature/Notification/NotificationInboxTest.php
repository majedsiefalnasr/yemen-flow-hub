<?php

namespace Tests\Feature\Notification;

use App\Enums\UserRole;
use App\Models\EngineNotification;
use App\Models\NotificationRecipient;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;

    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GovernanceSeeder::class);

        $sysOrg = Organization::where('code', 'system_administration')->firstOrFail();

        $this->userA = User::create([
            'name' => 'User A',
            'email' => 'a@test.cby',
            'password' => bcrypt('password'),
            'role' => UserRole::CBY_ADMIN,
            'organization_id' => $sysOrg->id,
            'is_active' => true,
        ]);

        $this->userB = User::create([
            'name' => 'User B',
            'email' => 'b@test.cby',
            'password' => bcrypt('password'),
            'role' => UserRole::CBY_ADMIN,
            'organization_id' => $sysOrg->id,
            'is_active' => true,
        ]);
    }

    private function makeNotification(array $recipientIds, array $attrs = []): EngineNotification
    {
        $notification = EngineNotification::create(array_merge([
            'type' => 'test',
            'severity' => 'info',
            'title' => 'Test notification',
        ], $attrs));

        foreach ($recipientIds as $userId) {
            NotificationRecipient::create([
                'notification_id' => $notification->id,
                'user_id' => $userId,
            ]);
        }

        return $notification;
    }

    public function test_list_returns_only_own_copies(): void
    {
        $this->makeNotification([$this->userA->id, $this->userB->id]);
        $this->makeNotification([$this->userB->id]);

        $response = $this->actingAs($this->userA)
            ->getJson('/api/v1/notifications')
            ->assertOk();

        $data = $response->json('data.data');
        $this->assertCount(1, $data);
    }

    public function test_unread_count_accurate(): void
    {
        $this->makeNotification([$this->userA->id]);
        $this->makeNotification([$this->userA->id]);

        // Mark one as read
        $recipient = NotificationRecipient::where('user_id', $this->userA->id)->first();
        $recipient->update(['read_at' => now()]);

        $response = $this->actingAs($this->userA)
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk();

        $this->assertSame(1, $response->json('data.count'));
    }

    public function test_read_marks_own_recipient(): void
    {
        $this->makeNotification([$this->userA->id]);
        $recipient = NotificationRecipient::where('user_id', $this->userA->id)->first();

        $this->actingAs($this->userA)
            ->postJson("/api/v1/notifications/{$recipient->id}/read")
            ->assertOk();

        $this->assertNotNull($recipient->fresh()->read_at);
    }

    public function test_unread_clears_read_at(): void
    {
        $this->makeNotification([$this->userA->id]);
        $recipient = NotificationRecipient::where('user_id', $this->userA->id)->first();
        $recipient->update(['read_at' => now()]);

        $this->actingAs($this->userA)
            ->postJson("/api/v1/notifications/{$recipient->id}/unread")
            ->assertOk();

        $this->assertNull($recipient->fresh()->read_at);
    }

    public function test_archive_sets_archived_at(): void
    {
        $this->makeNotification([$this->userA->id]);
        $recipient = NotificationRecipient::where('user_id', $this->userA->id)->first();

        $this->actingAs($this->userA)
            ->postJson("/api/v1/notifications/{$recipient->id}/archive")
            ->assertOk();

        $this->assertNotNull($recipient->fresh()->archived_at);
    }

    public function test_read_all_marks_all_unread(): void
    {
        $this->makeNotification([$this->userA->id]);
        $this->makeNotification([$this->userA->id]);
        $this->makeNotification([$this->userA->id]);

        $this->actingAs($this->userA)
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk();

        $unread = NotificationRecipient::where('user_id', $this->userA->id)
            ->whereNull('read_at')
            ->count();

        $this->assertSame(0, $unread);
    }

    public function test_cannot_act_on_other_users_copy(): void
    {
        $this->makeNotification([$this->userB->id]);
        $recipient = NotificationRecipient::where('user_id', $this->userB->id)->first();

        $this->actingAs($this->userA)
            ->postJson("/api/v1/notifications/{$recipient->id}/read")
            ->assertStatus(403);
    }

    public function test_shared_notification_not_deleted(): void
    {
        $notification = $this->makeNotification([$this->userA->id, $this->userB->id]);
        $recipientA = NotificationRecipient::where('user_id', $this->userA->id)->first();

        $this->actingAs($this->userA)
            ->postJson("/api/v1/notifications/{$recipientA->id}/archive")
            ->assertOk();

        // The shared notification row still exists
        $this->assertTrue(EngineNotification::where('id', $notification->id)->exists());
        // User B's copy is untouched
        $recipientB = NotificationRecipient::where('user_id', $this->userB->id)->first();
        $this->assertNull($recipientB->archived_at);
    }

    public function test_archived_excluded_from_default_list(): void
    {
        $this->makeNotification([$this->userA->id]);
        $this->makeNotification([$this->userA->id]);

        $recipient = NotificationRecipient::where('user_id', $this->userA->id)->first();
        $recipient->update(['archived_at' => now()]);

        $response = $this->actingAs($this->userA)
            ->getJson('/api/v1/notifications')
            ->assertOk();

        $this->assertCount(1, $response->json('data.data'));
    }
}

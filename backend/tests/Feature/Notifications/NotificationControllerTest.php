<?php

namespace Tests\Feature\Notifications;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(UserRole $role = UserRole::DATA_ENTRY, ?int $bankId = null): User
    {
        return User::query()->create([
            'name' => 'Test User',
            'email' => Str::random(8) . '@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
            'bank_id' => $bankId,
            'is_active' => true,
        ]);
    }

    // --- GET /api/notifications/unread-count ---

    public function test_unread_count_returns_zero_when_no_notifications(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->getJson('/api/notifications/unread-count');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.count', 0);
    }

    public function test_unread_count_returns_correct_count_with_unread_notifications(): void
    {
        $user = $this->makeUser();

        // Insert 3 unread notifications directly into DB
        for ($i = 0; $i < 3; $i++) {
            \DB::table('notifications')->insert([
                'id' => Str::uuid()->toString(),
                'type' => 'App\\Notifications\\RequestSubmittedNotification',
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode(['type' => 'request_submitted', 'message' => 'Test', 'request_id' => null, 'reference_number' => null]),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = $this->actingAs($user)->getJson('/api/notifications/unread-count');

        $response->assertStatus(200);
        $response->assertJsonPath('data.count', 3);
    }

    public function test_unread_count_excludes_already_read_notifications(): void
    {
        $user = $this->makeUser();

        // 2 unread
        for ($i = 0; $i < 2; $i++) {
            \DB::table('notifications')->insert([
                'id' => Str::uuid()->toString(),
                'type' => 'App\\Notifications\\RequestApprovedNotification',
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode(['type' => 'request_approved', 'message' => 'Approved', 'request_id' => 1, 'reference_number' => 'YFH-2026-000001']),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 1 read
        \DB::table('notifications')->insert([
            'id' => Str::uuid()->toString(),
            'type' => 'App\\Notifications\\RequestApprovedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['type' => 'request_approved', 'message' => 'Approved', 'request_id' => 2, 'reference_number' => 'YFH-2026-000002']),
            'read_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/notifications/unread-count');

        $response->assertStatus(200);
        $response->assertJsonPath('data.count', 2);
    }

    public function test_unread_count_resets_to_zero_after_read_all(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 3; $i++) {
            \DB::table('notifications')->insert([
                'id' => Str::uuid()->toString(),
                'type' => 'App\\Notifications\\RequestSubmittedNotification',
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode(['type' => 'request_submitted', 'message' => 'Test', 'request_id' => null, 'reference_number' => null]),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($user)->postJson('/api/notifications/read-all');

        $response = $this->actingAs($user)->getJson('/api/notifications/unread-count');

        $response->assertStatus(200);
        $response->assertJsonPath('data.count', 0);
    }

    public function test_unread_count_only_counts_own_notifications(): void
    {
        $userA = $this->makeUser();
        $userB = $this->makeUser();

        // 3 unread for userA
        for ($i = 0; $i < 3; $i++) {
            \DB::table('notifications')->insert([
                'id' => Str::uuid()->toString(),
                'type' => 'App\\Notifications\\RequestSubmittedNotification',
                'notifiable_type' => User::class,
                'notifiable_id' => $userA->id,
                'data' => json_encode(['type' => 'request_submitted', 'message' => 'Test', 'request_id' => null, 'reference_number' => null]),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // userB should see 0
        $response = $this->actingAs($userB)->getJson('/api/notifications/unread-count');

        $response->assertStatus(200);
        $response->assertJsonPath('data.count', 0);
    }

    public function test_unread_count_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/notifications/unread-count')->assertStatus(401);
    }

    // --- GET /api/notifications (existing, test enriched payload) ---

    public function test_notification_index_returns_enriched_data_fields(): void
    {
        $user = $this->makeUser();

        \DB::table('notifications')->insert([
            'id' => Str::uuid()->toString(),
            'type' => 'App\\Notifications\\RequestSubmittedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'type' => 'request_submitted',
                'message' => 'تم تقديم طلب جديد',
                'request_id' => 42,
                'reference_number' => 'YFH-2026-000042',
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/notifications');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.data.type', 'request_submitted');
        $response->assertJsonPath('data.0.data.message', 'تم تقديم طلب جديد');
        $response->assertJsonPath('data.0.data.request_id', 42);
        $response->assertJsonPath('data.0.data.reference_number', 'YFH-2026-000042');
    }

    // --- POST /api/notifications/{id}/read ---

    public function test_mark_read_returns_403_for_another_users_notification(): void
    {
        $userA = $this->makeUser();
        $userB = $this->makeUser();

        $notifId = Str::uuid()->toString();
        \DB::table('notifications')->insert([
            'id' => $notifId,
            'type' => 'App\\Notifications\\RequestSubmittedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $userA->id,
            'data' => json_encode(['type' => 'request_submitted', 'message' => 'Test', 'request_id' => null, 'reference_number' => null]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($userB)->postJson("/api/notifications/{$notifId}/read");

        $response->assertStatus(403);
    }
}

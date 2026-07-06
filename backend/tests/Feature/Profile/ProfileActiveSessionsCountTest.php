<?php

namespace Tests\Feature\Profile;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\TestCase;

class ProfileActiveSessionsCountTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernance();
    }

    public function test_active_sessions_count_is_scoped_to_authenticated_user_tokens(): void
    {
        $user = $this->user('profile-user@example.test');
        $other = $this->user('other-profile-user@example.test');

        $this->tokenFor($user, null);
        $this->tokenFor($user, now()->subHours(2));
        $this->tokenFor($user, now()->subDays(2));
        $this->tokenFor($other, now()->subHour());
        $this->tokenFor($other, null);

        $this->actingAs($user)
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.active_sessions_count', 2);
    }

    private function user(string $email): User
    {
        return $this->assignGovernanceIdentity(User::query()->create([
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => UserRole::DATA_ENTRY->value,
            'is_active' => true,
        ]), UserRole::DATA_ENTRY);
    }

    private function tokenFor(User $user, mixed $lastUsedAt): void
    {
        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => $user->getMorphClass(),
            'tokenable_id' => $user->id,
            'name' => 'test-token',
            'token' => hash('sha256', uniqid('', true)),
            'abilities' => json_encode(['*']),
            'last_used_at' => $lastUsedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

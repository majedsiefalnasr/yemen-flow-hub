<?php

namespace Tests\Feature\Api;

use App\Models\Bank;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Guards ARCH-003: the authenticated v1 API group carries a default per-user
 * request throttle (throttle:api-default) so a single client cannot drive
 * unbounded volume against the expensive list/report/dashboard endpoints. The
 * limiter is keyed per authenticated user, not a shared global bucket.
 */
class ApiDefaultThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        // Make the 429 body assertable and keep the limiter cache clean per test.
        ThrottleRequests::shouldHashKeys(false);
        RateLimiter::clear('api-default');
    }

    private function bankUser(string $email): User
    {
        $org = Organization::where('code', 'commercial_banks')->firstOrFail();
        $role = Role::where('code', 'intake')->firstOrFail();
        $suffix = uniqid();
        $bank = Bank::create(['name' => "Throttle Bank {$suffix}", 'code' => 'THR'.$suffix, 'is_active' => true, 'organization_id' => $org->id]);

        $user = User::create([
            'name' => 'Throttle U',
            'email' => $email,
            'password' => bcrypt('pass'),
            'bank_id' => $bank->id,
            'organization_id' => $org->id,
            'is_active' => true,
        ]);
        $user->roles()->attach($role);

        return $user->fresh(['roles']);
    }

    public function test_authenticated_v1_requests_are_capped_and_return_429(): void
    {
        config(['auth_security.api_throttle_per_minute' => 3]);
        $user = $this->bankUser('cap@throttle.test');

        // The first N requests within the window succeed.
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user)
                ->getJson('/api/v1/engine-requests')
                ->assertOk();
        }

        // The next one crosses the cap → 429 via the API throttle exception handler,
        // carrying the machine-readable code and the limiter's backoff headers.
        $this->actingAs($user)
            ->getJson('/api/v1/engine-requests')
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'RATE_LIMITED')
            ->assertHeader('Retry-After');
    }

    public function test_throttle_bucket_is_per_user_not_shared(): void
    {
        config(['auth_security.api_throttle_per_minute' => 2]);
        $userA = $this->bankUser('a@throttle.test');
        $userB = $this->bankUser('b@throttle.test');

        // Exhaust user A's bucket.
        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($userA)->getJson('/api/v1/engine-requests')->assertOk();
        }
        $this->actingAs($userA)->getJson('/api/v1/engine-requests')->assertStatus(429);

        // User B still has a full bucket — the limiter must not be a shared global.
        $this->actingAs($userB)->getJson('/api/v1/engine-requests')->assertOk();
    }
}

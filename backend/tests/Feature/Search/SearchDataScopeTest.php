<?php

namespace Tests\Feature\Search;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\TestCase;

class SearchDataScopeTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);
    }

    public function test_bank_admin_only_searches_users_in_their_bank(): void
    {
        $bankA = Bank::create(['name' => 'Bank A', 'code' => 'BA', 'is_active' => true]);
        $bankB = Bank::create(['name' => 'Bank B', 'code' => 'BB', 'is_active' => true]);

        $adminA = $this->user(UserRole::BANK_ADMIN, $bankA, 'admin@banka.test');

        // Users to find
        $this->user(UserRole::DATA_ENTRY, $bankA, 'user1@banka.test');
        $this->user(UserRole::DATA_ENTRY, $bankB, 'user2@bankb.test');

        $response = $this->actingAs($adminA)
            ->getJson('/api/search?q=user')
            ->assertOk();

        $emails = collect($response->json('data.users'))->pluck('email')->all();
        $this->assertContains('user1@banka.test', $emails);
        $this->assertNotContains('user2@bankb.test', $emails);
    }

    public function test_system_admin_searches_users_system_wide(): void
    {
        $bankA = Bank::create(['name' => 'Bank A', 'code' => 'BA', 'is_active' => true]);
        $bankB = Bank::create(['name' => 'Bank B', 'code' => 'BB', 'is_active' => true]);

        $sysAdmin = $this->user(UserRole::CBY_ADMIN, null, 'sysadmin@cby.test');

        $this->user(UserRole::DATA_ENTRY, $bankA, 'user1@banka.test');
        $this->user(UserRole::DATA_ENTRY, $bankB, 'user2@bankb.test');

        $response = $this->actingAs($sysAdmin)
            ->getJson('/api/search?q=user')
            ->assertOk();

        $emails = collect($response->json('data.users'))->pluck('email')->all();
        $this->assertContains('user1@banka.test', $emails);
        $this->assertContains('user2@bankb.test', $emails);
    }

    public function test_system_admin_searches_banks_system_wide(): void
    {
        Bank::create(['name' => 'Target Bank A', 'code' => 'TBA', 'is_active' => true]);
        Bank::create(['name' => 'Target Bank B', 'code' => 'TBB', 'is_active' => true]);

        $sysAdmin = $this->user(UserRole::CBY_ADMIN, null, 'sysadmin@cby.test');

        $response = $this->actingAs($sysAdmin)
            ->getJson('/api/search?q=Target')
            ->assertOk();

        $names = collect($response->json('data.banks'))->pluck('name')->all();
        $this->assertContains('Target Bank A', $names);
        $this->assertContains('Target Bank B', $names);
    }

    public function test_bank_admin_cannot_search_banks(): void
    {
        Bank::create(['name' => 'Target Bank A', 'code' => 'TBA', 'is_active' => true]);

        $bankA = Bank::create(['name' => 'Bank A', 'code' => 'BA', 'is_active' => true]);
        $adminA = $this->user(UserRole::BANK_ADMIN, $bankA, 'admin@banka.test');

        $response = $this->actingAs($adminA)
            ->getJson('/api/search?q=Target')
            ->assertOk();

        $this->assertEmpty($response->json('data.banks'));
    }

    private function user(UserRole $role, ?Bank $bank, string $email): User
    {
        return $this->assignGovernanceIdentity(User::query()->create([
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]), $role);
    }
}

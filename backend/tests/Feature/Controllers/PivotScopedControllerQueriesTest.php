<?php

namespace Tests\Feature\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PivotScopedControllerQueriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);
        $this->seed(BankSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_bank_admin_only_sees_own_bank_in_bank_list(): void
    {
        $bankAdmin = User::query()->where('role', UserRole::BANK_ADMIN->value)->firstOrFail();

        $response = $this->actingAs($bankAdmin)->getJson('/api/banks');
        $response->assertOk();

        $bankIds = collect($response->json('data.data'))->pluck('id')->all();
        $this->assertEquals([$bankAdmin->bank_id], $bankIds);
    }

    public function test_cby_admin_sees_all_banks(): void
    {
        $admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();

        $response = $this->actingAs($admin)->getJson('/api/banks');
        $response->assertOk();

        $this->assertGreaterThan(1, count($response->json('data.data')));
    }

    public function test_bank_admin_user_list_only_shows_manageable_roles(): void
    {
        $bankAdmin = User::query()->where('role', UserRole::BANK_ADMIN->value)->firstOrFail();

        $response = $this->actingAs($bankAdmin)->getJson('/api/users');
        $response->assertOk();

        $roles = collect($response->json('data.data'))->pluck('role')->unique()->all();
        sort($roles);
        $this->assertEquals([UserRole::BANK_REVIEWER->value, UserRole::DATA_ENTRY->value], $roles);
    }

    public function test_search_users_forbidden_for_data_entry(): void
    {
        $entry = User::query()->where('role', UserRole::DATA_ENTRY->value)->firstOrFail();

        $response = $this->actingAs($entry)->getJson('/api/search?q=test&type=users');
        $response->assertOk();
        $this->assertEmpty($response->json('data.users'));
    }
}

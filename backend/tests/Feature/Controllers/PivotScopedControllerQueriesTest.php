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

    public function test_search_users_forbidden_for_data_entry(): void
    {
        $entry = $this->firstUserWithRole(UserRole::DATA_ENTRY);

        $response = $this->actingAs($entry)->getJson('/api/search?q=test&type=users');
        $response->assertOk();
        $this->assertEmpty($response->json('data.users'));
    }
}

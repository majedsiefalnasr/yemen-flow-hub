<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyRouteAbsentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);
    }

    public function test_legacy_users_route_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/users')->assertNotFound();
    }

    public function test_legacy_banks_route_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/banks')->assertNotFound();
    }

    public function test_legacy_audit_route_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/audit')->assertNotFound();
    }

    public function test_legacy_report_presets_route_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/report-presets')->assertNotFound();
    }

    public function test_legacy_notifications_route_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/notifications')->assertNotFound();
    }
}

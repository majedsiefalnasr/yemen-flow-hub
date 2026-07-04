<?php

namespace Tests\Feature\Governance;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();
        $this->organization = Organization::query()->where('code', 'commercial_banks')->firstOrFail();
    }

    public function test_create_team_and_filter_by_organization(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/teams', [
            'organization_id' => $this->organization->id,
            'code' => 'treasury',
            'name' => 'Treasury',
        ])->assertCreated()
            ->assertJsonPath('data.organization.code', 'commercial_banks')
            ->assertJsonMissingPath('data.role_code');

        $this->actingAs($this->admin)->getJson('/api/v1/teams?organization_id='.$this->organization->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $response->json('data.id'), 'code' => 'treasury']);
    }

    public function test_rejects_role_code_duplicate_and_stale_version(): void
    {
        $payload = ['organization_id' => $this->organization->id, 'code' => 'treasury', 'name' => 'Treasury'];
        $this->actingAs($this->admin)->postJson('/api/v1/teams', $payload)->assertCreated();
        $this->actingAs($this->admin)->postJson('/api/v1/teams', $payload)->assertUnprocessable();
        $this->actingAs($this->admin)->postJson('/api/v1/teams', [...$payload, 'code' => 'x', 'role_code' => 'admin'])->assertUnprocessable();

        $team = Team::query()->where('code', 'treasury')->firstOrFail();
        $this->actingAs($this->admin)->putJson("/api/v1/teams/{$team->id}", ['name' => 'Changed', 'version' => 99])
            ->assertConflict()->assertJsonPath('error.code', 'STALE_RESOURCE');
    }

    public function test_in_use_and_system_teams_are_protected(): void
    {
        $team = Team::query()->where('code', 'administration')->firstOrFail();
        $this->actingAs($this->admin)->postJson("/api/v1/teams/{$team->id}/deactivate")
            ->assertStatus(422)->assertJsonPath('error.code', 'TEAM_IN_USE');
        $this->actingAs($this->admin)->deleteJson("/api/v1/teams/{$team->id}")
            ->assertStatus(422)->assertJsonPath('error.code', 'TEAM_PROTECTED');
    }
}

<?php

namespace Tests\Feature\Governance;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, GovernanceSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();
    }

    public function test_create_list_update_activate_and_deactivate(): void
    {
        $created = $this->actingAs($this->admin)->postJson('/api/v1/organizations', [
            'code' => 'test_agency',
            'name' => 'Test Agency',
        ])->assertCreated()
            ->assertJsonPath('data.code', 'test_agency')
            ->assertJsonPath('data.is_active', true);

        $id = $created->json('data.id');
        $this->actingAs($this->admin)->getJson('/api/v1/organizations?search=test_agency')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($this->admin)->putJson("/api/v1/organizations/{$id}", [
            'name' => 'Renamed Agency',
            'version' => 1,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Renamed Agency')
            ->assertJsonPath('data.version', 2);

        $this->actingAs($this->admin)->postJson("/api/v1/organizations/{$id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
        $this->actingAs($this->admin)->postJson("/api/v1/organizations/{$id}/activate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    }

    public function test_protection_in_use_and_concurrency_rules(): void
    {
        $system = Organization::query()->where('code', 'commercial_banks')->firstOrFail();

        $this->actingAs($this->admin)->deleteJson("/api/v1/organizations/{$system->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ORGANIZATION_PROTECTED');

        $this->actingAs($this->admin)->postJson("/api/v1/organizations/{$system->id}/deactivate")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ORGANIZATION_IN_USE');

        $this->actingAs($this->admin)->putJson("/api/v1/organizations/{$system->id}", [
            'code' => 'changed',
            'name' => $system->name,
            'version' => 1,
        ])->assertUnprocessable();

        $this->actingAs($this->admin)->putJson("/api/v1/organizations/{$system->id}", [
            'name' => 'Changed',
            'version' => 99,
        ])->assertConflict()
            ->assertJsonPath('error.code', 'STALE_RESOURCE');
    }

    public function test_mutations_are_audited(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/organizations', [
            'code' => 'audited',
            'name' => 'Audited',
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'GOVERNANCE_CREATED',
            'subject_type' => Organization::class,
        ]);
    }
}

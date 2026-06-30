<?php

namespace Tests\Feature\Governance;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Rules\RoleBelongsToOrganization;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, GovernanceSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();
    }

    public function test_create_duplicate_and_stale_role_rules(): void
    {
        $org = Organization::query()->where('code', 'commercial_banks')->firstOrFail();
        $payload = ['organization_id' => $org->id, 'code' => 'treasurer', 'name' => 'Treasurer'];
        $created = $this->actingAs($this->admin)->postJson('/api/v1/roles', $payload)->assertCreated();
        $this->actingAs($this->admin)->postJson('/api/v1/roles', $payload)->assertUnprocessable();
        $this->actingAs($this->admin)->putJson('/api/v1/roles/'.$created->json('data.id'), ['name' => 'Changed', 'version' => 99])
            ->assertConflict()->assertJsonPath('error.code', 'STALE_RESOURCE');
    }

    public function test_assigned_and_system_roles_are_protected(): void
    {
        $role = Role::query()->where('code', 'system_admin')->firstOrFail();
        $this->actingAs($this->admin)->postJson("/api/v1/roles/{$role->id}/deactivate")->assertStatus(422);
        $this->actingAs($this->admin)->deleteJson("/api/v1/roles/{$role->id}")->assertStatus(422);
    }

    public function test_role_belongs_to_user_organization_rule(): void
    {
        $bankOrg = Organization::query()->where('code', 'commercial_banks')->firstOrFail();
        $committeeRole = Role::query()->where('code', 'support')->firstOrFail();
        $validator = Validator::make(['role_id' => $committeeRole->id], [
            'role_id' => [new RoleBelongsToOrganization($bankOrg->id)],
        ]);

        $this->assertTrue($validator->fails());
    }
}

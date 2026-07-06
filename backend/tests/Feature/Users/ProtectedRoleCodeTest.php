<?php

namespace Tests\Feature\Users;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProtectedRoleCodeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, UserSeeder::class]);
        $this->admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();
    }

    public function test_protected_role_cannot_be_deleted(): void
    {
        $role = Role::query()->where('code', 'system_admin')->firstOrFail();

        $this->actingAs($this->admin)->deleteJson("/api/v1/roles/{$role->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ROLE_CODE_PROTECTED');
    }

    public function test_protected_role_cannot_be_deactivated(): void
    {
        $role = Role::query()->where('code', 'fx_swift')->firstOrFail();

        $this->actingAs($this->admin)->postJson("/api/v1/roles/{$role->id}/deactivate")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ROLE_CODE_PROTECTED');
    }

    public function test_protected_role_display_name_can_still_be_updated(): void
    {
        $role = Role::query()->where('code', 'system_admin')->firstOrFail();

        $this->actingAs($this->admin)->putJson("/api/v1/roles/{$role->id}", [
            'name' => 'مدير النظام المحدّث',
            'version' => $role->version,
        ])->assertOk()
            ->assertJsonPath('data.name', 'مدير النظام المحدّث');
    }
}

<?php

namespace Tests\Feature\Engine;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests BANK_ADMIN RBAC via the V1 users endpoint, which uses UserPolicy.
 * The policy grants BANK_ADMIN list/view/create/update/delete on users only
 * within their own bank and only bank-role targets.
 */
class EngineBankAdminRbacTest extends TestCase
{
    use RefreshDatabase;

    private User $bankAdmin;

    private User $bankAdminB;

    private User $dataEntry;

    private User $dataEntryB;

    private Bank $bankA;

    private Bank $bankB;

    private Organization $bankOrg;

    private Role $intakeRole;

    private Team $entryTeam;

    private Role $bankAdminRole;

    private Team $bankAdminTeam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        $this->bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $this->intakeRole = Role::where('code', 'intake')->firstOrFail();
        $this->entryTeam = Team::where('code', 'entry')->firstOrFail();
        $this->bankAdminRole = Role::where('code', 'bank_admin')->firstOrFail();
        $this->bankAdminTeam = Team::where('code', 'bank_admin')->firstOrFail();

        $this->bankA = Bank::create([
            'name' => 'RBAC Bank A',
            'code' => 'RBA',
            'is_active' => true,
            'organization_id' => $this->bankOrg->id,
        ]);
        $this->bankB = Bank::create([
            'name' => 'RBAC Bank B',
            'code' => 'RBB',
            'is_active' => true,
            'organization_id' => $this->bankOrg->id,
        ]);

        $this->bankAdmin = $this->makeUser('admin@rbac.test', UserRole::BANK_ADMIN, $this->bankA, $this->bankAdminRole, $this->bankAdminTeam);
        $this->bankAdminB = $this->makeUser('adminb@rbac.test', UserRole::BANK_ADMIN, $this->bankB, $this->bankAdminRole, $this->bankAdminTeam);
        $this->dataEntry = $this->makeUser('de@rbac.test', UserRole::DATA_ENTRY, $this->bankA, $this->intakeRole, $this->entryTeam);
        $this->dataEntryB = $this->makeUser('deb@rbac.test', UserRole::DATA_ENTRY, $this->bankB, $this->intakeRole, $this->entryTeam);
    }

    private function makeUser(string $email, UserRole $role, Bank $bank, Role $governanceRole, Team $team): User
    {
        $user = User::create([
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => $role,
            'bank_id' => $bank->id,
            'organization_id' => $this->bankOrg->id,
            'is_active' => true,
        ]);
        $user->teams()->attach($team);
        $user->roles()->attach($governanceRole);

        return $user;
    }

    public function test_bank_admin_can_list_users_in_their_bank(): void
    {
        $response = $this->actingAs($this->bankAdmin)->getJson('/api/v1/users');

        $response->assertOk();
        $userIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($this->bankAdmin->id, $userIds);
        $this->assertContains($this->dataEntry->id, $userIds);
    }

    public function test_bank_admin_cannot_see_users_from_another_bank(): void
    {
        $response = $this->actingAs($this->bankAdmin)->getJson('/api/v1/users');

        $response->assertOk();
        $userIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($this->dataEntryB->id, $userIds);
        $this->assertNotContains($this->bankAdminB->id, $userIds);
    }

    public function test_data_entry_cannot_list_users(): void
    {
        // DATA_ENTRY role lacks viewAny User policy permission
        $this->actingAs($this->dataEntry)
            ->getJson('/api/v1/users')
            ->assertForbidden();
    }

    public function test_bank_admin_can_view_own_bank_user(): void
    {
        $this->actingAs($this->bankAdmin)
            ->getJson("/api/v1/users/{$this->dataEntry->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $this->dataEntry->id);
    }

    public function test_bank_admin_cannot_view_user_from_another_bank(): void
    {
        $this->actingAs($this->bankAdmin)
            ->getJson("/api/v1/users/{$this->dataEntryB->id}")
            ->assertForbidden();
    }

    public function test_bank_admin_can_deactivate_own_bank_user(): void
    {
        $target = $this->makeUser('target@rbac.test', UserRole::DATA_ENTRY, $this->bankA, $this->intakeRole, $this->entryTeam);

        $response = $this->actingAs($this->bankAdmin)
            ->postJson("/api/v1/users/{$target->id}/deactivate");

        // Either 200 (deactivated) or 422 (has active work) — both indicate the
        // policy gate passed. A 403 would mean RBAC blocked it.
        $this->assertNotEquals(403, $response->getStatusCode(), 'Bank admin should not be forbidden from deactivating own bank user.');
    }

    public function test_bank_admin_cannot_deactivate_user_from_another_bank(): void
    {
        $this->actingAs($this->bankAdmin)
            ->postJson("/api/v1/users/{$this->dataEntryB->id}/deactivate")
            ->assertForbidden();
    }

    public function test_data_entry_cannot_deactivate_any_user(): void
    {
        $target = $this->makeUser('victim@rbac.test', UserRole::DATA_ENTRY, $this->bankA, $this->intakeRole, $this->entryTeam);

        $this->actingAs($this->dataEntry)
            ->postJson("/api/v1/users/{$target->id}/deactivate")
            ->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Engine;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Screen;
use App\Models\ScreenPermission;
use App\Models\Team;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\Authorization\PermissionService;
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

    private function grantStaffViewToIntake(): void
    {
        ScreenPermission::query()->create([
            'role_id' => $this->intakeRole->id,
            'screen_id' => Screen::query()->where('key', 'staff')->value('id'),
            'capability' => 'VIEW',
        ]);

        app(PermissionService::class)->clearScreenPermissionCache($this->intakeRole->id);
    }

    private function createUserPayload(Bank $bank, string $email): array
    {
        return [
            'organization_id' => $this->bankOrg->id,
            'team_id' => $this->entryTeam->id,
            'role_id' => $this->intakeRole->id,
            'bank_id' => $bank->id,
            'name' => 'Delegated staff target',
            'email' => $email,
            'password' => 'ValidPassword123!',
            'is_active' => true,
        ];
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

    public function test_staff_capability_holder_lists_only_own_bank_users(): void
    {
        $this->grantStaffViewToIntake();

        $response = $this->actingAs($this->dataEntry)->getJson('/api/v1/users')->assertOk();
        $userIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($this->dataEntry->id, $userIds);
        $this->assertContains($this->bankAdmin->id, $userIds);
        $this->assertNotContains($this->dataEntryB->id, $userIds);
        $this->assertNotContains($this->bankAdminB->id, $userIds);
    }

    public function test_staff_capability_holder_can_manage_eligible_own_bank_user_only(): void
    {
        $this->grantStaffViewToIntake();

        $target = $this->makeUser(
            'eligible-target@rbac.test',
            UserRole::DATA_ENTRY,
            $this->bankA,
            $this->intakeRole,
            $this->entryTeam
        );

        $policy = app(UserPolicy::class);

        $this->assertTrue($policy->viewAny($this->dataEntry));
        $this->assertTrue($policy->create($this->dataEntry));
        $this->assertTrue($policy->view($this->dataEntry, $target));
        $this->assertTrue($policy->update($this->dataEntry, $target));
        $this->assertTrue($policy->delete($this->dataEntry, $target));
        $this->assertTrue($policy->resetPassword($this->dataEntry, $target));
        $this->assertTrue($policy->resetMfa($this->dataEntry, $target));
        $this->assertTrue($policy->resetPin($this->dataEntry, $target));

        $this->assertFalse($policy->view($this->dataEntry, $this->dataEntryB));
        $this->assertFalse($policy->update($this->dataEntry, $this->dataEntryB));
        $this->assertFalse($policy->delete($this->dataEntry, $this->dataEntryB));
    }

    public function test_revoking_staff_view_removes_bank_admin_users_api_access(): void
    {
        $staffScreenId = Screen::query()->where('key', 'staff')->value('id');
        ScreenPermission::query()
            ->where('role_id', $this->bankAdminRole->id)
            ->where('screen_id', $staffScreenId)
            ->delete();
        app(PermissionService::class)->clearScreenPermissionCache($this->bankAdminRole->id);

        $this->actingAs($this->bankAdmin)->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_staff_capability_holder_can_create_only_in_own_bank(): void
    {
        $this->grantStaffViewToIntake();

        $this->actingAs($this->dataEntry)
            ->postJson('/api/v1/users', $this->createUserPayload($this->bankA, 'own-bank@rbac.test'))
            ->assertCreated()
            ->assertJsonPath('data.bank.id', $this->bankA->id);

        $this->actingAs($this->dataEntry)
            ->postJson('/api/v1/users', $this->createUserPayload($this->bankB, 'other-bank@rbac.test'))
            ->assertForbidden();
    }
}

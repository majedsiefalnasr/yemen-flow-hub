<?php

namespace Tests\Feature\Users;

use App\Enums\UserRole;
use App\Http\Resources\AuthMeResource;
use App\Http\Resources\UserResource;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\LegacyRoleMapper;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\TestCase;

class RoleModelTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernance();
        $this->seed(BankSeeder::class);
    }

    public function test_user_role_returns_single_active_pivot_role(): void
    {
        $this->seed(UserSeeder::class);

        $user = User::query()->with('roles')->where('role', UserRole::DATA_ENTRY->value)->firstOrFail();

        $this->assertSame('intake', $user->role()?->code);
        $this->assertSame(UserRole::DATA_ENTRY, $user->legacyRole());
        $this->assertSame(1, $user->roles()->wherePivot('is_active', true)->count());
    }

    public function test_changing_active_role_deactivates_prior_pivot_row(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makeBankUser(UserRole::DATA_ENTRY, 'entry@bank.test');
        $reviewerRole = Role::query()->where('code', 'internal_reviewer')->firstOrFail();
        $team = Team::query()->where('code', 'internal_review')->firstOrFail();

        $this->actingAs($admin)->putJson("/api/v1/users/{$user->id}", [
            'organization_id' => $user->organization_id,
            'team_id' => $team->id,
            'role_id' => $reviewerRole->id,
            'bank_id' => $user->bank_id,
            'name' => $user->name,
            'email' => $user->email,
            'version' => $user->version,
        ])->assertOk();

        $user->refresh()->load('roles');
        $this->assertSame('internal_reviewer', $user->role()?->code);
        $this->assertSame(1, $user->roles()->wherePivot('is_active', true)->count());
        $this->assertGreaterThanOrEqual(1, $user->roles()->wherePivot('is_active', false)->count());
    }

    public function test_user_resource_serializes_role_from_pivot_not_stale_column(): void
    {
        $user = $this->makeBankUser(UserRole::DATA_ENTRY, 'pivot@bank.test');
        $user->forceFill(['role' => UserRole::BANK_REVIEWER->value])->save();

        $payload = (new UserResource($user->load('roles', 'bank')))->resolve();

        $this->assertSame(UserRole::DATA_ENTRY->value, $payload['role']);
    }

    public function test_auth_me_resource_role_matches_pivot(): void
    {
        $user = $this->makeBankUser(UserRole::SWIFT_OFFICER, 'swift@bank.test')->load('organization', 'teams', 'roles', 'bank');
        $payload = (new AuthMeResource($user))->resolve();

        $this->assertSame('fx_swift', $payload['role']['code']);
        $this->assertSame(UserRole::SWIFT_OFFICER->value, $payload['user']['role']);
    }

    public function test_legacy_role_mapper_maps_committee_director_code(): void
    {
        $this->assertSame(
            UserRole::COMMITTEE_DIRECTOR,
            LegacyRoleMapper::toLegacyEnum('committee_director')
        );
    }

    public function test_role_ids_plural_assignment_is_prohibited(): void
    {
        $admin = $this->makeAdmin();
        $org = Organization::query()->where('code', 'commercial_banks')->firstOrFail();
        $team = Team::query()->where('code', 'entry')->firstOrFail();
        $role = Role::query()->where('code', 'intake')->firstOrFail();
        $bank = \App\Models\Bank::query()->firstOrFail();

        $this->actingAs($admin)->postJson('/api/v1/users', [
            'organization_id' => $org->id,
            'team_id' => $team->id,
            'role_ids' => [$role->id, Role::query()->where('code', 'internal_reviewer')->value('id')],
            'bank_id' => $bank->id,
            'name' => 'Duplicate Role',
            'email' => 'duplicate@bank.test',
            'password' => 'Password123!',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['role_ids']);
    }

    private function makeAdmin(): User
    {
        return $this->assignGovernanceIdentity(User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@test.gov',
            'password' => Hash::make('Password123'),
            'role' => UserRole::CBY_ADMIN->value,
            'is_active' => true,
        ]), UserRole::CBY_ADMIN);
    }

    private function makeBankUser(UserRole $role, string $email): User
    {
        $bank = \App\Models\Bank::query()->firstOrFail();

        return $this->assignGovernanceIdentity(User::query()->create([
            'name' => $role->value,
            'email' => $email,
            'password' => Hash::make('Password123'),
            'role' => $role->value,
            'bank_id' => $bank->id,
            'is_active' => true,
        ]), $role);
    }
}

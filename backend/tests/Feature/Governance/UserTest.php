<?php

namespace Tests\Feature\Governance;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, GovernanceSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();
    }

    public function test_creates_bank_and_committee_users_with_nested_identity(): void
    {
        $bankOrg = Organization::query()->where('code', 'commercial_banks')->firstOrFail();
        $bank = Bank::query()->where('is_active', true)->firstOrFail();
        $bankUser = $this->actingAs($this->admin)->postJson('/api/v1/users', [
            'organization_id' => $bankOrg->id,
            'team_id' => Team::query()->where('code', 'entry')->value('id'),
            'role_id' => Role::query()->where('code', 'intake')->value('id'),
            'bank_id' => $bank->id,
            'name' => 'Bank User',
            'email' => 'bank-user@test.local',
            'password' => 'Password123',
        ])->assertCreated();
        $bankUser->assertJsonPath('data.organization.code', 'commercial_banks')
            ->assertJsonPath('data.team.code', 'entry')
            ->assertJsonPath('data.role.code', 'intake')
            ->assertJsonPath('data.bank.id', $bank->id);

        $committeeOrg = Organization::query()->where('code', 'national_committee')->firstOrFail();
        $this->actingAs($this->admin)->postJson('/api/v1/users', [
            'organization_id' => $committeeOrg->id,
            'team_id' => Team::query()->where('code', 'support')->value('id'),
            'role_id' => Role::query()->where('code', 'support')->value('id'),
            'bank_id' => $bank->id,
            'name' => 'Committee User',
            'email' => 'committee-user@test.local',
            'password' => 'Password123',
        ])->assertCreated()->assertJsonPath('data.bank', null);
    }

    public function test_rejects_cross_organization_team_role_and_missing_bank(): void
    {
        $bankOrg = Organization::query()->where('code', 'commercial_banks')->firstOrFail();
        $this->actingAs($this->admin)->postJson('/api/v1/users', [
            'organization_id' => $bankOrg->id,
            'team_id' => Team::query()->where('code', 'support')->value('id'),
            'role_id' => Role::query()->where('code', 'support')->value('id'),
            'name' => 'Invalid',
            'email' => 'invalid@test.local',
            'password' => 'Password123',
        ])->assertUnprocessable();
    }

    public function test_deactivation_blocks_active_work_and_invalidates_sessions(): void
    {
        $target = User::query()->where('role', UserRole::SUPPORT_COMMITTEE->value)->firstOrFail();
        $target->createToken('active');
        $bank = Bank::query()->firstOrFail();
        $creator = User::query()->where('bank_id', $bank->id)->firstOrFail();
        app()->instance('workflow.transition.active', true);
        $request = ImportRequest::query()->create([
            'bank_id' => $bank->id, 'created_by' => $creator->id, 'currency' => 'USD',
            'amount' => 1, 'supplier_name' => 'X', 'goods_description' => 'X',
            'port_of_entry' => 'Aden', 'status' => RequestStatus::SUPPORT_REVIEW_IN_PROGRESS,
            'current_owner_role' => UserRole::SUPPORT_COMMITTEE, 'claimed_by' => $target->id,
        ]);
        app()->offsetUnset('workflow.transition.active');

        $this->actingAs($this->admin)->postJson("/api/v1/users/{$target->id}/deactivate")
            ->assertStatus(422)->assertJsonPath('error.code', 'USER_HAS_ACTIVE_WORK');
        $request->forceDelete();
        $this->actingAs($this->admin)->postJson("/api/v1/users/{$target->id}/deactivate")->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $target->id]);
    }

    public function test_reset_password_and_mfa_are_audited(): void
    {
        $target = User::query()->where('role', UserRole::SUPPORT_COMMITTEE->value)->firstOrFail();
        $this->actingAs($this->admin)->postJson("/api/v1/users/{$target->id}/reset-password", [
            'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123',
        ])->assertOk();
        $this->actingAs($this->admin)->postJson("/api/v1/users/{$target->id}/reset-mfa")->assertOk();
        $this->assertDatabaseHas('audit_logs', ['subject_id' => $target->id, 'action' => 'PASSWORD_RESET']);
        $this->assertDatabaseHas('audit_logs', ['subject_id' => $target->id, 'action' => 'MFA_RESET']);
    }
}

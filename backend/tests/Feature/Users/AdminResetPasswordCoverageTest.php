<?php

namespace Tests\Feature\Users;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\TestCase;

class AdminResetPasswordCoverageTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernance();
    }

    public function test_system_admin_can_reset_fx_swift_user_password(): void
    {
        $admin = $this->makeSystemAdmin();
        $target = $this->makePivotUser('fx_swift', 'swift-reset@bank.test', true);

        $this->actingAs($admin)->postJson("/api/v1/users/{$target->id}/reset-password", [
            'password' => 'TempPassword123',
            'password_confirmation' => 'TempPassword123',
        ])->assertOk();

        $this->assertTrue($target->refresh()->must_change_password);
    }

    public function test_system_admin_can_reset_fx_confirm_user_password(): void
    {
        $admin = $this->makeSystemAdmin();
        $target = $this->makePivotUser('fx_confirm', 'confirm-reset@committee.test', false);

        $this->actingAs($admin)->postJson("/api/v1/users/{$target->id}/reset-password", [
            'password' => 'TempPassword123',
            'password_confirmation' => 'TempPassword123',
        ])->assertOk();
    }

    public function test_self_admin_reset_password_is_forbidden(): void
    {
        $admin = $this->makeSystemAdmin();

        $this->actingAs($admin)->postJson("/api/v1/users/{$admin->id}/reset-password", [
            'password' => 'TempPassword123',
            'password_confirmation' => 'TempPassword123',
        ])->assertForbidden();
    }

    private function makeSystemAdmin(): User
    {
        return $this->assignGovernanceIdentity(User::query()->create([
            'name' => 'System Admin',
            'email' => 'sysadmin@test.gov',
            'password' => Hash::make('Password123'),
            'is_active' => true,
        ]), UserRole::CBY_ADMIN);
    }

    private function makePivotUser(string $roleCode, string $email, bool $withBank): User
    {
        $map = [
            'fx_swift' => ['commercial_banks', 'fx_ops', UserRole::SWIFT_OFFICER, true],
            'fx_confirm' => ['national_committee', 'executive', UserRole::EXECUTIVE_MEMBER, false],
        ];
        [$orgCode, $teamCode, $userRole, $keepsBank] = $map[$roleCode];
        $organization = Organization::query()->where('code', $orgCode)->firstOrFail();
        $team = Team::query()->whereBelongsTo($organization)->where('code', $teamCode)->firstOrFail();
        $role = Role::query()->whereBelongsTo($organization)->where('code', $roleCode)->firstOrFail();
        $bank = $withBank
            ? Bank::query()->create([
                'name' => 'Bank',
                'code' => 'BR'.random_int(10, 99),
                'is_active' => true,
                'organization_id' => $organization->id,
            ])
            : null;

        $user = User::query()->create([
            'name' => $roleCode,
            'email' => $email,
            'password' => Hash::make('Password123'),
            'organization_id' => $organization->id,
            'bank_id' => $keepsBank ? $bank?->id : null,
            'is_active' => true,
        ]);
        $user->teams()->sync([$team->id]);
        $user->assignActiveRole($role->id);

        return $user->fresh();
    }
}

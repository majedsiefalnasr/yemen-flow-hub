<?php

namespace Tests\Support;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\GovernanceSeeder;

/**
 * Assigns governance pivot identity (organization/team/role) to a user created
 * with a legacy UserRole enum value, mirroring UserSeeder::assignIdentity().
 *
 * Tests that build users via raw User::query()->create()/User::factory() (not
 * through UserSeeder) need this so pivot-based policy checks (hasRoleCode(),
 * hasAnyRoleCode()) see the same authority the legacy `role` enum implies.
 * Call $this->seedGovernance() once in setUp(), then assignGovernanceIdentity()
 * after creating each user.
 */
trait AssignsGovernanceIdentity
{
    protected function seedGovernance(): void
    {
        $this->seed(GovernanceSeeder::class);
    }

    protected function assignGovernanceIdentity(User $user, UserRole $legacyRole): User
    {
        $map = [
            UserRole::DATA_ENTRY->value => ['commercial_banks', 'entry', 'intake', true],
            UserRole::BANK_REVIEWER->value => ['commercial_banks', 'internal_review', 'internal_reviewer', true],
            UserRole::BANK_ADMIN->value => ['commercial_banks', 'bank_admin', 'bank_admin', true],
            UserRole::SWIFT_OFFICER->value => ['commercial_banks', 'fx_ops', 'fx_swift', true],
            UserRole::SUPPORT_COMMITTEE->value => ['national_committee', 'support', 'support', false],
            UserRole::EXECUTIVE_MEMBER->value => ['national_committee', 'executive', 'committee_manager', false],
            UserRole::COMMITTEE_DIRECTOR->value => ['national_committee', 'executive', 'committee_director', false],
            UserRole::CBY_ADMIN->value => ['system_administration', 'administration', 'system_admin', false],
        ];

        [$organizationCode, $teamCode, $roleCode, $keepsBank] = $map[$legacyRole->value];
        $organization = Organization::query()->where('code', $organizationCode)->firstOrFail();
        $team = Team::query()
            ->whereBelongsTo($organization)
            ->where('code', $teamCode)
            ->firstOrFail();
        $role = Role::query()
            ->whereBelongsTo($organization)
            ->where('code', $roleCode)
            ->firstOrFail();

        $user->forceFill([
            'organization_id' => $organization->id,
            'bank_id' => $keepsBank ? $user->bank_id : null,
        ])->save();
        $user->teams()->sync([$team->id]);
        $user->roles()->sync([$role->id]);

        return $user->fresh();
    }
}

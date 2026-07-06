<?php

namespace Tests\Feature\Governance;

use App\Enums\OrganizationClassification;
use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\Organization;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use App\Services\Workflow\StagePermissionResolver;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\TestCase;

class OrganizationClassificationTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            GovernanceSeeder::class,
            ScreenPermissionSeeder::class,
            BankSeeder::class,
            UserSeeder::class,
        ]);
        $this->admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();
    }

    public function test_migration_classifies_seeded_organizations(): void
    {
        $this->assertSame(
            OrganizationClassification::BANKING_SECTOR,
            Organization::query()->where('code', 'commercial_banks')->value('classification'),
        );
        $this->assertSame(
            OrganizationClassification::NATIONAL_COMMITTEE,
            Organization::query()->where('code', 'national_committee')->value('classification'),
        );
        $this->assertSame(
            OrganizationClassification::OTHER,
            Organization::query()->where('code', 'system_administration')->value('classification'),
        );
    }

    public function test_organization_crud_requires_and_returns_classification(): void
    {
        $created = $this->actingAs($this->admin)->postJson('/api/v1/organizations', [
            'code' => 'exchange_co',
            'name' => 'Exchange Company',
            'classification' => OrganizationClassification::BANKING_SECTOR->value,
        ])->assertCreated()
            ->assertJsonPath('data.classification', OrganizationClassification::BANKING_SECTOR->value);

        $this->actingAs($this->admin)->postJson('/api/v1/organizations', [
            'code' => 'missing_class',
            'name' => 'Missing',
        ])->assertUnprocessable();

        $id = $created->json('data.id');
        $this->actingAs($this->admin)->putJson("/api/v1/organizations/{$id}", [
            'name' => 'Exchange Company Updated',
            'classification' => OrganizationClassification::OTHER->value,
            'version' => 1,
        ])->assertOk()
            ->assertJsonPath('data.classification', OrganizationClassification::OTHER->value);
    }

    public function test_banking_sector_user_requires_bank_and_non_banking_user_nulls_bank(): void
    {
        $bankOrg = Organization::query()->where('code', 'commercial_banks')->firstOrFail();
        $committeeOrg = Organization::query()->where('code', 'national_committee')->firstOrFail();
        $bank = Bank::query()->firstOrFail();

        $this->actingAs($this->admin)->postJson('/api/v1/users', [
            'organization_id' => $bankOrg->id,
            'team_id' => $bankOrg->teams()->where('code', 'entry')->value('id'),
            'role_id' => $bankOrg->roles()->where('code', 'intake')->value('id'),
            'name' => 'Bank User',
            'email' => 'bank-user@class.test',
            'password' => 'Password1',
            'bank_id' => $bank->id,
        ])->assertCreated();

        $this->actingAs($this->admin)->postJson('/api/v1/users', [
            'organization_id' => $bankOrg->id,
            'team_id' => $bankOrg->teams()->where('code', 'entry')->value('id'),
            'role_id' => $bankOrg->roles()->where('code', 'intake')->value('id'),
            'name' => 'Missing Bank',
            'email' => 'missing-bank@class.test',
            'password' => 'Password1',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'BANK_REQUIRED');

        $this->actingAs($this->admin)->postJson('/api/v1/users', [
            'organization_id' => $committeeOrg->id,
            'team_id' => $committeeOrg->teams()->where('code', 'support')->value('id'),
            'role_id' => $committeeOrg->roles()->where('code', 'support')->value('id'),
            'name' => 'Committee User',
            'email' => 'committee-user@class.test',
            'password' => 'Password1',
            'bank_id' => $bank->id,
        ])->assertCreated();

        $this->assertNull(User::query()->where('email', 'committee-user@class.test')->value('bank_id'));
    }

    public function test_non_banking_user_cannot_create_engine_request(): void
    {
        $support = User::query()->where('role', UserRole::SUPPORT_COMMITTEE->value)->firstOrFail();
        $version = $this->createPublishedWorkflowForBankCreators();

        $this->actingAs($support)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $version->id,
            'data' => [],
        ])->assertForbidden()
            ->assertJsonPath('error_code', 'CREATION_NOT_ALLOWED_FOR_ORGANIZATION');

        $this->actingAs($support)->getJson('/api/v1/engine-requests/available-workflows')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_bank_user_can_still_create_engine_request(): void
    {
        $entry = User::query()->where('role', UserRole::DATA_ENTRY->value)->firstOrFail();
        $version = $this->createPublishedWorkflowForBankCreators();

        $this->actingAs($entry)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $version->id,
            'data' => [],
        ])->assertCreated();
    }

    private function createPublishedWorkflowForBankCreators(): WorkflowVersion
    {
        $bankOrg = Organization::query()->where('code', 'commercial_banks')->firstOrFail();
        $entryRole = Role::query()->where('code', 'intake')->firstOrFail();

        $definition = WorkflowDefinition::query()->create(['code' => 'wp1-create', 'name' => 'WP1 Create']);
        $version = WorkflowVersion::query()->create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'published_at' => now(),
        ]);
        $stage = WorkflowStage::query()->create([
            'workflow_version_id' => $version->id,
            'code' => 'create',
            'name' => 'Create',
            'is_initial' => true,
            'is_final' => true,
        ]);
        StagePermission::query()->create([
            'stage_id' => $stage->id,
            'organization_id' => $bankOrg->id,
            'role_id' => $entryRole->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Create',
        ]);

        return $version;
    }

    public function test_null_org_user_matches_no_stage_permissions(): void
    {
        $org = Organization::query()->where('code', 'commercial_banks')->firstOrFail();
        $user = User::query()->create([
            'name' => 'Null Org',
            'email' => 'null-org@class.test',
            'password' => bcrypt('password'),
            'role' => UserRole::DATA_ENTRY->value,
            'organization_id' => null,
            'is_active' => true,
        ]);

        $definition = WorkflowDefinition::query()->create(['code' => 'wp1-null-org', 'name' => 'WP1 Null Org']);
        $version = WorkflowVersion::query()->create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => 'PUBLISHED',
        ]);
        $stage = WorkflowStage::query()->create([
            'workflow_version_id' => $version->id,
            'code' => 'null-org-stage',
            'name' => 'Null Org Stage',
        ]);
        $row = StagePermission::query()->create([
            'stage_id' => $stage->id,
            'organization_id' => $org->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Org row',
        ]);

        $resolver = app(StagePermissionResolver::class);
        $identity = [
            'organization_id' => null,
            'team_ids' => [],
            'role_ids' => [],
            'user_id' => $user->id,
        ];

        $this->assertFalse($resolver->identityMatchesAny($identity, [$row], StageAccessLevel::EXECUTE));
    }
}

<?php

namespace Tests\Feature\Workflow;

use App\Enums\FieldType;
use App\Enums\OrganizationClassification;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\FieldDefinition;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use App\Models\WorkflowStage;
use App\Support\RoleCodes;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicFieldOptionsScopingTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bankA;
    private Bank $bankB;
    private User $bankAUser;
    private User $ncUser;
    private User $adminUser;
    private WorkflowVersion $version;
    private FieldDefinition $merchantField;
    private FieldDefinition $companyField;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class]);

        $this->bankA = Bank::query()->first();
        $this->bankB = Bank::create(['name' => 'Bank B', 'code' => 'BANKB', 'is_active' => true]);

        $this->bankAUser = User::factory()->create([
            'bank_id' => $this->bankA->id,
            'organization_id' => Organization::query()->where('classification', OrganizationClassification::BANKING_SECTOR)->first()->id,
        ]);
        $this->attachRole($this->bankAUser, 'intake');

        $this->ncUser = User::factory()->create([
            'organization_id' => Organization::query()->where('classification', OrganizationClassification::NATIONAL_COMMITTEE)->first()->id,
        ]);
        $this->attachRole($this->ncUser, 'support');

        $this->adminUser = User::factory()->create([
            'organization_id' => Organization::query()->where('classification', OrganizationClassification::NATIONAL_COMMITTEE)->first()->id,
        ]);
        $this->attachRole($this->adminUser, 'system_admin');

        $definition = WorkflowDefinition::create([
            'code' => 'TEST_WF',
            'name' => 'Test Workflow',
            'is_active' => true,
        ]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'version' => 1,
        ]);

        $group = $this->version->fieldGroups()->create(['name' => 'g', 'label' => 'G', 'sort_order' => 1]);

        $this->merchantField = $this->version->fieldDefinitions()->create([
            'field_group_id' => $group->id,
            'key' => 'merchant',
            'label' => 'Merchant',
            'type' => FieldType::DYNAMIC_SELECT,
            'dynamic_source' => 'MERCHANTS',
        ]);

        $this->companyField = $this->version->fieldDefinitions()->create([
            'field_group_id' => $group->id,
            'key' => 'company',
            'label' => 'Company',
            'type' => FieldType::DYNAMIC_SELECT,
            'dynamic_source' => 'MERCHANT_COMPANIES',
        ]);

        // Create merchants for both banks
        $merchantA = Merchant::create(['name' => 'Merchant A', 'bank_id' => $this->bankA->id, 'tax_number' => 'A1']);
        $merchantB = Merchant::create(['name' => 'Merchant B', 'bank_id' => $this->bankB->id, 'tax_number' => 'B1']);

        // Create companies for both merchants
        MerchantCompany::create([
            'name' => 'Company A',
            'merchant_id' => $merchantA->id,
            'commercial_registration_number' => 'CR-A',
            'is_active' => true,
        ]);
        MerchantCompany::create([
            'name' => 'Company B',
            'merchant_id' => $merchantB->id,
            'commercial_registration_number' => 'CR-B',
            'is_active' => true,
        ]);
    }

    private function attachRole(User $user, string $roleCode): void
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->attach($role);
    }

    public function test_bank_user_sees_only_own_bank_merchants_and_companies(): void
    {
        // Use the admin user but with a bank_id set to test scoping.
        // A regular bank user doesn't have 'workflow_designer' 'MANAGE' permission.
        $bankAAdmin = User::factory()->create([
            'bank_id' => $this->bankA->id,
            'organization_id' => Organization::query()->where('classification', OrganizationClassification::BANKING_SECTOR)->first()->id,
        ]);
        $this->attachRole($bankAAdmin, 'system_admin');

        $this->actingAs($bankAAdmin)
            ->getJson("/api/v1/workflow-versions/{$this->version->id}/fields/{$this->merchantField->id}/options")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'Merchant A');

        $this->actingAs($bankAAdmin)
            ->getJson("/api/v1/workflow-versions/{$this->version->id}/fields/{$this->companyField->id}/options")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'Company A');
    }

    public function test_nc_user_sees_all_merchants_and_companies_in_general_view(): void
    {
        $this->actingAs($this->adminUser)
            ->getJson("/api/v1/workflow-versions/{$this->version->id}/fields/{$this->merchantField->id}/options")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($this->adminUser)
            ->getJson("/api/v1/workflow-versions/{$this->version->id}/fields/{$this->companyField->id}/options")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_nc_user_on_request_sees_only_requests_bank_merchants_and_companies(): void
    {
        $stage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'STAGE_1',
            'name' => 'Stage 1',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'version' => 1,
        ]);

        // NC user must have VIEW access to the stage
        \App\Models\StagePermission::create([
            'stage_id' => $stage->id,
            'organization_id' => $this->ncUser->organization_id,
            'access_level' => \App\Enums\StageAccessLevel::VIEW,
            'display_label' => 'View',
            'version' => 1,
        ]);
        
        $request = EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'bank_id' => $this->bankA->id,
            'current_stage_id' => $stage->id,
            'reference' => 'REQ-001',
            'status' => 'ACTIVE',
            'created_by' => $this->bankAUser->id,
            'version' => 1,
        ]);

        $this->actingAs($this->ncUser)
            ->getJson("/api/v1/engine-requests/{$request->id}/form-schema")
            ->assertOk()
            ->assertJsonFragment(['label' => 'Merchant A'])
            ->assertJsonMissing(['label' => 'Merchant B'])
            ->assertJsonFragment(['label' => 'Company A'])
            ->assertJsonMissing(['label' => 'Company B']);
    }
}

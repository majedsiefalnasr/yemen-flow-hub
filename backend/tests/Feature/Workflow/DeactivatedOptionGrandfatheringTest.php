<?php

namespace Tests\Feature\Workflow;

use App\Enums\FieldType;
use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\FieldDefinition;
use App\Models\FieldGroup;
use App\Models\Merchant;
use App\Models\Organization;
use App\Models\ReferenceTable;
use App\Models\ReferenceValue;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use App\Services\Workflow\DynamicFieldOptionsResolver;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeactivatedOptionGrandfatheringTest extends TestCase
{
    use RefreshDatabase;

    private User $executor;

    private Bank $bank;

    private Merchant $activeMerchant;

    private Merchant $suspendedMerchant;

    private WorkflowVersion $version;

    private WorkflowStage $initialStage;

    private FieldDefinition $merchantField;

    private FieldDefinition $sectorField;

    private ReferenceValue $activeSector;

    private ReferenceValue $inactiveSector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);
        $this->setUpWorkflow();
    }

    private function setUpWorkflow(): void
    {
        $bankOrg = Organization::where('code', 'commercial_banks')->first();
        $entryRole = Role::where('code', 'intake')->first();
        $entryTeam = Team::where('code', 'entry')->first();

        $this->bank = Bank::create([
            'name' => 'Test Bank',
            'code' => 'TST',
            'is_active' => true,
            'organization_id' => $bankOrg->id,
        ]);

        $this->executor = User::create([
            'name' => 'Executor',
            'email' => 'executor@test.bank',
            'password' => bcrypt('password'),
            'bank_id' => $this->bank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $this->executor->teams()->attach($entryTeam);
        $this->executor->roles()->attach($entryRole);

        $this->activeMerchant = Merchant::create([
            'bank_id' => $this->bank->id,
            'name' => 'Active Merchant',
            'tax_number' => 'ACT-1',
            'status' => 'ACTIVE',
        ]);

        $this->suspendedMerchant = Merchant::create([
            'bank_id' => $this->bank->id,
            'name' => 'Suspended Merchant',
            'tax_number' => 'SUS-1',
            'status' => 'SUSPENDED',
        ]);

        $sectorTable = ReferenceTable::create([
            'key' => 'test_sector',
            'label' => 'Test Sector',
            'is_active' => true,
        ]);

        $this->activeSector = ReferenceValue::create([
            'reference_table_id' => $sectorTable->id,
            'key' => 'active_sector',
            'label' => 'Active Sector',
            'is_active' => true,
        ]);

        $this->inactiveSector = ReferenceValue::create([
            'reference_table_id' => $sectorTable->id,
            'key' => 'legacy_sector',
            'label' => 'Legacy Sector',
            'is_active' => true,
        ]);
        $this->inactiveSector->update(['is_active' => false]);

        $definition = WorkflowDefinition::create([
            'code' => 'GRANDFATHER_WF',
            'name' => 'Grandfather Workflow',
            'is_active' => true,
        ]);

        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED,
            'published_at' => now(),
            'version' => 1,
        ]);

        $this->initialStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'DATA_ENTRY',
            'name' => 'Data Entry',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'version' => 1,
        ]);

        StagePermission::create([
            'stage_id' => $this->initialStage->id,
            'organization_id' => $bankOrg->id,
            'role_id' => $entryRole->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Data Entry',
            'version' => 1,
        ]);

        $group = FieldGroup::create([
            'workflow_version_id' => $this->version->id,
            'name' => 'main',
            'label' => 'Main Fields',
            'sort_order' => 1,
            'version' => 1,
        ]);

        $this->merchantField = FieldDefinition::create([
            'workflow_version_id' => $this->version->id,
            'field_group_id' => $group->id,
            'key' => 'merchant_pick',
            'label' => 'Merchant',
            'type' => FieldType::DYNAMIC_SELECT,
            'dynamic_source' => 'MERCHANTS',
            'is_required' => false,
            'sort_order' => 1,
            'version' => 1,
        ]);

        $this->sectorField = FieldDefinition::create([
            'workflow_version_id' => $this->version->id,
            'field_group_id' => $group->id,
            'key' => 'sector',
            'label' => 'Sector',
            'type' => FieldType::DYNAMIC_SELECT,
            'dynamic_source' => 'REFERENCE_DATA',
            'reference_table_id' => $sectorTable->id,
            'is_required' => false,
            'sort_order' => 2,
            'version' => 1,
        ]);
    }

    private function createRequestWithSuspendedMerchant(): EngineRequest
    {
        $this->suspendedMerchant->update(['status' => 'ACTIVE']);

        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $this->activeMerchant->id,
            'data' => ['merchant_pick' => $this->suspendedMerchant->id],
        ]);

        $response->assertCreated();
        $this->suspendedMerchant->update(['status' => 'SUSPENDED']);

        return EngineRequest::findOrFail($response->json('data.id'));
    }

    public function test_resolver_excludes_inactive_options_without_stored_value(): void
    {
        $resolver = app(DynamicFieldOptionsResolver::class);
        $options = $resolver->resolve($this->merchantField, $this->executor);

        $this->assertCount(1, $options);
        $this->assertSame($this->activeMerchant->id, $options[0]['value']);
        $this->assertArrayNotHasKey('inactive', $options[0]);
    }

    public function test_resolver_includes_grandfathered_merchant_for_stored_value(): void
    {
        $resolver = app(DynamicFieldOptionsResolver::class);
        $options = $resolver->resolve(
            $this->merchantField,
            $this->executor,
            null,
            $this->suspendedMerchant->id,
        );

        $this->assertCount(2, $options);
        $this->assertSame($this->activeMerchant->id, $options[0]['value']);
        $this->assertSame($this->suspendedMerchant->id, $options[1]['value']);
        $this->assertTrue($options[1]['inactive']);
        $this->assertSame('Suspended Merchant', $options[1]['label']);
    }

    public function test_resolver_includes_grandfathered_reference_value_for_stored_value(): void
    {
        $resolver = app(DynamicFieldOptionsResolver::class);
        $options = $resolver->resolve(
            $this->sectorField,
            $this->executor,
            null,
            'legacy_sector',
        );

        $this->assertCount(2, $options);
        $this->assertSame('active_sector', $options[0]['value']);
        $this->assertSame('legacy_sector', $options[1]['value']);
        $this->assertTrue($options[1]['inactive']);
        $this->assertSame('Legacy Sector', $options[1]['label']);
    }

    public function test_form_schema_includes_grandfathered_option_for_stored_value(): void
    {
        $request = $this->createRequestWithSuspendedMerchant();

        $this->actingAs($this->executor)
            ->getJson("/api/v1/engine-requests/{$request->id}/form-schema")
            ->assertOk()
            ->assertJsonFragment([
                'value' => $this->suspendedMerchant->id,
                'label' => 'Suspended Merchant',
                'inactive' => true,
            ])
            ->assertJsonFragment([
                'value' => $this->activeMerchant->id,
                'label' => 'Active Merchant',
            ]);
    }

    public function test_draft_save_allows_unchanged_grandfathered_value(): void
    {
        $request = $this->createRequestWithSuspendedMerchant();

        $this->actingAs($this->executor)
            ->patchJson("/api/v1/engine-requests/{$request->id}/draft", [
                'data' => ['merchant_pick' => $this->suspendedMerchant->id],
                'version' => $request->version,
            ])
            ->assertOk()
            ->assertJsonPath('data.data.merchant_pick', $this->suspendedMerchant->id);
    }

    public function test_draft_save_rejects_new_selection_of_deactivated_merchant(): void
    {
        $request = $this->createRequestWithSuspendedMerchant();

        $this->actingAs($this->executor)
            ->patchJson("/api/v1/engine-requests/{$request->id}/draft", [
                'data' => ['merchant_pick' => $this->activeMerchant->id],
                'version' => $request->version,
            ])
            ->assertOk()
            ->assertJsonPath('data.data.merchant_pick', $this->activeMerchant->id);

        $this->suspendedMerchant->refresh();
        $this->assertSame('SUSPENDED', $this->suspendedMerchant->status);

        $updated = EngineRequest::findOrFail($request->id);

        $this->actingAs($this->executor)
            ->patchJson("/api/v1/engine-requests/{$updated->id}/draft", [
                'data' => ['merchant_pick' => $this->suspendedMerchant->id],
                'version' => $updated->version,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'STAGE_FIELDS_INVALID')
            ->assertJsonPath('errors.merchant_pick', 'The selected value is not a valid option.');
    }

    public function test_create_rejects_deactivated_merchant_for_new_request(): void
    {
        $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $this->activeMerchant->id,
            'data' => ['merchant_pick' => $this->suspendedMerchant->id],
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'STAGE_FIELDS_INVALID')
            ->assertJsonPath('errors.merchant_pick', 'The selected value is not a valid option.');
    }
}

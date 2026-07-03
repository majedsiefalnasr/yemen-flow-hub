<?php

namespace Tests\Feature\Engine;

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
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineDuplicateInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $executor;

    private WorkflowVersion $version;

    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $entryRole = Role::where('code', 'intake')->firstOrFail();
        $entryTeam = Team::where('code', 'entry')->firstOrFail();

        $bank = Bank::create(['name' => 'Dup Test Bank', 'code' => 'DUP', 'is_active' => true, 'organization_id' => $bankOrg->id]);

        $this->executor = User::create([
            'name' => 'DE User',
            'email' => 'de@dup.test',
            'password' => bcrypt('password'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $bank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $this->executor->teams()->attach($entryTeam);
        $this->executor->roles()->attach($entryRole);

        $this->merchant = Merchant::create([
            'bank_id' => $bank->id,
            'name' => 'Dup Merchant',
            'tax_number' => 'TAX-DUP-001',
            'status' => 'ACTIVE',
        ]);

        $def = WorkflowDefinition::create(['code' => 'DUP_WF', 'name' => 'Dup Workflow', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED,
            'published_at' => now(),
            'version' => 1,
        ]);

        $stage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'ENTRY',
            'name' => 'Entry',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'version' => 1,
        ]);

        StagePermission::create([
            'stage_id' => $stage->id,
            'organization_id' => $bankOrg->id,
            'role_id' => $entryRole->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Entry',
            'version' => 1,
        ]);

        $action = WorkflowAction::create(['code' => 'SUBMIT_DUP', 'name' => 'Submit', 'kind' => 'DRAFT', 'is_active' => true, 'version' => 1]);
        $finalStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'DONE',
            'name' => 'Done',
            'sort_order' => 2,
            'is_initial' => false,
            'is_final' => true,
            'version' => 1,
        ]);
        WorkflowTransition::create([
            'workflow_version_id' => $this->version->id,
            'from_stage_id' => $stage->id,
            'to_stage_id' => $finalStage->id,
            'action_id' => $action->id,
            'requires_comment' => false,
            'version' => 1,
        ]);

        // Register invoice_number as a FieldDefinition so RequestProjectionSync syncs
        // data['invoice_number'] → engine_requests.invoice_number (projection column).
        $group = FieldGroup::create([
            'workflow_version_id' => $this->version->id,
            'name' => 'general',
            'label' => 'General',
            'sort_order' => 1,
            'version' => 1,
        ]);

        FieldDefinition::create([
            'workflow_version_id' => $this->version->id,
            'field_group_id' => $group->id,
            'key' => 'invoice_number',
            'label' => 'Invoice Number',
            'type' => FieldType::TEXT,
            'sort_order' => 1,
            'is_required' => false,
            'version' => 1,
        ]);
    }

    public function test_duplicate_invoice_warning_on_second_submission(): void
    {
        // Create the first request with invoice INV-DUP-001
        $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $this->merchant->id,
            'data' => ['amount' => 10000, 'currency' => 'USD', 'invoice_number' => 'INV-DUP-001'],
        ])->assertCreated();

        // Second request with the same invoice triggers a warning, not a hard block
        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $this->merchant->id,
            'data' => ['amount' => 10000, 'currency' => 'USD', 'invoice_number' => 'INV-DUP-001'],
        ]);

        $response->assertCreated();
        $this->assertArrayHasKey('warnings', $response->json());
        $this->assertEquals('DUPLICATE_INVOICE', $response->json('warnings.0.code'));
    }

    public function test_unique_invoice_has_no_warning(): void
    {
        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $this->merchant->id,
            'data' => ['amount' => 10000, 'currency' => 'USD', 'invoice_number' => 'INV-UNIQUE-999'],
        ]);

        $response->assertCreated();
        $this->assertArrayNotHasKey('warnings', $response->json());
    }

    public function test_editing_own_request_does_not_self_trigger_duplicate(): void
    {
        // Create first request
        $first = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $this->merchant->id,
            'data' => ['amount' => 10000, 'currency' => 'USD', 'invoice_number' => 'INV-SELF-001'],
        ]);
        $first->assertCreated();
        $firstId = $first->json('data.id');
        $firstRequest = EngineRequest::find($firstId);

        // Draft-save with same invoice number — exclude_request_id exclusion means
        // the duplicate checker should not flag it against itself.
        $response = $this->actingAs($this->executor)->patchJson("/api/v1/engine-requests/{$firstId}/draft", [
            'data' => ['amount' => 20000, 'currency' => 'USD', 'invoice_number' => 'INV-SELF-001'],
            'version' => $firstRequest->version,
        ]);

        // Draft save should succeed without triggering DUPLICATE_INVOICE warning
        $response->assertOk();
        $this->assertArrayNotHasKey('warnings', $response->json());
    }

    public function test_show_surfaces_duplicate_warning_for_conflicting_request(): void
    {
        // First request holds the invoice number.
        $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $this->merchant->id,
            'data' => ['invoice_number' => 'INV-SHOW-001'],
        ])->assertCreated();

        // Second request re-uses it; viewing its detail must expose the conflict
        // so a reviewer sees it without re-running a transition.
        $second = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $this->merchant->id,
            'data' => ['invoice_number' => 'INV-SHOW-001'],
        ])->assertCreated();

        $this->actingAs($this->executor)
            ->getJson("/api/v1/engine-requests/{$second->json('data.id')}")
            ->assertOk()
            ->assertJsonPath('warnings.0.code', 'DUPLICATE_INVOICE');
    }

    public function test_show_has_no_warning_for_unique_invoice(): void
    {
        $created = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $this->merchant->id,
            'data' => ['invoice_number' => 'INV-SHOW-UNIQUE'],
        ])->assertCreated();

        $response = $this->actingAs($this->executor)
            ->getJson("/api/v1/engine-requests/{$created->json('data.id')}")
            ->assertOk();

        $this->assertArrayNotHasKey('warnings', $response->json());
    }

    public function test_duplicate_warning_includes_matching_reference_list(): void
    {
        $first = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $this->merchant->id,
            'data' => ['invoice_number' => 'INV-MULTI-001'],
        ])->assertCreated();

        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $this->merchant->id,
            'data' => ['invoice_number' => 'INV-MULTI-001'],
        ])->assertCreated();

        $duplicates = $response->json('warnings.0.duplicates');
        $this->assertIsArray($duplicates);
        $this->assertNotEmpty($duplicates);
    }
}

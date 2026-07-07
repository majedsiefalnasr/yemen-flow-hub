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
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\SystemSetting;
use App\Models\Team;
use App\Services\Settings\SettingResolver;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use App\Services\Workflow\DuplicateInvoiceChecker;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicateInvoicePolicyTest extends TestCase
{
    use RefreshDatabase;

    private function makeActiveRequest(string $invoiceNumberNormalized): EngineRequest
    {
        $definition = WorkflowDefinition::create(['code' => 'DUP_POLICY_WF', 'name' => 'Dup Policy Workflow', 'is_active' => true]);
        $version = WorkflowVersion::create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED,
            'published_at' => now(),
            'version' => 1,
        ]);
        $stage = WorkflowStage::create([
            'workflow_version_id' => $version->id,
            'code' => 'ENTRY',
            'name' => 'Entry',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'version' => 1,
        ]);
        $user = User::factory()->create();

        return EngineRequest::create([
            'workflow_version_id' => $version->id,
            'current_stage_id' => $stage->id,
            'reference' => 'REF-'.$invoiceNumberNormalized,
            'status' => 'ACTIVE',
            'created_by' => $user->id,
            'invoice_number' => $invoiceNumberNormalized,
            'invoice_number_normalized' => $invoiceNumberNormalized,
        ]);
    }

    public function test_warn_policy_returns_detection_with_warn_severity(): void
    {
        $this->makeActiveRequest('INV-001');

        $checker = app(DuplicateInvoiceChecker::class);
        $result = $checker->check('INV-001');

        $this->assertNotNull($result);
        $this->assertSame('warn', $result['severity']);
    }

    public function test_block_policy_returns_block_severity(): void
    {
        $this->makeActiveRequest('INV-002');

        SystemSetting::findByKey('duplicate_invoice_policy')?->update(['value' => 'block']);
        app(SettingResolver::class)->forget('duplicate_invoice_policy');

        $checker = app(DuplicateInvoiceChecker::class);
        $result = $checker->check('INV-002');

        $this->assertSame('block', $result['severity']);
    }

    public function test_store_returns_422_duplicate_invoice_blocked_when_policy_is_block(): void
    {
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $entryRole = Role::where('code', 'intake')->firstOrFail();
        $entryTeam = Team::where('code', 'entry')->firstOrFail();

        $bank = Bank::create(['name' => 'Dup Block Bank', 'code' => 'BLK', 'is_active' => true, 'organization_id' => $bankOrg->id]);

        $executor = User::create([
            'name' => 'DE User',
            'email' => 'de@block.test',
            'password' => bcrypt('password'),
            'bank_id' => $bank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $executor->teams()->attach($entryTeam);
        $executor->roles()->attach($entryRole);

        $merchant = Merchant::create([
            'bank_id' => $bank->id,
            'name' => 'Dup Block Merchant',
            'tax_number' => 'TAX-BLK-001',
            'status' => 'ACTIVE',
        ]);

        $def = WorkflowDefinition::create(['code' => 'BLK_WF', 'name' => 'Block Workflow', 'is_active' => true]);
        $version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED,
            'published_at' => now(),
            'version' => 1,
        ]);

        $stage = WorkflowStage::create([
            'workflow_version_id' => $version->id,
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

        $group = FieldGroup::create([
            'workflow_version_id' => $version->id,
            'name' => 'general',
            'label' => 'General',
            'sort_order' => 1,
            'version' => 1,
        ]);

        FieldDefinition::create([
            'workflow_version_id' => $version->id,
            'field_group_id' => $group->id,
            'key' => 'invoice_number',
            'label' => 'Invoice Number',
            'type' => FieldType::TEXT,
            'sort_order' => 1,
            'is_required' => false,
            'version' => 1,
        ]);

        // First request holds the invoice number.
        $this->actingAs($executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $version->id,
            'merchant_id' => $merchant->id,
            'data' => ['invoice_number' => 'INV-BLOCK-001'],
        ])->assertCreated();

        SystemSetting::findByKey('duplicate_invoice_policy')?->update(['value' => 'block']);
        app(SettingResolver::class)->forget('duplicate_invoice_policy');

        $countBefore = EngineRequest::count();

        // Second request re-uses the invoice number under a block policy.
        $response = $this->actingAs($executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $version->id,
            'merchant_id' => $merchant->id,
            'data' => ['invoice_number' => 'INV-BLOCK-001'],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'DUPLICATE_INVOICE_BLOCKED');

        // The blocked request must not have been persisted.
        $this->assertSame($countBefore, EngineRequest::count());
    }
}

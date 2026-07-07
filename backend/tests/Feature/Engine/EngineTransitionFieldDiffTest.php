<?php

namespace Tests\Feature\Engine;

use App\Enums\AuditAction;
use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\EngineRequest;
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
use App\Support\TransitionFieldDiffBuilder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineTransitionFieldDiffTest extends TestCase
{
    use RefreshDatabase;

    private User $executor;

    private WorkflowVersion $version;

    private WorkflowTransition $submitTransition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $entryRole = Role::where('code', 'intake')->firstOrFail();
        $entryTeam = Team::where('code', 'entry')->firstOrFail();

        $bank = Bank::create([
            'name' => 'Diff Bank',
            'code' => 'DFB',
            'is_active' => true,
            'organization_id' => $bankOrg->id,
        ]);

        $this->executor = User::create([
            'name' => 'Executor',
            'email' => 'executor@diff.test',
            'password' => bcrypt('pass'),
            'bank_id' => $bank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $this->executor->teams()->attach($entryTeam);
        $this->executor->roles()->attach($entryRole);

        $def = WorkflowDefinition::create(['code' => 'DIFF_WF', 'name' => 'Diff WF', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'published_at' => now(),
            'version' => 1,
        ]);

        $initialStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'ENTRY',
            'name' => 'Entry',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'version' => 1,
        ]);

        $nextStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'REVIEW',
            'name' => 'Review',
            'sort_order' => 2,
            'is_initial' => false,
            'is_final' => false,
            'version' => 1,
        ]);

        StagePermission::create([
            'stage_id' => $initialStage->id,
            'organization_id' => $bankOrg->id,
            'role_id' => $entryRole->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Entry',
            'version' => 1,
        ]);

        $action = WorkflowAction::create([
            'code' => 'DIFF_SUBMIT',
            'name' => 'Submit',
            'kind' => 'DRAFT',
            'is_active' => true,
            'version' => 1,
        ]);

        $this->submitTransition = WorkflowTransition::create([
            'workflow_version_id' => $this->version->id,
            'from_stage_id' => $initialStage->id,
            'to_stage_id' => $nextStage->id,
            'action_id' => $action->id,
            'requires_comment' => false,
            'version' => 1,
        ]);
    }

    private function makeRequest(): EngineRequest
    {
        $response = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'data' => ['amount' => 5000, 'currency' => 'USD'],
        ]);
        $response->assertCreated();

        return EngineRequest::findOrFail($response->json('data.id'));
    }

    public function test_diff_masks_amount_and_invoice_number(): void
    {
        $builder = new TransitionFieldDiffBuilder;
        ['old_values' => $old, 'new_values' => $new] = $builder->diff(
            ['amount' => 100, 'invoice_number' => 'INV-1', 'notes' => 'a'],
            ['amount' => 200, 'invoice_number' => 'INV-2', 'notes' => 'b'],
        );

        $this->assertSame('[REDACTED]', $old['amount']);
        $this->assertSame('[REDACTED]', $new['amount']);
        $this->assertSame('[REDACTED]', $old['invoice_number']);
        $this->assertSame('[REDACTED]', $new['invoice_number']);
        $this->assertSame('a', $old['notes']);
        $this->assertSame('b', $new['notes']);
    }

    public function test_diff_masks_semantic_merchant_tax_number_field_key(): void
    {
        $builder = new TransitionFieldDiffBuilder;
        ['old_values' => $old, 'new_values' => $new] = $builder->diff(
            ['tax_number_field' => '111', 'notes' => 'a'],
            ['tax_number_field' => '222', 'notes' => 'b'],
            ['tax_number_field'],
        );

        $this->assertSame('[REDACTED]', $old['tax_number_field']);
        $this->assertSame('[REDACTED]', $new['tax_number_field']);
        $this->assertSame('a', $old['notes']);
        $this->assertSame('b', $new['notes']);
    }

    public function test_diff_returns_empty_arrays_when_no_changes(): void
    {
        $builder = new TransitionFieldDiffBuilder;
        $diff = $builder->diff(['notes' => 'same'], ['notes' => 'same']);

        $this->assertSame([], $diff['old_values']);
        $this->assertSame([], $diff['new_values']);
    }

    public function test_transition_with_data_patch_writes_field_level_audit_diff(): void
    {
        $request = $this->makeRequest();
        $request->update(['data' => array_merge($request->data ?? [], ['notes' => 'before'])]);

        $this->actingAs($this->executor)->postJson(
            "/api/v1/engine-requests/{$request->id}/actions",
            [
                'transition_id' => $this->submitTransition->id,
                'data' => ['notes' => 'after'],
                'version' => $request->version,
            ],
        )->assertOk();

        $log = AuditLog::query()
            ->where('workflow_instance_id', $request->id)
            ->where('action', AuditAction::STATUS_TRANSITION->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('before', $log->old_values['notes'] ?? null);
        $this->assertSame('after', $log->new_values['notes'] ?? null);
    }

    public function test_save_draft_writes_field_level_audit_diff(): void
    {
        $request = $this->makeRequest();
        $request->update(['data' => array_merge($request->data ?? [], ['notes' => 'before'])]);

        $this->actingAs($this->executor)->patchJson(
            "/api/v1/engine-requests/{$request->id}/draft",
            [
                'data' => ['notes' => 'draft-after'],
                'version' => $request->version,
            ],
        )->assertOk();

        $log = AuditLog::query()
            ->where('workflow_instance_id', $request->id)
            ->where('action', AuditAction::REQUEST_UPDATED->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('before', $log->old_values['notes'] ?? null);
        $this->assertSame('draft-after', $log->new_values['notes'] ?? null);
    }
}

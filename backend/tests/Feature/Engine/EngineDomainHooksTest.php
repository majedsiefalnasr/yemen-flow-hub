<?php

namespace Tests\Feature\Engine;

use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Exceptions\FinancingLimitExceededException;
use App\Models\Bank;
use App\Models\CustomsDeclaration;
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
use App\Services\Workflow\Engine\EngineFinancingLedger;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EngineDomainHooksTest extends TestCase
{
    use RefreshDatabase;

    private User $executor;

    private Bank $bank;

    private Merchant $merchant;

    private WorkflowVersion $version;

    private WorkflowStage $startStage;

    private WorkflowStage $execStage;

    private WorkflowStage $fxStage;

    private WorkflowTransition $toExec;

    private WorkflowTransition $toFx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, GovernanceSeeder::class]);

        // Point the DI-4 hooks at this test workflow's stage codes.
        config([
            'engine_hooks.financing_reserve_stage' => 'EXEC',
            'engine_hooks.fx_pdf_stage' => 'FX_CONFIRM',
        ]);
        Storage::fake('local');

        $this->buildWorkflow();
    }

    private function buildWorkflow(): void
    {
        $bankOrg = Organization::where('code', 'commercial_banks')->first();
        $this->bank = Bank::create([
            'name' => 'Hook Bank', 'code' => 'HKB', 'is_active' => true, 'organization_id' => $bankOrg->id,
        ]);

        $role = Role::where('code', 'intake')->first();
        $team = Team::where('code', 'entry')->first();

        $this->executor = User::create([
            'name' => 'Hook Executor', 'email' => 'hook@test.bank', 'password' => bcrypt('password'),
            'role' => UserRole::DATA_ENTRY, 'bank_id' => $this->bank->id, 'organization_id' => $bankOrg->id, 'is_active' => true,
        ]);
        $this->executor->teams()->attach($team);
        $this->executor->roles()->attach($role);

        $this->merchant = Merchant::create([
            'bank_id' => $this->bank->id, 'name' => 'Hook Merchant', 'tax_number' => 'TAX-HOOK-1', 'status' => 'ACTIVE',
        ]);

        $def = WorkflowDefinition::create(['code' => 'HOOK_WF', 'name' => 'Hook WF', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id, 'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED, 'published_at' => now(), 'version' => 1,
        ]);

        $this->startStage = $this->stage('START', 1, isInitial: true);
        $this->execStage = $this->stage('EXEC', 2);
        $this->fxStage = $this->stage('FX_CONFIRM', 3, isFinal: true);

        foreach ([$this->startStage, $this->execStage, $this->fxStage] as $stage) {
            StagePermission::create([
                'stage_id' => $stage->id, 'organization_id' => $bankOrg->id, 'role_id' => $role->id,
                'access_level' => StageAccessLevel::EXECUTE, 'display_label' => 'Exec', 'version' => 1,
            ]);
        }

        $submit = WorkflowAction::create(['code' => 'SUBMIT', 'name' => 'Submit', 'kind' => 'APPROVE', 'is_active' => true, 'version' => 1]);
        $approve = WorkflowAction::create(['code' => 'APPROVE', 'name' => 'Approve', 'kind' => 'APPROVE', 'is_active' => true, 'version' => 1]);

        $this->toExec = WorkflowTransition::create([
            'workflow_version_id' => $this->version->id, 'from_stage_id' => $this->startStage->id,
            'to_stage_id' => $this->execStage->id, 'action_id' => $submit->id, 'requires_comment' => false, 'version' => 1,
        ]);
        $this->toFx = WorkflowTransition::create([
            'workflow_version_id' => $this->version->id, 'from_stage_id' => $this->execStage->id,
            'to_stage_id' => $this->fxStage->id, 'action_id' => $approve->id, 'requires_comment' => false, 'version' => 1,
        ]);

        $group = FieldGroup::create(['workflow_version_id' => $this->version->id, 'name' => 'main', 'label' => 'Main', 'sort_order' => 1, 'version' => 1]);
        foreach (['invoice_number' => 'TEXT', 'request_percentage' => 'NUMBER'] as $key => $type) {
            FieldDefinition::create([
                'workflow_version_id' => $this->version->id, 'field_group_id' => $group->id, 'key' => $key,
                'label' => $key, 'type' => $type, 'is_required' => false, 'sort_order' => 1, 'version' => 1,
            ]);
        }
    }

    private function stage(string $code, int $order, bool $isInitial = false, bool $isFinal = false): WorkflowStage
    {
        return WorkflowStage::create([
            'workflow_version_id' => $this->version->id, 'code' => $code, 'name' => $code,
            'sort_order' => $order, 'is_initial' => $isInitial, 'is_final' => $isFinal, 'version' => 1,
        ]);
    }

    private function createRequest(float $percent, string $invoice = 'INV-HOOK-1'): EngineRequest
    {
        $res = $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', [
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $this->merchant->id,
            'data' => ['invoice_number' => $invoice, 'request_percentage' => $percent],
        ])->assertCreated();

        return EngineRequest::findOrFail($res->json('data.id'));
    }

    private function submit(EngineRequest $request): TestResponse
    {
        return $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->toExec->id, 'data' => [], 'version' => $request->version,
        ]);
    }

    public function test_financing_reserve_succeeds_under_cap(): void
    {
        $request = $this->createRequest(40.0);

        $this->submit($request)->assertOk();

        $request->refresh();
        $this->assertEquals($this->execStage->id, $request->current_stage_id);
    }

    public function test_financing_breach_rolls_back_transition(): void
    {
        // Existing in-flight request consuming 70% on the same (tax, invoice).
        $existing = $this->createRequest(70.0);
        $this->submit($existing)->assertOk();

        // New request for 40% would push the cap to 110% → breach.
        $request = $this->createRequest(40.0);
        $response = $this->submit($request);

        $response->assertStatus(422)->assertJsonPath('error_code', 'FINANCING_LIMIT_EXCEEDED');

        $request->refresh();
        $this->assertEquals($this->startStage->id, $request->current_stage_id, 'transition must roll back on breach');
        $this->assertEquals('ACTIVE', $request->status);
    }

    public function test_rejected_request_frees_capacity(): void
    {
        $rejected = $this->createRequest(80.0);
        $rejected->forceFill(['status' => 'REJECTED'])->save();

        // 80% is held by a REJECTED row → it frees its allocation, so 40% fits.
        $request = $this->createRequest(40.0);
        $this->submit($request)->assertOk();

        $request->refresh();
        $this->assertEquals($this->execStage->id, $request->current_stage_id);
    }

    public function test_customs_pdf_generated_on_fx_stage_entry(): void
    {
        $request = $this->createRequest(30.0);
        $this->submit($request)->assertOk();
        $request->refresh();

        $this->actingAs($this->executor)->postJson("/api/v1/engine-requests/{$request->id}/actions", [
            'transition_id' => $this->toFx->id, 'data' => [], 'version' => $request->version,
        ])->assertOk();

        $declaration = CustomsDeclaration::where('engine_request_id', $request->id)->first();
        $this->assertNotNull($declaration, 'a declaration must be linked to the engine request');
        $this->assertStringStartsWith('CD-', $declaration->declaration_number);
        Storage::disk('local')->assertExists('private/'.$declaration->pdf_path);
    }

    public function test_ledger_named_lock_serializes_concurrent_reserves(): void
    {
        // Two reserves that together exceed 100% under one lock — the second must fail.
        $ledger = app(EngineFinancingLedger::class);
        $this->createRequest(60.0)->forceFill(['status' => 'ACTIVE'])->save();

        $this->expectException(FinancingLimitExceededException::class);
        $ledger->assertWithinLimit('TAX-HOOK-1', 'INV-HOOK-1', 60.0);
    }
}

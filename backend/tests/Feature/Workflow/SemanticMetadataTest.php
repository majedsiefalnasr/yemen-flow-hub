<?php

namespace Tests\Feature\Workflow;

use App\Enums\FieldSemanticTag;
use App\Enums\FinalOutcome;
use App\Enums\StageAccessLevel;
use App\Enums\StageSemanticRole;
use App\Enums\UserRole;
use App\Enums\WorkflowEffectCode;
use App\Enums\WorkflowVersionState;
use App\Models\EngineRequest;
use App\Models\FieldDefinition;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use App\Services\Workflow\SemanticResolver;
use App\Services\Workflow\WorkflowVersionValidator;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SemanticMetadataTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $org;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = $this->firstUserWithRole(UserRole::CBY_ADMIN);
        $this->org = Organization::query()->firstOrFail();
        $this->role = Role::query()->where('organization_id', $this->org->id)->firstOrFail();
    }

    private function validDraftWithSemantics(): WorkflowVersion
    {
        $definition = WorkflowDefinition::query()->create(['code' => 'sem_'.uniqid(), 'name' => 'Semantic Flow']);
        $version = $definition->versions()->create([
            'version_number' => 1,
            'state' => WorkflowVersionState::DRAFT,
        ])->refresh();

        $intake = $version->stages()->create([
            'code' => 'intake',
            'name' => 'Intake',
            'semantic_role' => StageSemanticRole::INITIAL_ENTRY,
            'is_initial' => true,
            'sort_order' => 0,
        ]);
        $reserve = $version->stages()->create([
            'code' => 'reserve',
            'name' => 'Reserve',
            'semantic_role' => StageSemanticRole::EXECUTIVE_VOTE,
            'attached_effects' => [WorkflowEffectCode::FINANCING_RESERVE->value],
            'sort_order' => 1,
        ]);
        $done = $version->stages()->create([
            'code' => 'done',
            'name' => 'Done',
            'semantic_role' => StageSemanticRole::FINAL,
            'is_final' => true,
            'final_outcome' => FinalOutcome::COMPLETED,
            'sort_order' => 2,
        ]);

        $group = $version->fieldGroups()->create(['name' => 'main', 'label' => 'Main', 'sort_order' => 1]);
        FieldDefinition::query()->create([
            'workflow_version_id' => $version->id,
            'field_group_id' => $group->id,
            'key' => 'invoice_number',
            'semantic_tag' => FieldSemanticTag::INVOICE_NUMBER,
            'label' => 'Invoice',
            'type' => 'TEXT',
            'sort_order' => 1,
        ]);
        FieldDefinition::query()->create([
            'workflow_version_id' => $version->id,
            'field_group_id' => $group->id,
            'key' => 'request_percentage',
            'semantic_tag' => FieldSemanticTag::REQUESTED_PERCENTAGE,
            'label' => 'Percent',
            'type' => 'NUMBER',
            'sort_order' => 2,
        ]);
        FieldDefinition::query()->create([
            'workflow_version_id' => $version->id,
            'field_group_id' => $group->id,
            'key' => 'taxNumber',
            'semantic_tag' => FieldSemanticTag::MERCHANT_TAX_NUMBER,
            'label' => 'Tax',
            'type' => 'TEXT',
            'sort_order' => 3,
        ]);

        foreach ([$intake, $reserve] as $stage) {
            $stage->stagePermissions()->create([
                'organization_id' => $this->org->id,
                'role_id' => $this->role->id,
                'access_level' => StageAccessLevel::EXECUTE,
                'display_label' => 'Executors',
            ]);
        }

        $approve = WorkflowAction::query()->create(['code' => 'APPROVE_'.uniqid(), 'name' => 'Approve', 'kind' => 'APPROVE']);
        $version->transitions()->create([
            'from_stage_id' => $intake->id,
            'action_id' => $approve->id,
            'to_stage_id' => $reserve->id,
            'is_default_submit' => true,
        ]);
        $version->transitions()->create([
            'from_stage_id' => $reserve->id,
            'action_id' => $approve->id,
            'to_stage_id' => $done->id,
        ]);

        return $version->refresh();
    }

    public function test_publish_blocks_when_financing_effect_missing_required_tag(): void
    {
        $version = $this->validDraftWithSemantics();
        FieldDefinition::query()
            ->where('workflow_version_id', $version->id)
            ->where('key', 'invoice_number')
            ->delete();

        $codes = collect(app(WorkflowVersionValidator::class)->validate($version->fresh()))->pluck('code');

        $this->assertContains('SEMANTIC_MAPPING_MISSING', $codes);
    }

    public function test_publish_blocks_duplicate_semantic_tag(): void
    {
        $version = $this->validDraftWithSemantics();
        $groupId = $version->fieldGroups()->value('id');
        FieldDefinition::query()->create([
            'workflow_version_id' => $version->id,
            'field_group_id' => $groupId,
            'key' => 'invoice_copy',
            'semantic_tag' => FieldSemanticTag::INVOICE_NUMBER,
            'label' => 'Invoice copy',
            'type' => 'TEXT',
            'sort_order' => 99,
        ]);

        $codes = collect(app(WorkflowVersionValidator::class)->validate($version->fresh()))->pluck('code');

        $this->assertContains('SEMANTIC_MAPPING_AMBIGUOUS', $codes);
    }

    public function test_resolver_reads_tag_after_field_key_rename(): void
    {
        $version = $this->validDraftWithSemantics();
        $field = FieldDefinition::query()
            ->where('workflow_version_id', $version->id)
            ->where('semantic_tag', FieldSemanticTag::INVOICE_NUMBER->value)
            ->firstOrFail();
        $field->update(['key' => 'renamed_invoice_ref']);

        $request = EngineRequest::query()->create([
            'workflow_version_id' => $version->id,
            'current_stage_id' => $version->stages()->first()->id,
            'reference' => 'ENG-SEM-1',
            'status' => 'ACTIVE',
            'created_by' => $this->admin->id,
            'bank_id' => $this->admin->bank_id,
            'data' => ['renamed_invoice_ref' => 'INV-RENAMED'],
            'invoice_number' => 'INV-RENAMED',
        ]);

        $resolved = app(SemanticResolver::class)->resolveFieldValue($request->fresh(), FieldSemanticTag::INVOICE_NUMBER);

        $this->assertSame('INV-RENAMED', $resolved);
    }

    public function test_validate_endpoint_returns_semantic_warnings(): void
    {
        $version = $this->validDraftWithSemantics();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$version->id}/validate")
            ->assertOk();

        $this->assertIsArray($response->json('data.warnings'));
    }
}

<?php

namespace Tests\Feature\Workflow;

use App\Enums\FieldType;
use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\StageFieldRule;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageFieldRuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $nonAdmin;

    private WorkflowVersion $draft;

    private WorkflowStage $stage;

    private int $fieldId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, GovernanceSeeder::class, ScreenPermissionSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();
        $this->nonAdmin = User::query()->where('role', '!=', UserRole::CBY_ADMIN->value)->firstOrFail();

        $definition = WorkflowDefinition::query()->create(['code' => 'flow', 'name' => 'Flow']);
        $this->draft = $definition->versions()->create([
            'version_number' => 1,
            'state' => WorkflowVersionState::DRAFT,
        ])->refresh();
        $this->stage = $this->draft->stages()->create(['code' => 'intake', 'name' => 'Intake']);
        $group = $this->draft->fieldGroups()->create(['name' => 'g', 'label' => 'G']);
        $this->fieldId = $this->draft->fieldDefinitions()->create([
            'field_group_id' => $group->id,
            'key' => 'amount',
            'label' => 'Amount',
            'type' => FieldType::CURRENCY,
        ])->id;
    }

    public function test_set_field_rule(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-stages/{$this->stage->id}/field-rules", [
                'field_id' => $this->fieldId,
                'is_visible' => true,
                'is_editable' => false,
                'is_required' => true,
            ])->assertCreated()
            ->assertJsonPath('data.is_editable', false)
            ->assertJsonPath('data.is_required', true);

        $this->assertDatabaseHas('stage_field_rules', [
            'stage_id' => $this->stage->id,
            'field_id' => $this->fieldId,
            'is_required' => true,
        ]);
    }

    public function test_setting_an_existing_rule_upserts_it(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-stages/{$this->stage->id}/field-rules", [
                'field_id' => $this->fieldId,
                'is_required' => false,
            ])->assertCreated();

        // Second set updates the same row (unique stage+field), bumping version.
        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-stages/{$this->stage->id}/field-rules", [
                'field_id' => $this->fieldId,
                'is_required' => true,
            ])->assertCreated()
            ->assertJsonPath('data.is_required', true)
            ->assertJsonPath('data.version', 2);

        $this->assertSame(1, StageFieldRule::query()->where('stage_id', $this->stage->id)->count());
    }

    public function test_field_must_belong_to_the_stage_version(): void
    {
        $otherVersion = $this->draft->definition->versions()->create([
            'version_number' => 2,
            'state' => WorkflowVersionState::DRAFT,
        ]);
        $otherGroup = $otherVersion->fieldGroups()->create(['name' => 'g2', 'label' => 'G2']);
        $foreignField = $otherVersion->fieldDefinitions()->create([
            'field_group_id' => $otherGroup->id,
            'key' => 'x',
            'label' => 'X',
            'type' => FieldType::TEXT,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-stages/{$this->stage->id}/field-rules", [
                'field_id' => $foreignField->id,
            ])->assertUnprocessable()
            ->assertJsonValidationErrors('field_id');
    }

    public function test_mutating_rule_on_published_version_is_rejected(): void
    {
        $this->draft->update(['state' => WorkflowVersionState::PUBLISHED]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-stages/{$this->stage->id}/field-rules", [
                'field_id' => $this->fieldId,
            ])->assertStatus(409)
            ->assertJsonPath('error.code', 'WORKFLOW_IMMUTABLE_STATE');
    }

    public function test_delete_field_rule(): void
    {
        $rule = $this->stage->stageFieldRules()->create([
            'field_id' => $this->fieldId,
            'is_visible' => true,
            'is_editable' => true,
            'is_required' => false,
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/workflow-stages/{$this->stage->id}/field-rules/{$rule->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('stage_field_rules', ['id' => $rule->id]);
    }

    public function test_non_admin_cannot_manage_field_rules(): void
    {
        $this->actingAs($this->nonAdmin)
            ->getJson("/api/v1/workflow-stages/{$this->stage->id}/field-rules")
            ->assertForbidden();
    }
}

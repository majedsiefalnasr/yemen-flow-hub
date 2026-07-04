<?php

namespace Tests\Feature\Workflow;

use App\Enums\FieldType;
use App\Enums\UserRole;
use App\Enums\WorkflowVersionState;
use App\Models\Bank;
use App\Models\Merchant;
use App\Models\ReferenceTable;
use App\Models\ReferenceValue;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldDefinitionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $nonAdmin;

    private WorkflowVersion $draft;

    private int $groupId;

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
        $this->groupId = $this->draft->fieldGroups()->create([
            'name' => 'request_data',
            'label' => 'بيانات الطلب',
            'sort_order' => 0,
        ])->id;
    }

    public function test_add_field_group_orders_as_tabs(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$this->draft->id}/field-groups", [
                'name' => 'supplier',
                'label' => 'بيانات المورد',
                'sort_order' => 1,
            ])->assertCreated()
            ->assertJsonPath('data.name', 'supplier');

        $this->actingAs($this->admin)
            ->getJson("/api/v1/workflow-versions/{$this->draft->id}/field-groups")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'request_data')
            ->assertJsonPath('data.1.name', 'supplier');
    }

    public function test_add_field_with_full_settings(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$this->draft->id}/fields", [
                'field_group_id' => $this->groupId,
                'key' => 'amount',
                'label' => 'المبلغ',
                'type' => 'CURRENCY',
                'min_value' => 1,
                'max_value' => 1000000,
                'is_required' => true,
            ])->assertCreated()
            ->assertJsonPath('data.key', 'amount')
            ->assertJsonPath('data.type', 'CURRENCY')
            ->assertJsonPath('data.is_required', true);
    }

    public function test_invalid_type_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$this->draft->id}/fields", [
                'field_group_id' => $this->groupId,
                'key' => 'bad',
                'label' => 'Bad',
                'type' => 'NONSENSE',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_duplicate_key_within_version_is_rejected(): void
    {
        $this->draft->fieldDefinitions()->create([
            'field_group_id' => $this->groupId,
            'key' => 'amount',
            'label' => 'Amount',
            'type' => FieldType::CURRENCY,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$this->draft->id}/fields", [
                'field_group_id' => $this->groupId,
                'key' => 'amount',
                'label' => 'Other',
                'type' => 'NUMBER',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors('key');
    }

    public function test_key_is_immutable_on_update(): void
    {
        $field = $this->draft->fieldDefinitions()->create([
            'field_group_id' => $this->groupId,
            'key' => 'amount',
            'label' => 'Amount',
            'type' => FieldType::CURRENCY,
        ])->refresh();

        $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-versions/{$this->draft->id}/fields/{$field->id}", [
                'key' => 'changed',
                'label' => 'Amount',
                'version' => 1,
            ])->assertUnprocessable()
            ->assertJsonValidationErrors('key');

        // Label-only update succeeds.
        $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-versions/{$this->draft->id}/fields/{$field->id}", [
                'label' => 'Total Amount',
                'version' => 1,
            ])->assertOk()
            ->assertJsonPath('data.label', 'Total Amount')
            ->assertJsonPath('data.version', 2);
    }

    public function test_dynamic_select_requires_a_source(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$this->draft->id}/fields", [
                'field_group_id' => $this->groupId,
                'key' => 'merchant',
                'label' => 'التاجر',
                'type' => 'DYNAMIC_SELECT',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors('dynamic_source');
    }

    public function test_dynamic_select_reference_data_requires_reference_table(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-versions/{$this->draft->id}/fields", [
                'field_group_id' => $this->groupId,
                'key' => 'sector',
                'label' => 'القطاع',
                'type' => 'DYNAMIC_SELECT',
                'dynamic_source' => 'REFERENCE_DATA',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors('reference_table_id');
    }

    public function test_dynamic_select_options_resolve_from_merchants(): void
    {
        $bank = Bank::query()->firstOrFail();
        Merchant::query()->create(['name' => 'تاجر أ', 'bank_id' => $bank->id, 'tax_number' => '100']);
        Merchant::query()->create(['name' => 'تاجر ب', 'bank_id' => $bank->id, 'tax_number' => '200']);

        $field = $this->draft->fieldDefinitions()->create([
            'field_group_id' => $this->groupId,
            'key' => 'merchant',
            'label' => 'التاجر',
            'type' => FieldType::DYNAMIC_SELECT,
            'dynamic_source' => 'MERCHANTS',
        ]);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/workflow-versions/{$this->draft->id}/fields/{$field->id}/options")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.label', 'تاجر أ');
    }

    public function test_dynamic_select_options_resolve_from_reference_data(): void
    {
        $table = ReferenceTable::query()->create(['key' => 'sector', 'label' => 'القطاع']);
        ReferenceValue::query()->create([
            'reference_table_id' => $table->id, 'key' => 'food', 'label' => 'أغذية', 'sort_order' => 0,
        ]);

        $field = $this->draft->fieldDefinitions()->create([
            'field_group_id' => $this->groupId,
            'key' => 'sector',
            'label' => 'القطاع',
            'type' => FieldType::DYNAMIC_SELECT,
            'dynamic_source' => 'REFERENCE_DATA',
            'reference_table_id' => $table->id,
        ]);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/workflow-versions/{$this->draft->id}/fields/{$field->id}/options")
            ->assertOk()
            ->assertJsonPath('data.0.value', 'food')
            ->assertJsonPath('data.0.label', 'أغذية');
    }

    public function test_system_field_delete_is_blocked(): void
    {
        $field = $this->draft->fieldDefinitions()->create([
            'field_group_id' => $this->groupId,
            'key' => 'amount',
            'label' => 'Amount',
            'type' => FieldType::CURRENCY,
            'is_system' => true,
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/workflow-versions/{$this->draft->id}/fields/{$field->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'FIELD_DEFINITION_PROTECTED');
    }

    public function test_delete_unused_field(): void
    {
        $field = $this->draft->fieldDefinitions()->create([
            'field_group_id' => $this->groupId,
            'key' => 'note',
            'label' => 'Note',
            'type' => FieldType::TEXTAREA,
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/workflow-versions/{$this->draft->id}/fields/{$field->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('field_definitions', ['id' => $field->id]);
    }

    public function test_mutating_field_on_published_version_is_rejected(): void
    {
        $field = $this->draft->fieldDefinitions()->create([
            'field_group_id' => $this->groupId,
            'key' => 'amount',
            'label' => 'Amount',
            'type' => FieldType::CURRENCY,
        ])->refresh();
        $this->draft->update(['state' => WorkflowVersionState::PUBLISHED]);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-versions/{$this->draft->id}/fields/{$field->id}", [
                'label' => 'Changed',
                'version' => $field->fresh()->version,
            ])->assertStatus(409)
            ->assertJsonPath('error.code', 'WORKFLOW_IMMUTABLE_STATE');
    }

    public function test_non_admin_cannot_manage_fields(): void
    {
        $this->actingAs($this->nonAdmin)
            ->getJson("/api/v1/workflow-versions/{$this->draft->id}/fields")
            ->assertForbidden();
    }
}

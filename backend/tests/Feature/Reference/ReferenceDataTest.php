<?php

namespace Tests\Feature\Reference;

use App\Enums\UserRole;
use App\Models\ReferenceTable;
use App\Models\User;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceDataTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $nonAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, ReferenceDataSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = $this->firstUserWithRole(UserRole::CBY_ADMIN);
        $this->nonAdmin = User::query()->withoutUserRole(UserRole::CBY_ADMIN)->firstOrFail();
    }

    public function test_seeded_default_tables_exist(): void
    {
        $this->assertDatabaseHas('reference_tables', ['key' => 'sector_activity', 'is_system' => true]);
        $this->assertDatabaseHas('reference_tables', ['key' => 'arrival_port', 'is_system' => true]);
        $this->assertDatabaseHas('reference_tables', ['key' => 'origin_country', 'is_system' => true]);
        $this->assertDatabaseHas('reference_values', ['key' => 'food_beverages', 'is_system' => true]);
    }

    public function test_create_list_update_activate_and_deactivate_table(): void
    {
        $created = $this->actingAs($this->admin)->postJson('/api/v1/reference-tables', [
            'key' => 'shipment_mode',
            'label' => 'Shipment Mode',
        ])->assertCreated()
            ->assertJsonPath('data.key', 'shipment_mode')
            ->assertJsonPath('data.is_active', true);

        $id = $created->json('data.id');

        $this->actingAs($this->admin)->getJson('/api/v1/reference-tables?search=shipment_mode')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($this->admin)->putJson("/api/v1/reference-tables/{$id}", [
            'label' => 'Mode of Shipment',
            'version' => 1,
        ])->assertOk()
            ->assertJsonPath('data.label', 'Mode of Shipment')
            ->assertJsonPath('data.version', 2);

        $this->actingAs($this->admin)->postJson("/api/v1/reference-tables/{$id}/deactivate", [
            'version' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.version', 3);
        $this->actingAs($this->admin)->postJson("/api/v1/reference-tables/{$id}/activate", [
            'version' => 3,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.version', 4);
    }

    public function test_create_list_update_value_under_table(): void
    {
        $table = ReferenceTable::query()->where('key', 'shipment_mode')->first()
            ?? ReferenceTable::query()->create(['key' => 'shipment_mode', 'label' => 'Shipment Mode']);

        $created = $this->actingAs($this->admin)->postJson('/api/v1/reference-values', [
            'reference_table_id' => $table->id,
            'key' => 'sea',
            'label' => 'Sea Freight',
        ])->assertCreated()
            ->assertJsonPath('data.key', 'sea');

        $id = $created->json('data.id');

        $this->actingAs($this->admin)->getJson("/api/v1/reference-values?reference_table_id={$table->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($this->admin)->putJson("/api/v1/reference-values/{$id}", [
            'label' => 'Sea Cargo',
            'version' => 1,
        ])->assertOk()
            ->assertJsonPath('data.label', 'Sea Cargo')
            ->assertJsonPath('data.version', 2);
    }

    public function test_non_admin_cannot_access_reference_data(): void
    {
        $this->actingAs($this->nonAdmin)->getJson('/api/v1/reference-tables')->assertForbidden();
        $this->actingAs($this->nonAdmin)->postJson('/api/v1/reference-tables', [
            'key' => 'blocked',
            'label' => 'Blocked',
        ])->assertForbidden();
    }

    public function test_mutations_are_audited(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/reference-tables', [
            'key' => 'audited_table',
            'label' => 'Audited Table',
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'GOVERNANCE_CREATED',
            'subject_type' => ReferenceTable::class,
        ]);
    }

    public function test_lists_use_required_pagination_and_sort_contract(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/reference-tables?sort=key&direction=desc')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('data.0.key', 'sector_activity');

        $this->actingAs($this->admin)->getJson('/api/v1/reference-tables?sort=invalid')
            ->assertUnprocessable();
    }

    public function test_activation_rejects_a_stale_version(): void
    {
        $table = ReferenceTable::query()->create(['key' => 'stale_table', 'label' => 'Stale Table'])->refresh();

        $this->actingAs($this->admin)->postJson("/api/v1/reference-tables/{$table->id}/deactivate", [
            'version' => $table->version,
        ])->assertOk();

        $this->actingAs($this->admin)->postJson("/api/v1/reference-tables/{$table->id}/activate", [
            'version' => $table->version,
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'STALE_RESOURCE');
    }

    public function test_rerunning_seed_does_not_overwrite_managed_fields(): void
    {
        $table = ReferenceTable::query()->where('key', 'origin_country')->firstOrFail();
        $table->update(['label' => 'Countries', 'is_active' => false]);

        $this->seed(ReferenceDataSeeder::class);

        $this->assertDatabaseHas('reference_tables', [
            'id' => $table->id,
            'label' => 'Countries',
            'is_active' => false,
        ]);
    }
}

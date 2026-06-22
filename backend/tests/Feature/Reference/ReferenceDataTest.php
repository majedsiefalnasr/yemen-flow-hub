<?php

namespace Tests\Feature\Reference;

use App\Enums\UserRole;
use App\Models\ReferenceTable;
use App\Models\User;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReferenceDataSeeder;
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
        $this->seed([PermissionSeeder::class, GovernanceSeeder::class, ReferenceDataSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = User::query()->where('role', UserRole::CBY_ADMIN->value)->firstOrFail();
        $this->nonAdmin = User::query()->where('role', '!=', UserRole::CBY_ADMIN->value)->firstOrFail();
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

        $this->actingAs($this->admin)->postJson("/api/v1/reference-tables/{$id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
        $this->actingAs($this->admin)->postJson("/api/v1/reference-tables/{$id}/activate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
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
}

<?php

namespace Tests\Feature\Reference;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\ReferenceTable;
use App\Models\ReferenceValue;
use App\Models\User;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceProtectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, ReferenceDataSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = $this->firstUserWithRole(UserRole::CBY_ADMIN);
    }

    public function test_key_is_immutable_on_update_for_table_and_value(): void
    {
        $table = ReferenceTable::query()->where('key', 'sector_activity')->firstOrFail();
        $value = ReferenceValue::query()->where('key', 'food_beverages')->firstOrFail();

        $this->actingAs($this->admin)->putJson("/api/v1/reference-tables/{$table->id}", [
            'key' => 'changed_key',
            'label' => $table->label,
            'version' => $table->version,
        ])->assertUnprocessable();

        $this->actingAs($this->admin)->putJson("/api/v1/reference-values/{$value->id}", [
            'key' => 'changed_key',
            'label' => $value->label,
            'version' => $value->version,
        ])->assertUnprocessable();
    }

    public function test_system_table_and_value_cannot_be_deleted_but_can_be_deactivated(): void
    {
        $table = ReferenceTable::query()->where('key', 'origin_country')->firstOrFail();
        $value = ReferenceValue::query()->where('key', 'cn')->firstOrFail();

        $this->actingAs($this->admin)->deleteJson("/api/v1/reference-tables/{$table->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'REFERENCE_TABLE_PROTECTED');

        $this->actingAs($this->admin)->postJson("/api/v1/reference-tables/{$table->id}/deactivate", [
            'version' => $table->version,
        ])->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($this->admin)->deleteJson("/api/v1/reference-values/{$value->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'REFERENCE_VALUE_PROTECTED');

        $this->actingAs($this->admin)->postJson("/api/v1/reference-values/{$value->id}/deactivate", [
            'version' => $value->version,
        ])->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_non_system_table_with_values_cannot_be_deleted(): void
    {
        $table = ReferenceTable::query()->create(['key' => 'custom_table', 'label' => 'Custom Table']);
        ReferenceValue::query()->create(['reference_table_id' => $table->id, 'key' => 'one', 'label' => 'One']);

        $this->actingAs($this->admin)->deleteJson("/api/v1/reference-tables/{$table->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'REFERENCE_TABLE_PROTECTED');
    }

    public function test_value_used_by_merchant_company_sector_cannot_be_deleted(): void
    {
        $table = ReferenceTable::query()->create(['key' => 'custom_sector', 'label' => 'Custom Sector']);
        $value = ReferenceValue::query()->create(['reference_table_id' => $table->id, 'key' => 'retail', 'label' => 'Retail']);

        $bank = Bank::query()->firstOrFail();
        $merchant = Merchant::query()->create([
            'bank_id' => $bank->id,
            'name' => 'Retail Merchant',
            'tax_number' => 'TX-9001',
            'tax_card_expiry' => now()->addYear(),
            'status' => 'active',
        ]);
        MerchantCompany::query()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Retail Co',
            'commercial_registration_number' => 'CR-1001',
            'commercial_registration_expiry' => now()->addYear(),
            'sector_reference_value_id' => $value->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)->deleteJson("/api/v1/reference-values/{$value->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'REFERENCE_VALUE_PROTECTED');
    }

    public function test_blocked_delete_attempt_is_audited(): void
    {
        $table = ReferenceTable::query()->where('key', 'origin_country')->firstOrFail();

        $this->actingAs($this->admin)->deleteJson("/api/v1/reference-tables/{$table->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'AUTHORIZATION_FAILURE',
            'subject_type' => ReferenceTable::class,
        ]);
    }

    public function test_blocked_key_change_attempts_are_audited(): void
    {
        $table = ReferenceTable::query()->where('key', 'origin_country')->firstOrFail();
        $value = ReferenceValue::query()->where('key', 'cn')->firstOrFail();

        $this->actingAs($this->admin)->putJson("/api/v1/reference-tables/{$table->id}", [
            'key' => 'changed',
            'label' => $table->label,
            'version' => $table->version,
        ])->assertUnprocessable();

        $this->actingAs($this->admin)->putJson("/api/v1/reference-values/{$value->id}", [
            'key' => 'changed',
            'label' => $value->label,
            'version' => $value->version,
        ])->assertUnprocessable();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'AUTHORIZATION_FAILURE',
            'subject_type' => ReferenceTable::class,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'AUTHORIZATION_FAILURE',
            'subject_type' => ReferenceValue::class,
        ]);
    }

    public function test_successful_delete_is_audited(): void
    {
        $table = ReferenceTable::query()->create(['key' => 'deletable', 'label' => 'Deletable']);

        $this->actingAs($this->admin)->deleteJson("/api/v1/reference-tables/{$table->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'GOVERNANCE_DELETED',
            'subject_type' => ReferenceTable::class,
            'subject_id' => $table->id,
        ]);
    }

    public function test_resources_expose_usage_state(): void
    {
        $table = ReferenceTable::query()->create(['key' => 'with_value', 'label' => 'With Value']);
        ReferenceValue::query()->create([
            'reference_table_id' => $table->id,
            'key' => 'one',
            'label' => 'One',
        ]);

        $this->actingAs($this->admin)->getJson('/api/v1/reference-tables?search=with_value')
            ->assertOk()
            ->assertJsonPath('data.0.is_in_use', true);
    }
}

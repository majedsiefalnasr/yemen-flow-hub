<?php

namespace Tests\Feature\Reference;

use App\Enums\UserRole;
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

class ReferenceImmutableKeysTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class, ReferenceDataSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = $this->firstUserWithRole(UserRole::CBY_ADMIN);
    }

    public function test_reference_table_key_change_is_a_validation_error(): void
    {
        $table = ReferenceTable::query()->create(['key' => 'immutable_table', 'label' => 'Immutable Table']);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/reference-tables/{$table->id}", [
                'key' => 'changed_table',
                'label' => 'Immutable Table',
                'version' => $table->refresh()->version,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);
    }

    public function test_reference_table_unchanged_key_is_idempotent(): void
    {
        $table = ReferenceTable::query()->create(['key' => 'same_table', 'label' => 'Same Table']);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/reference-tables/{$table->id}", [
                'key' => 'same_table',
                'label' => 'Same Table Updated',
                'version' => $table->refresh()->version,
            ])
            ->assertOk()
            ->assertJsonPath('data.key', 'same_table');
    }

    public function test_reference_value_key_change_is_a_validation_error(): void
    {
        $table = ReferenceTable::query()->create(['key' => 'immutable_values', 'label' => 'Immutable Values']);
        $value = ReferenceValue::query()->create([
            'reference_table_id' => $table->id,
            'key' => 'old_value',
            'label' => 'Old Value',
        ]);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/reference-values/{$value->id}", [
                'key' => 'new_value',
                'label' => 'Old Value',
                'version' => $value->refresh()->version,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);
    }

    public function test_reference_value_unchanged_key_is_idempotent(): void
    {
        $table = ReferenceTable::query()->create(['key' => 'same_values', 'label' => 'Same Values']);
        $value = ReferenceValue::query()->create([
            'reference_table_id' => $table->id,
            'key' => 'same_value',
            'label' => 'Same Value',
        ]);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/reference-values/{$value->id}", [
                'key' => 'same_value',
                'label' => 'Same Value Updated',
                'version' => $value->refresh()->version,
            ])
            ->assertOk()
            ->assertJsonPath('data.key', 'same_value');
    }
}

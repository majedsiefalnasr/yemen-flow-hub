<?php

namespace Tests\Feature\Governance;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, BankSeeder::class, UserSeeder::class]);
        $this->admin = $this->firstUserWithRole(UserRole::CBY_ADMIN);
    }

    public function test_create_bank_with_engine_fields_and_unique_swift(): void
    {
        $bankOrg = Organization::query()->where('code', 'commercial_banks')->firstOrFail();
        $payload = ['organization_id' => $bankOrg->id, 'code' => 'NEW', 'name' => 'New Bank', 'license_number' => 'LIC-1', 'swift_code' => 'NEWBYESA', 'status' => 'ACTIVE'];
        $this->actingAs($this->admin)->postJson('/api/v1/banks', $payload)
            ->assertCreated()
            ->assertJsonPath('data.organization.code', 'commercial_banks')
            ->assertJsonPath('data.swift_code', 'NEWBYESA');
        $this->actingAs($this->admin)->postJson('/api/v1/banks', [...$payload, 'code' => 'NEW2'])->assertUnprocessable();

        $this->actingAs($this->admin)->postJson('/api/v1/banks', ['organization_id' => $bankOrg->id, 'code' => 'NULL1', 'name' => 'Null One', 'swift_code' => null, 'status' => 'ACTIVE'])->assertCreated();
        $this->actingAs($this->admin)->postJson('/api/v1/banks', ['organization_id' => $bankOrg->id, 'code' => 'NULL2', 'name' => 'Null Two', 'swift_code' => null, 'status' => 'ACTIVE'])->assertCreated();
    }

    public function test_existing_banks_are_backfilled_and_suspend_allowed_with_history(): void
    {
        $bank = Bank::query()->where('code', 'YBRD')->firstOrFail();
        $this->assertNotNull($bank->organization_id);
        $this->assertSame('ACTIVE', $bank->status);
        $this->actingAs($this->admin)->postJson("/api/v1/banks/{$bank->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'SUSPENDED');
        $this->actingAs($this->admin)->deleteJson("/api/v1/banks/{$bank->id}")
            ->assertStatus(422)->assertJsonPath('error.code', 'BANK_IN_USE');
    }

    public function test_used_bank_organization_is_immutable_and_version_is_checked(): void
    {
        $bank = Bank::query()->where('code', 'YBRD')->firstOrFail();
        $this->actingAs($this->admin)->putJson("/api/v1/banks/{$bank->id}", [
            'organization_id' => 999,
            'code' => $bank->code,
            'name' => $bank->name,
            'status' => 'ACTIVE',
            'version' => $bank->version,
        ])->assertStatus(422)->assertJsonPath('error.code', 'BANK_ORGANIZATION_IMMUTABLE');

        $this->actingAs($this->admin)->putJson("/api/v1/banks/{$bank->id}", [
            'code' => $bank->code,
            'name' => $bank->name,
            'status' => 'ACTIVE',
            'version' => 99,
        ])->assertConflict();
    }
}

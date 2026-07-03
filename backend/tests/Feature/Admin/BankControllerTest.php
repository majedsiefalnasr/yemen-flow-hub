<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\TestCase;

class BankControllerTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernance();
    }

    private function makeCbyAdmin(): User
    {
        return $this->assignGovernanceIdentity(User::query()->create([
            'name' => 'CBY Admin',
            'email' => 'admin@cby.gov.ye',
            'password' => Hash::make('password'),
            'role' => UserRole::CBY_ADMIN->value,
            'bank_id' => null,
            'is_active' => true,
        ]), UserRole::CBY_ADMIN);
    }

    private function makeBankReviewer(Bank $bank): User
    {
        return $this->assignGovernanceIdentity(User::query()->create([
            'name' => 'Reviewer',
            'email' => 'reviewer@bank.com',
            'password' => Hash::make('password'),
            'role' => UserRole::BANK_REVIEWER->value,
            'bank_id' => $bank->id,
            'is_active' => true,
        ]), UserRole::BANK_REVIEWER);
    }

    private function makeSupportCommittee(): User
    {
        return $this->assignGovernanceIdentity(User::query()->create([
            'name' => 'Support Member',
            'email' => 'support@cby.gov.ye',
            'password' => Hash::make('password'),
            'role' => UserRole::SUPPORT_COMMITTEE->value,
            'bank_id' => null,
            'is_active' => true,
        ]), UserRole::SUPPORT_COMMITTEE);
    }

    private function makeBank(array $attrs = []): Bank
    {
        return Bank::query()->create(array_merge([
            'name' => 'البنك التجاري اليمني',
            'code' => 'YCB',
            'is_active' => true,
        ], $attrs));
    }

    // ─── Index ───────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_banks_for_cby_admin(): void
    {
        $this->makeBank();
        $admin = $this->makeCbyAdmin();

        $response = $this->actingAs($admin)->getJson('/api/banks');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['data' => [['id', 'name', 'code', 'is_active']]]]);
    }

    public function test_index_returns_banks_for_bank_reviewer(): void
    {
        $bank = $this->makeBank();
        $reviewer = $this->makeBankReviewer($bank);

        $response = $this->actingAs($reviewer)->getJson('/api/banks');

        $response->assertOk();
    }

    public function test_index_returns_all_banks_for_support_committee(): void
    {
        $this->makeBank(['name' => 'بنك أول', 'code' => 'ONE']);
        $this->makeBank(['name' => 'بنك ثانٍ', 'code' => 'TWO']);
        $support = $this->makeSupportCommittee();

        $response = $this->actingAs($support)->getJson('/api/banks');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/banks')->assertUnauthorized();
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_store_creates_bank_for_cby_admin(): void
    {
        $admin = $this->makeCbyAdmin();

        $response = $this->actingAs($admin)->postJson('/api/banks', [
            'name' => 'بنك سبأ الإسلامي',
            'code' => 'SIB',
            'is_active' => true,
            'adminName' => 'مدير بنك سبأ',
            'adminEmail' => 'admin@sib.test',
            'adminPassword' => 'TempPassword123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'بنك سبأ الإسلامي')
            ->assertJsonPath('data.code', 'SIB')
            ->assertJsonPath('data.admin.name', 'مدير بنك سبأ')
            ->assertJsonPath('data.admin.email', 'admin@sib.test');

        $this->assertDatabaseHas('banks', ['code' => 'SIB', 'name' => 'بنك سبأ الإسلامي']);
        $this->assertDatabaseHas('users', [
            'email' => 'admin@sib.test',
            'role' => UserRole::BANK_ADMIN->value,
            'must_change_password' => true,
        ]);
    }

    public function test_store_returns_403_for_bank_reviewer(): void
    {
        $bank = $this->makeBank();
        $reviewer = $this->makeBankReviewer($bank);

        $this->actingAs($reviewer)->postJson('/api/banks', [
            'name' => 'بنك جديد',
            'code' => 'NBK',
        ])->assertForbidden();
    }

    public function test_store_validates_name_uniqueness(): void
    {
        $admin = $this->makeCbyAdmin();
        $this->makeBank(['name' => 'البنك التجاري اليمني', 'code' => 'YCB']);

        $this->actingAs($admin)->postJson('/api/banks', [
            'name' => 'البنك التجاري اليمني',
            'code' => 'ACB',
            'adminName' => 'مدير البنك',
            'adminEmail' => 'admin@acb.test',
            'adminPassword' => 'TempPassword123',
        ])->assertUnprocessable();
    }

    public function test_store_validates_code_uniqueness(): void
    {
        $admin = $this->makeCbyAdmin();
        $this->makeBank(['code' => 'YCB']);

        $this->actingAs($admin)->postJson('/api/banks', [
            'name' => 'بنك آخر',
            'code' => 'YCB',
            'adminName' => 'مدير البنك',
            'adminEmail' => 'admin@other.test',
            'adminPassword' => 'TempPassword123',
        ])->assertUnprocessable();
    }

    public function test_store_requires_name_and_code(): void
    {
        $admin = $this->makeCbyAdmin();

        $this->actingAs($admin)->postJson('/api/banks', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'code']);
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_update_modifies_bank_for_cby_admin(): void
    {
        $admin = $this->makeCbyAdmin();
        $bank = $this->makeBank();
        $bankAdmin = User::query()->create([
            'name' => 'Old Bank Admin',
            'email' => 'old-admin@bank.test',
            'password' => Hash::make('Password123'),
            'role' => UserRole::BANK_ADMIN,
            'bank_id' => $bank->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->putJson("/api/banks/{$bank->id}", [
            'name' => 'اسم محدث',
            'code' => $bank->code,
            'is_active' => false,
            'adminName' => 'Updated Bank Admin',
            'adminEmail' => 'updated-admin@bank.test',
        ]);

        $response->assertJsonPath('data.admin.name', 'Updated Bank Admin')
            ->assertJsonPath('data.admin.email', 'updated-admin@bank.test');
        $this->assertSame('Updated Bank Admin', $bankAdmin->refresh()->name);

        $response->assertOk()
            ->assertJsonPath('data.name', 'اسم محدث')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_update_returns_403_for_non_admin(): void
    {
        $bank = $this->makeBank();
        $reviewer = $this->makeBankReviewer($bank);

        $this->actingAs($reviewer)->putJson("/api/banks/{$bank->id}", [
            'name' => 'اسم محدث',
            'code' => $bank->code,
            'is_active' => true,
        ])->assertForbidden();
    }

    public function test_update_allows_same_name_for_same_bank(): void
    {
        $admin = $this->makeCbyAdmin();
        $bank = $this->makeBank(['name' => 'البنك التجاري اليمني', 'code' => 'YCB']);

        $this->actingAs($admin)->putJson("/api/banks/{$bank->id}", [
            'name' => 'البنك التجاري اليمني',
            'code' => 'YCB',
            'is_active' => true,
        ])->assertOk();
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    public function test_delete_returns_403_for_non_admin(): void
    {
        $bank = $this->makeBank();
        $reviewer = $this->makeBankReviewer($bank);

        $this->actingAs($reviewer)->deleteJson("/api/banks/{$bank->id}")->assertForbidden();
    }

    public function test_delete_succeeds_for_cby_admin(): void
    {
        $admin = $this->makeCbyAdmin();
        $bank = $this->makeBank();

        $this->actingAs($admin)->deleteJson("/api/banks/{$bank->id}")->assertOk();

        $this->assertDatabaseMissing('banks', ['id' => $bank->id]);
    }
}

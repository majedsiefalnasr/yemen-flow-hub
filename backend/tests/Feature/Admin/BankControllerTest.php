<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BankControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeCbyAdmin(): User
    {
        return User::query()->create([
            'name' => 'CBY Admin',
            'email' => 'admin@cby.gov.ye',
            'password' => Hash::make('password'),
            'role' => UserRole::CBY_ADMIN->value,
            'bank_id' => null,
            'is_active' => true,
        ]);
    }

    private function makeBankReviewer(Bank $bank): User
    {
        return User::query()->create([
            'name' => 'Reviewer',
            'email' => 'reviewer@bank.com',
            'password' => Hash::make('password'),
            'role' => UserRole::BANK_REVIEWER->value,
            'bank_id' => $bank->id,
            'is_active' => true,
        ]);
    }

    private function makeBank(array $attrs = []): Bank
    {
        return Bank::query()->create(array_merge([
            'name_ar' => 'البنك التجاري اليمني',
            'name_en' => 'Yemen Commercial Bank',
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
            ->assertJsonStructure(['data' => [['id', 'name_ar', 'name_en', 'code', 'is_active']]]);
    }

    public function test_index_returns_banks_for_bank_reviewer(): void
    {
        $bank = $this->makeBank();
        $reviewer = $this->makeBankReviewer($bank);

        $response = $this->actingAs($reviewer)->getJson('/api/banks');

        $response->assertOk();
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
            'name_ar' => 'بنك سبأ الإسلامي',
            'name_en' => 'Saba Islamic Bank',
            'code' => 'SIB',
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name_ar', 'بنك سبأ الإسلامي')
            ->assertJsonPath('data.name_en', 'Saba Islamic Bank')
            ->assertJsonPath('data.code', 'SIB');

        $this->assertDatabaseHas('banks', ['code' => 'SIB', 'name_ar' => 'بنك سبأ الإسلامي']);
    }

    public function test_store_returns_403_for_bank_reviewer(): void
    {
        $bank = $this->makeBank();
        $reviewer = $this->makeBankReviewer($bank);

        $this->actingAs($reviewer)->postJson('/api/banks', [
            'name_ar' => 'بنك جديد',
            'name_en' => 'New Bank',
            'code' => 'NBK',
        ])->assertForbidden();
    }

    public function test_store_validates_name_ar_uniqueness(): void
    {
        $admin = $this->makeCbyAdmin();
        $this->makeBank(['name_ar' => 'البنك التجاري اليمني', 'code' => 'YCB']);

        $this->actingAs($admin)->postJson('/api/banks', [
            'name_ar' => 'البنك التجاري اليمني',
            'name_en' => 'Another English Name',
            'code' => 'ACB',
        ])->assertUnprocessable();
    }

    public function test_store_validates_code_uniqueness(): void
    {
        $admin = $this->makeCbyAdmin();
        $this->makeBank(['code' => 'YCB']);

        $this->actingAs($admin)->postJson('/api/banks', [
            'name_ar' => 'بنك آخر',
            'name_en' => 'Other Bank',
            'code' => 'YCB',
        ])->assertUnprocessable();
    }

    public function test_store_requires_name_ar_and_name_en(): void
    {
        $admin = $this->makeCbyAdmin();

        $this->actingAs($admin)->postJson('/api/banks', [
            'code' => 'XYZ',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name_ar', 'name_en']);
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_update_modifies_bank_for_cby_admin(): void
    {
        $admin = $this->makeCbyAdmin();
        $bank = $this->makeBank();

        $response = $this->actingAs($admin)->putJson("/api/banks/{$bank->id}", [
            'name_ar' => 'اسم محدث',
            'name_en' => 'Updated Name',
            'code' => $bank->code,
            'is_active' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name_ar', 'اسم محدث')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_update_returns_403_for_non_admin(): void
    {
        $bank = $this->makeBank();
        $reviewer = $this->makeBankReviewer($bank);

        $this->actingAs($reviewer)->putJson("/api/banks/{$bank->id}", [
            'name_ar' => 'اسم محدث',
            'name_en' => 'Updated Name',
            'code' => $bank->code,
            'is_active' => true,
        ])->assertForbidden();
    }

    public function test_update_allows_same_name_ar_for_same_bank(): void
    {
        $admin = $this->makeCbyAdmin();
        $bank = $this->makeBank(['name_ar' => 'البنك التجاري اليمني', 'code' => 'YCB']);

        $this->actingAs($admin)->putJson("/api/banks/{$bank->id}", [
            'name_ar' => 'البنك التجاري اليمني',
            'name_en' => 'Yemen Commercial Bank Updated',
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

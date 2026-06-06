<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserControllerTest extends TestCase
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

    private function makeBank(): Bank
    {
        return Bank::query()->create([
            'name' => 'بنك تجريبي',
            'code' => 'TST',
            'is_active' => true,
        ]);
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_users_for_cby_admin(): void
    {
        $admin = $this->makeCbyAdmin();

        $response = $this->actingAs($admin)->getJson('/api/users');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['data' => [['id', 'name', 'email', 'role']]]]);
    }

    public function test_index_honors_role_and_per_page_filters(): void
    {
        $admin = $this->makeCbyAdmin();
        $bank = $this->makeBank();

        User::query()->create([
            'name' => 'Data Entry',
            'email' => 'entry@bank.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY->value,
            'bank_id' => $bank->id,
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 3; $i++) {
            User::query()->create([
                'name' => "Reviewer {$i}",
                'email' => "reviewer{$i}@bank.com",
                'password' => Hash::make('password'),
                'role' => UserRole::BANK_REVIEWER->value,
                'bank_id' => $bank->id,
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($admin)->getJson('/api/users?role=BANK_REVIEWER&per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data.data')
            ->assertJsonPath('data.data.0.role', UserRole::BANK_REVIEWER->value)
            ->assertJsonPath('data.data.1.role', UserRole::BANK_REVIEWER->value);
    }

    public function test_index_returns_403_for_bank_reviewer(): void
    {
        $bank = $this->makeBank();
        $reviewer = $this->makeBankReviewer($bank);

        $this->actingAs($reviewer)->getJson('/api/users')->assertForbidden();
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_store_creates_cby_user_with_null_bank_id(): void
    {
        $admin = $this->makeCbyAdmin();

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'محمد العمري',
            'email' => 'exec@cby.gov.ye',
            'password' => 'password123',
            'role' => UserRole::EXECUTIVE_MEMBER->value,
            'bank_id' => null,
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', UserRole::EXECUTIVE_MEMBER->value);

        $this->assertDatabaseHas('users', ['email' => 'exec@cby.gov.ye', 'bank_id' => null]);
    }

    public function test_store_creates_bank_user_with_bank_id(): void
    {
        $admin = $this->makeCbyAdmin();
        $bank = $this->makeBank();

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'علي القاضي',
            'email' => 'entry@bank.com',
            'password' => 'password123',
            'role' => UserRole::DATA_ENTRY->value,
            'bank_id' => $bank->id,
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.bank_id', $bank->id);
    }

    public function test_store_returns_403_for_bank_reviewer(): void
    {
        $bank = $this->makeBank();
        $reviewer = $this->makeBankReviewer($bank);

        $this->actingAs($reviewer)->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'new@bank.com',
            'password' => 'password123',
            'role' => UserRole::DATA_ENTRY->value,
            'bank_id' => $bank->id,
        ])->assertForbidden();
    }

    public function test_store_rejects_bank_role_with_null_bank_id(): void
    {
        $admin = $this->makeCbyAdmin();

        $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'علي',
            'email' => 'entry@example.com',
            'password' => 'password123',
            'role' => UserRole::DATA_ENTRY->value,
            'bank_id' => null,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['bank_id']);
    }

    public function test_store_rejects_swift_officer_with_null_bank_id(): void
    {
        $admin = $this->makeCbyAdmin();

        $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'موظف سويفت',
            'email' => 'swift@example.com',
            'password' => 'password123',
            'role' => UserRole::SWIFT_OFFICER->value,
            'bank_id' => null,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['bank_id']);
    }

    public function test_store_rejects_cby_role_with_non_null_bank_id(): void
    {
        $admin = $this->makeCbyAdmin();
        $bank = $this->makeBank();

        $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'لجنة المساندة',
            'email' => 'support@cby.gov.ye',
            'password' => 'password123',
            'role' => UserRole::SUPPORT_COMMITTEE->value,
            'bank_id' => $bank->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['bank_id']);
    }

    public function test_store_rejects_committee_director_with_bank_id(): void
    {
        $admin = $this->makeCbyAdmin();
        $bank = $this->makeBank();

        $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'مدير اللجنة',
            'email' => 'director@cby.gov.ye',
            'password' => 'password123',
            'role' => UserRole::COMMITTEE_DIRECTOR->value,
            'bank_id' => $bank->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['bank_id']);
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_update_modifies_user_for_cby_admin(): void
    {
        $admin = $this->makeCbyAdmin();
        $bank = $this->makeBank();
        $user = $this->makeBankReviewer($bank);

        $response = $this->actingAs($admin)->putJson("/api/users/{$user->id}", [
            'name' => 'الاسم المحدث',
            'email' => $user->email,
            'role' => UserRole::BANK_REVIEWER->value,
            'bank_id' => $bank->id,
            'is_active' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'الاسم المحدث')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_update_returns_403_for_non_admin(): void
    {
        $bank = $this->makeBank();
        $reviewer = $this->makeBankReviewer($bank);

        $this->actingAs($reviewer)->putJson("/api/users/{$reviewer->id}", [
            'name' => 'Hacked Name',
            'email' => $reviewer->email,
            'role' => UserRole::BANK_REVIEWER->value,
            'bank_id' => $bank->id,
            'is_active' => true,
        ])->assertForbidden();
    }

    public function test_update_omitting_password_does_not_change_it(): void
    {
        $admin = $this->makeCbyAdmin();
        $bank = $this->makeBank();
        $user = $this->makeBankReviewer($bank);
        $originalHash = $user->password;

        $this->actingAs($admin)->putJson("/api/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => UserRole::BANK_REVIEWER->value,
            'bank_id' => $bank->id,
            'is_active' => true,
        ])->assertOk();

        $this->assertSame($originalHash, $user->fresh()->password);
    }

    // ─── Delete (deactivate) ─────────────────────────────────────────────────

    public function test_delete_deactivates_user_for_cby_admin(): void
    {
        $admin = $this->makeCbyAdmin();
        $bank = $this->makeBank();
        $user = $this->makeBankReviewer($bank);

        $this->actingAs($admin)->deleteJson("/api/users/{$user->id}")->assertOk();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);
    }

    public function test_delete_returns_403_for_non_admin(): void
    {
        $bank = $this->makeBank();
        $reviewer = $this->makeBankReviewer($bank);

        $this->actingAs($reviewer)->deleteJson("/api/users/{$reviewer->id}")->assertForbidden();
    }
}

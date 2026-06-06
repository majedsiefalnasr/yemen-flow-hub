<?php

namespace Tests\Feature\Staff;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BankAdminStaffManagementTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;

    private Bank $otherBank;

    private User $bankAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bank = Bank::query()->create(['name' => 'Bank YCB', 'code' => 'YCB', 'is_active' => true]);
        $this->otherBank = Bank::query()->create(['name' => 'Bank OTH', 'code' => 'OTH', 'is_active' => true]);
        $this->bankAdmin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank, 'admin@bank.test');
    }

    private function makeUser(UserRole $role, ?Bank $bank, string $email): User
    {
        return User::query()->create([
            'name' => $role->value,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }

    // ─── List staff ───────────────────────────────────────────────────────────

    public function test_bank_admin_lists_own_bank_data_entry_and_reviewer_staff(): void
    {
        $entry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank, 'entry@bank.test');
        $reviewer = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank, 'reviewer@bank.test');
        $otherEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank, 'other.entry@bank.test');
        $swift = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank, 'swift@bank.test');

        $response = $this->actingAs($this->bankAdmin)->getJson('/api/users');

        $response->assertOk();
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains($entry->id), 'own DATA_ENTRY should be visible');
        $this->assertTrue($ids->contains($reviewer->id), 'own BANK_REVIEWER should be visible');
        $this->assertFalse($ids->contains($otherEntry->id), 'other bank staff must be excluded');
        $this->assertFalse($ids->contains($swift->id), 'SWIFT_OFFICER must be excluded');
        $this->assertFalse($ids->contains($this->bankAdmin->id), 'BANK_ADMIN itself must be excluded');
    }

    public function test_bank_admin_list_is_empty_when_no_manageable_staff(): void
    {
        $response = $this->actingAs($this->bankAdmin)->getJson('/api/users');

        $response->assertOk();
        $this->assertEmpty($response->json('data.data'));
    }

    // ─── Create staff ─────────────────────────────────────────────────────────

    public function test_bank_admin_creates_data_entry_user_in_own_bank(): void
    {
        $response = $this->actingAs($this->bankAdmin)->postJson('/api/users', [
            'name' => 'موظف جديد',
            'email' => 'new.entry@bank.test',
            'password' => 'password123',
            'role' => UserRole::DATA_ENTRY->value,
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'email' => 'new.entry@bank.test',
            'bank_id' => $this->bank->id,
            'role' => UserRole::DATA_ENTRY->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->bankAdmin->id,
            'action' => AuditAction::USER_CREATED->value,
        ]);
    }

    public function test_bank_admin_creates_bank_reviewer_user_in_own_bank(): void
    {
        $response = $this->actingAs($this->bankAdmin)->postJson('/api/users', [
            'name' => 'مراجع جديد',
            'email' => 'new.reviewer@bank.test',
            'password' => 'password123',
            'role' => UserRole::BANK_REVIEWER->value,
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'email' => 'new.reviewer@bank.test',
            'role' => UserRole::BANK_REVIEWER->value,
            'bank_id' => $this->bank->id,
        ]);
    }

    public function test_bank_admin_cannot_create_cby_admin_role(): void
    {
        $response = $this->actingAs($this->bankAdmin)->postJson('/api/users', [
            'name' => 'Bad Actor',
            'email' => 'bad@bank.test',
            'password' => 'password123',
            'role' => UserRole::CBY_ADMIN->value,
            'bank_id' => $this->bank->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'bad@bank.test']);
    }

    public function test_bank_admin_cannot_create_swift_officer_role(): void
    {
        $response = $this->actingAs($this->bankAdmin)->postJson('/api/users', [
            'name' => 'Swift User',
            'email' => 'swift.bad@bank.test',
            'password' => 'password123',
            'role' => UserRole::SWIFT_OFFICER->value,
            'bank_id' => $this->bank->id,
        ]);

        $response->assertForbidden();
    }

    public function test_bank_admin_cannot_create_user_in_other_bank(): void
    {
        $response = $this->actingAs($this->bankAdmin)->postJson('/api/users', [
            'name' => 'Cross Bank',
            'email' => 'cross@bank.test',
            'password' => 'password123',
            'role' => UserRole::DATA_ENTRY->value,
            'bank_id' => $this->otherBank->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'cross@bank.test']);
    }

    // ─── Update staff ─────────────────────────────────────────────────────────

    public function test_bank_admin_updates_own_bank_staff_member(): void
    {
        $target = $this->makeUser(UserRole::DATA_ENTRY, $this->bank, 'update.target@bank.test');

        $response = $this->actingAs($this->bankAdmin)->putJson("/api/users/{$target->id}", [
            'name' => 'اسم محدّث',
            'email' => $target->email,
            'role' => UserRole::BANK_REVIEWER->value,
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'name' => 'اسم محدّث',
            'role' => UserRole::BANK_REVIEWER->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->bankAdmin->id,
            'action' => AuditAction::USER_UPDATED->value,
        ]);
    }

    public function test_bank_admin_cannot_update_staff_in_other_bank(): void
    {
        $target = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank, 'other.target@bank.test');

        $this->actingAs($this->bankAdmin)->putJson("/api/users/{$target->id}", [
            'name' => 'Hacker',
            'email' => $target->email,
            'role' => UserRole::DATA_ENTRY->value,
            'bank_id' => $this->otherBank->id,
            'is_active' => true,
        ])->assertForbidden();
    }

    public function test_bank_admin_cannot_escalate_role_to_cby_admin(): void
    {
        $target = $this->makeUser(UserRole::DATA_ENTRY, $this->bank, 'escalate.target@bank.test');

        $this->actingAs($this->bankAdmin)->putJson("/api/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'role' => UserRole::CBY_ADMIN->value,
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ])->assertForbidden();
    }

    // ─── Deactivate staff ─────────────────────────────────────────────────────

    public function test_bank_admin_deactivates_own_bank_staff(): void
    {
        $target = $this->makeUser(UserRole::DATA_ENTRY, $this->bank, 'deactivate.target@bank.test');

        $response = $this->actingAs($this->bankAdmin)->putJson("/api/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'role' => UserRole::DATA_ENTRY->value,
            'bank_id' => $this->bank->id,
            'is_active' => false,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false]);
    }

    public function test_bank_admin_cannot_deactivate_staff_in_other_bank(): void
    {
        $target = $this->makeUser(UserRole::BANK_REVIEWER, $this->otherBank, 'other.deactivate@bank.test');

        $this->actingAs($this->bankAdmin)->putJson("/api/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'role' => UserRole::BANK_REVIEWER->value,
            'bank_id' => $target->bank_id,
            'is_active' => false,
        ])
            ->assertForbidden();
    }

    // ─── Unauthenticated / wrong role ─────────────────────────────────────────

    public function test_data_entry_user_cannot_list_users(): void
    {
        $dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank, 'noauth.entry@bank.test');

        $this->actingAs($dataEntry)->getJson('/api/users')->assertForbidden();
    }
}

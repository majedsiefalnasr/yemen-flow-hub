<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BankAdminRbacTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;
    private Bank $otherBank;
    private User $bankAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bank = $this->makeBank('YCB');
        $this->otherBank = $this->makeBank('OTH');
        $this->bankAdmin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank, 'bank.admin@bank.test');
    }

    private function makeBank(string $code): Bank
    {
        return Bank::query()->create([
            'name' => "Bank {$code}",
            'code' => $code,
            'is_active' => true,
        ]);
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

    private function makeRequest(Bank $bank, RequestStatus $status): ImportRequest
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $bank, uniqid('creator', true).'@bank.test');

        app()->instance('workflow.transition.active', true);
        try {
            return ImportRequest::query()->create([
                'bank_id' => $bank->id,
                'created_by' => $creator->id,
                'currency' => 'USD',
                'amount' => 1000,
                'supplier_name' => 'Supplier',
                'goods_description' => 'Goods',
                'port_of_entry' => 'Aden',
                'status' => $status,
                'current_owner_role' => UserRole::DATA_ENTRY,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    public function test_bank_admin_lists_only_own_bank_manageable_users(): void
    {
        $ownEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank, 'entry@bank.test');
        $this->makeUser(UserRole::BANK_REVIEWER, $this->otherBank, 'other.reviewer@bank.test');
        $this->makeUser(UserRole::CBY_ADMIN, null, 'admin@cby.test');
        $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank, 'swift@bank.test');

        $response = $this->actingAs($this->bankAdmin)->getJson('/api/users');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($ownEntry->id));
        $this->assertFalse($ids->contains($this->bankAdmin->id));
    }

    public function test_bank_admin_creates_only_own_bank_data_entry_or_reviewer(): void
    {
        $this->actingAs($this->bankAdmin)->postJson('/api/users', [
            'name' => 'New Entry',
            'email' => 'new.entry@bank.test',
            'password' => 'password123',
            'role' => UserRole::DATA_ENTRY->value,
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ])->assertCreated();

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

    public function test_bank_admin_cannot_create_forbidden_role_or_cross_bank_user(): void
    {
        $this->actingAs($this->bankAdmin)->postJson('/api/users', [
            'name' => 'Bad Role',
            'email' => 'bad.role@bank.test',
            'password' => 'password123',
            'role' => UserRole::CBY_ADMIN->value,
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ])->assertForbidden();

        $this->actingAs($this->bankAdmin)->postJson('/api/users', [
            'name' => 'Cross Bank',
            'email' => 'cross.bank@bank.test',
            'password' => 'password123',
            'role' => UserRole::DATA_ENTRY->value,
            'bank_id' => $this->otherBank->id,
            'is_active' => true,
        ])->assertForbidden();
    }

    public function test_bank_admin_can_update_and_deactivate_own_bank_managed_user_only(): void
    {
        $target = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank, 'reviewer@bank.test');
        $other = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank, 'other.entry@bank.test');

        $this->actingAs($this->bankAdmin)->putJson("/api/users/{$target->id}", [
            'name' => 'Updated Reviewer',
            'email' => $target->email,
            'role' => UserRole::BANK_REVIEWER->value,
            'bank_id' => $this->bank->id,
            'password' => 'newpass123',
            'is_active' => false,
        ])->assertOk();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Updated Reviewer', 'is_active' => false]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $this->bankAdmin->id, 'action' => AuditAction::USER_UPDATED->value]);

        $this->actingAs($this->bankAdmin)->deleteJson("/api/users/{$other->id}")->assertForbidden();
    }

    public function test_bank_admin_updates_only_own_bank_name(): void
    {
        $this->actingAs($this->bankAdmin)->putJson("/api/banks/{$this->bank->id}", [
            'name' => 'Updated Bank Name',
        ])->assertOk()->assertJsonPath('data.name', 'Updated Bank Name');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->bankAdmin->id,
            'action' => AuditAction::BANK_UPDATED->value,
        ]);

        $this->actingAs($this->bankAdmin)->putJson("/api/banks/{$this->otherBank->id}", [
            'name' => 'Other Name',
        ])->assertForbidden();
    }

    // ─── AC-3: GET /api/requests is org-scoped ───────────────────────────────

    public function test_bank_admin_request_list_returns_only_own_bank_requests(): void
    {
        $ownRequest = $this->makeRequest($this->bank, RequestStatus::SUBMITTED);
        $this->makeRequest($this->otherBank, RequestStatus::SUBMITTED);

        $response = $this->actingAs($this->bankAdmin)->getJson('/api/requests');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($ownRequest->id));
        $this->assertEquals(1, $ids->count());
    }

    public function test_bank_admin_request_list_excludes_other_bank_requests(): void
    {
        $this->makeRequest($this->otherBank, RequestStatus::BANK_REVIEW);
        $this->makeRequest($this->otherBank, RequestStatus::SUBMITTED);

        $response = $this->actingAs($this->bankAdmin)->getJson('/api/requests');

        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    // ─── AC-4: CBY-internal endpoints return 403 ─────────────────────────────

    public function test_bank_admin_cannot_access_voting_list(): void
    {
        $this->actingAs($this->bankAdmin)->getJson('/api/voting')->assertForbidden();
    }

    public function test_bank_admin_cannot_claim_support_review(): void
    {
        $request = $this->makeRequest($this->bank, RequestStatus::SUPPORT_REVIEW_PENDING);

        $this->actingAs($this->bankAdmin)
            ->postJson("/api/workflow/{$request->id}/claim-support-review")
            ->assertForbidden();
    }

    public function test_bank_admin_dashboard_is_own_bank_scoped_and_global_areas_forbidden(): void
    {
        $this->makeRequest($this->bank, RequestStatus::SUBMITTED);
        $this->makeRequest($this->otherBank, RequestStatus::SUBMITTED);

        $this->actingAs($this->bankAdmin)->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.pending', 1);

        $this->actingAs($this->bankAdmin)->getJson('/api/audit')->assertForbidden();
        $this->actingAs($this->bankAdmin)->getJson('/api/reports/workflow')->assertForbidden();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->bankAdmin->id,
            'action' => AuditAction::AUTHORIZATION_FAILURE->value,
        ]);
    }
}

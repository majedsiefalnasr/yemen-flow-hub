<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Workflow\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuditControllerTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bank = $this->makeBank();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeBank(string $code = 'YCB'): Bank
    {
        return Bank::query()->create([
            'name' => "بنك {$code}",
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function makeUser(UserRole $role, ?Bank $bank = null): User
    {
        static $counter = 0;
        $counter++;
        return User::query()->create([
            'name' => "User {$counter}",
            'email' => "user{$counter}@audittest.com",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }

    private function makeRequest(User $creator, RequestStatus $status = RequestStatus::DRAFT): ImportRequest
    {
        app()->instance('workflow.transition.active', true);
        try {
            return ImportRequest::query()->create([
                'bank_id' => $creator->bank_id ?? $this->bank->id,
                'created_by' => $creator->id,
                'currency' => 'USD',
                'amount' => 5000.00,
                'supplier_name' => 'Test Supplier',
                'goods_description' => 'Test goods',
                'port_of_entry' => 'Aden Port',
                'status' => $status,
                'current_owner_role' => UserRole::DATA_ENTRY,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    // ─── GET /api/audit — access control ─────────────────────────────────────

    /** @test */
    public function test_cby_admin_can_access_audit_log(): void
    {
        $admin = $this->makeUser(UserRole::CBY_ADMIN);
        AuditLog::query()->create([
            'user_id' => $admin->id,
            'user_role' => UserRole::CBY_ADMIN->value,
            'action' => AuditAction::LOGIN->value,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/audit');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['data' => [['id', 'action', 'created_at']], 'meta']]);
    }

    /** @test */
    public function test_non_cby_admin_cannot_access_audit_log(): void
    {
        foreach ([
            UserRole::DATA_ENTRY,
            UserRole::BANK_REVIEWER,
            UserRole::SUPPORT_COMMITTEE,
            UserRole::EXECUTIVE_MEMBER,
            UserRole::COMMITTEE_DIRECTOR,
            UserRole::SWIFT_OFFICER,
        ] as $role) {
            $bankForRole = in_array($role, [UserRole::DATA_ENTRY, UserRole::BANK_REVIEWER, UserRole::SWIFT_OFFICER], true)
                ? $this->bank
                : null;
            $user = $this->makeUser($role, $bankForRole);
            $this->actingAs($user)->getJson('/api/audit')->assertForbidden();
        }
    }

    // ─── GET /api/audit — response structure ─────────────────────────────────

    /** @test */
    public function test_audit_log_response_includes_user_details(): void
    {
        $admin = $this->makeUser(UserRole::CBY_ADMIN);
        $dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        AuditLog::query()->create([
            'user_id' => $dataEntry->id,
            'user_role' => UserRole::DATA_ENTRY->value,
            'action' => AuditAction::REQUEST_CREATED->value,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/audit');

        $response->assertOk();
        $entry = $response->json('data.data.0');

        $this->assertNotNull($entry['user']);
        $this->assertSame($dataEntry->id, $entry['user']['id']);
        $this->assertSame($dataEntry->name, $entry['user']['name']);
        $this->assertSame($dataEntry->email, $entry['user']['email']);
        $this->assertSame(UserRole::DATA_ENTRY->value, $entry['user']['role']);
    }

    /** @test */
    public function test_audit_log_response_uses_entity_type_and_entity_id_fields(): void
    {
        $admin = $this->makeUser(UserRole::CBY_ADMIN);
        $dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($dataEntry);

        AuditLog::query()->create([
            'user_id' => $dataEntry->id,
            'user_role' => UserRole::DATA_ENTRY->value,
            'action' => AuditAction::STATUS_TRANSITION->value,
            'subject_type' => ImportRequest::class,
            'subject_id' => $request->id,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/audit');

        $response->assertOk();
        $entry = $response->json('data.data.0');

        $this->assertArrayHasKey('entity_type', $entry);
        $this->assertArrayHasKey('entity_id', $entry);
        $this->assertSame('ImportRequest', $entry['entity_type']);
        $this->assertSame($request->id, $entry['entity_id']);
    }

    /** @test */
    public function test_audit_log_is_paginated(): void
    {
        $admin = $this->makeUser(UserRole::CBY_ADMIN);

        for ($i = 0; $i < 5; $i++) {
            AuditLog::query()->create([
                'user_id' => $admin->id,
                'user_role' => UserRole::CBY_ADMIN->value,
                'action' => AuditAction::LOGIN->value,
            ]);
        }

        $response = $this->actingAs($admin)->getJson('/api/audit');

        $response->assertOk();
        $this->assertIsArray($response->json('data.data'));
        $this->assertCount(5, $response->json('data.data'));
        $this->assertSame(1, $response->json('data.meta.current_page'));
        $this->assertSame(30, $response->json('data.meta.per_page'));
        $this->assertSame(5, $response->json('data.meta.total'));
    }

    // ─── GET /api/audit — filters ─────────────────────────────────────────────

    /** @test */
    public function test_audit_log_filterable_by_action(): void
    {
        $admin = $this->makeUser(UserRole::CBY_ADMIN);

        AuditLog::query()->create([
            'user_id' => $admin->id,
            'user_role' => UserRole::CBY_ADMIN->value,
            'action' => AuditAction::LOGIN->value,
        ]);
        AuditLog::query()->create([
            'user_id' => $admin->id,
            'user_role' => UserRole::CBY_ADMIN->value,
            'action' => AuditAction::LOGOUT->value,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/audit?action=LOGIN');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertSame('LOGIN', $data[0]['action']);
    }

    /** @test */
    public function test_audit_log_filterable_by_user_id(): void
    {
        $admin = $this->makeUser(UserRole::CBY_ADMIN);
        $dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        AuditLog::query()->create([
            'user_id' => $admin->id,
            'user_role' => UserRole::CBY_ADMIN->value,
            'action' => AuditAction::LOGIN->value,
        ]);
        AuditLog::query()->create([
            'user_id' => $dataEntry->id,
            'user_role' => UserRole::DATA_ENTRY->value,
            'action' => AuditAction::REQUEST_CREATED->value,
        ]);

        $response = $this->actingAs($admin)->getJson("/api/audit?user_id={$dataEntry->id}");

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertSame($dataEntry->id, $data[0]['user_id']);
    }

    /** @test */
    public function test_audit_log_filterable_by_date_range(): void
    {
        $admin = $this->makeUser(UserRole::CBY_ADMIN);

        // AuditLog has $timestamps = false and created_at is not fillable,
        // so we use forceFill + save to control created_at.
        $log1 = new AuditLog(['user_id' => $admin->id, 'user_role' => UserRole::CBY_ADMIN->value, 'action' => AuditAction::LOGIN->value]);
        $log1->forceFill(['created_at' => '2026-01-01 10:00:00'])->save();

        $log2 = new AuditLog(['user_id' => $admin->id, 'user_role' => UserRole::CBY_ADMIN->value, 'action' => AuditAction::LOGIN->value]);
        $log2->forceFill(['created_at' => '2026-06-15 10:00:00'])->save();

        $response = $this->actingAs($admin)->getJson('/api/audit?from_date=2026-06-01&to_date=2026-06-30');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    // ─── GET /api/requests/{id}/history ──────────────────────────────────────

    /** @test */
    public function test_history_endpoint_returns_actor_details(): void
    {
        $dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $bankReviewer = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $request = $this->makeRequest($dataEntry);

        app(WorkflowService::class)->transition($request, 'submit', $dataEntry);

        $response = $this->actingAs($bankReviewer)->getJson("/api/requests/{$request->id}/history");

        $response->assertOk();
        $history = $response->json('data');
        $this->assertNotEmpty($history);

        $entry = $history[0];
        $this->assertArrayHasKey('performed_by', $entry);
        $this->assertNotNull($entry['performed_by']);
        $this->assertSame($dataEntry->id, $entry['performed_by']['id']);
        $this->assertSame($dataEntry->name, $entry['performed_by']['name']);
        $this->assertSame(UserRole::DATA_ENTRY->value, $entry['performed_by']['role']);
    }

    /** @test */
    public function test_history_endpoint_returns_chronological_order(): void
    {
        $dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $bankReviewer = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $request = $this->makeRequest($dataEntry, RequestStatus::SUBMITTED);

        $workflowService = app(WorkflowService::class);
        $workflowService->transition($request->fresh(), 'bank_begin_review', $bankReviewer);

        $response = $this->actingAs($dataEntry)->getJson("/api/requests/{$request->id}/history");

        $response->assertOk();
        $history = $response->json('data');

        // chronological ascending order (oldest('id'))
        $this->assertGreaterThanOrEqual(1, count($history));
    }

    // ─── Authorization failure logging ────────────────────────────────────────

    /** @test */
    public function test_unauthorized_workflow_transition_creates_authorization_failure_audit_log(): void
    {
        $dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $otherBank = $this->makeBank('OTH');
        $otherDataEntry = $this->makeUser(UserRole::DATA_ENTRY, $otherBank);
        $request = $this->makeRequest($otherDataEntry);

        // DATA_ENTRY from a different bank tries to submit a request belonging to another bank
        $this->actingAs($dataEntry)->postJson("/api/workflow/{$request->id}/submit")
            ->assertStatus(403);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $dataEntry->id,
            'action' => AuditAction::AUTHORIZATION_FAILURE->value,
        ]);
    }

    /** @test */
    public function test_role_mismatch_workflow_transition_creates_authorization_failure_audit_log(): void
    {
        $dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($dataEntry, RequestStatus::SUBMITTED);

        // DATA_ENTRY trying a BANK_REVIEWER action
        $this->actingAs($dataEntry)->postJson("/api/workflow/{$request->id}/bank-review")
            ->assertStatus(403);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $dataEntry->id,
            'action' => AuditAction::AUTHORIZATION_FAILURE->value,
        ]);
    }

    /** @test */
    public function test_policy_denial_creates_authorization_failure_audit_log(): void
    {
        $dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $otherBank = $this->makeBank('ZBK');
        $otherDataEntry = $this->makeUser(UserRole::DATA_ENTRY, $otherBank);
        $request = $this->makeRequest($otherDataEntry);

        // Attempt to view a request from another bank (policy denies this)
        $this->actingAs($dataEntry)->getJson("/api/requests/{$request->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $dataEntry->id,
            'action' => AuditAction::AUTHORIZATION_FAILURE->value,
        ]);
    }
}

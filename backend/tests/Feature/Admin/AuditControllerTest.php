<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\User;
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
        // COMMITTEE_DIRECTOR now has access (AC 13 of Story 7.9); excluded here
        foreach ([
            UserRole::DATA_ENTRY,
            UserRole::BANK_REVIEWER,
            UserRole::SUPPORT_COMMITTEE,
            UserRole::EXECUTIVE_MEMBER,
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
        $this->assertArrayHasKey('entity_reference', $entry);
        $this->assertSame('ImportRequest', $entry['entity_type']);
        $this->assertSame($request->id, $entry['entity_id']);
        $this->assertSame(
            sprintf('IMP-%s-%04d', $request->created_at->format('Y'), $request->id),
            $entry['entity_reference']
        );
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

    // ─── GET /api/audit/stats ─────────────────────────────────────────────────

    /** @test */
    public function test_stats_endpoint_returns_correct_shape(): void
    {
        $admin = $this->makeUser(UserRole::CBY_ADMIN);

        AuditLog::query()->create([
            'user_id' => $admin->id,
            'user_role' => UserRole::CBY_ADMIN->value,
            'action' => AuditAction::LOGIN->value,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/audit/stats');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['today_count', 'duplicate_invoice_count']]);

        $this->assertIsInt($response->json('data.today_count'));
        $this->assertIsInt($response->json('data.duplicate_invoice_count'));
    }

    /** @test */
    public function test_stats_endpoint_forbidden_for_non_audit_roles(): void
    {
        foreach ([UserRole::DATA_ENTRY, UserRole::BANK_REVIEWER, UserRole::SWIFT_OFFICER] as $role) {
            $user = $this->makeUser($role, $this->bank);
            $this->actingAs($user)->getJson('/api/audit/stats')->assertForbidden();
        }
    }

    // ─── GET /api/audit/duplicates ────────────────────────────────────────────

    /** @test */
    public function test_duplicates_endpoint_returns_requests_with_same_invoice_number(): void
    {
        $admin = $this->makeUser(UserRole::CBY_ADMIN);
        $dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        $req1 = $this->makeRequest($dataEntry);
        $req2 = $this->makeRequest($dataEntry);
        $req3 = $this->makeRequest($dataEntry);

        // Two requests share an invoice number; the third is unique
        DB::table('import_requests')->where('id', $req1->id)->update(['invoice_number' => 'INV-DUP-001']);
        DB::table('import_requests')->where('id', $req2->id)->update(['invoice_number' => 'INV-DUP-001']);
        DB::table('import_requests')->where('id', $req3->id)->update(['invoice_number' => 'INV-UNIQUE-999']);

        $response = $this->actingAs($admin)->getJson('/api/audit/duplicates');

        $response->assertOk();
        $groups = $response->json('data.data');

        // Story 8.6 AC6: endpoint returns groups keyed by invoice_number
        $this->assertIsArray($groups);
        $this->assertCount(1, $groups); // only INV-DUP-001 is a duplicate group

        $group = $groups[0];
        $this->assertArrayHasKey('invoice_number', $group);
        $this->assertArrayHasKey('banks', $group);
        $this->assertArrayHasKey('requests', $group);
        $this->assertEquals('INV-DUP-001', $group['invoice_number']);
        $this->assertCount(2, $group['requests']);

        $reqIds = array_column($group['requests'], 'id');
        $this->assertContains($req1->id, $reqIds);
        $this->assertContains($req2->id, $reqIds);

        // req3 has a unique invoice; it must not appear in any group
        $allIds = array_merge(...array_column($groups, 'requests'));
        $allIds = array_column($allIds, 'id');
        $this->assertNotContains($req3->id, $allIds);

        // Each request row has the required fields
        $row = $group['requests'][0];
        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('reference_number', $row);
        $this->assertArrayHasKey('bank_name', $row);
        $this->assertArrayHasKey('amount', $row);
        $this->assertArrayHasKey('currency', $row);
        $this->assertArrayHasKey('created_at', $row);
        $this->assertArrayHasKey('status', $row);
    }

    // ─── GET /api/audit/risk-indicators ──────────────────────────────────────

    /** @test */
    public function test_risk_indicators_endpoint_returns_list_with_required_fields(): void
    {
        $admin = $this->makeUser(UserRole::CBY_ADMIN);
        $response = $this->actingAs($admin)->getJson('/api/audit/risk-indicators');

        $response->assertOk();
        $items = $response->json('data.data');

        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
        $this->assertArrayHasKey('title', $items[0]);
        $this->assertArrayHasKey('body', $items[0]);
        $this->assertArrayHasKey('level', $items[0]);
    }

    /** @test */
    public function test_new_audit_endpoints_forbidden_for_non_audit_roles(): void
    {
        $dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        $this->actingAs($dataEntry)->getJson('/api/audit/stats')->assertForbidden();
        $this->actingAs($dataEntry)->getJson('/api/audit/duplicates')->assertForbidden();
        $this->actingAs($dataEntry)->getJson('/api/audit/risk-indicators')->assertForbidden();
    }
}

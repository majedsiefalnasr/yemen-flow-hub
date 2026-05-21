<?php

namespace Tests\Feature\Requests;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\Merchant;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ImportRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;
    private Bank $otherBank;
    private User $dataEntry;
    private User $otherDataEntry;
    private User $supportReviewer;
    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seedPermissions();

        $this->bank = $this->makeBank('YCB');
        $this->otherBank = $this->makeBank('OTH');
        $this->dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->otherDataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);
        $this->supportReviewer = $this->makeUser(UserRole::SUPPORT_COMMITTEE);
        $this->merchant = $this->makeMerchant($this->bank);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function seedPermissions(): void
    {
        $permissionId = DB::table('permissions')->insertGetId([
            'slug' => 'request.create',
            'name_ar' => 'إنشاء طلب',
            'name_en' => 'Create request',
            'group' => 'requests',
        ]);

        DB::table('role_permissions')->insert([
            ['permission_id' => $permissionId, 'role' => UserRole::DATA_ENTRY->value],
            ['permission_id' => $permissionId, 'role' => UserRole::BANK_ADMIN->value],
        ]);
    }

    private function makeBank(string $code): Bank
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
            'email' => "user{$counter}@example.com",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }

    private function makeMerchant(Bank $bank): Merchant
    {
        static $merchantCounter = 0;
        $merchantCounter++;
        return Merchant::query()->create([
            'name' => "مورد تجريبي {$merchantCounter}",
            'tax_number' => "TX-{$merchantCounter}",
            'commercial_register' => "CR-{$merchantCounter}",
            'address' => 'صنعاء، اليمن',
            'contact' => '+9671234567',
            'category' => 'general',
            'status' => 'active',
            'bank_id' => $bank->id,
        ]);
    }

    private function makeRequest(Bank $bank, User $creator, RequestStatus $status = RequestStatus::DRAFT): ImportRequest
    {
        app()->instance('workflow.transition.active', true);
        try {
            return ImportRequest::query()->create([
                'bank_id' => $bank->id,
                'merchant_id' => $this->makeMerchant($bank)->id,
                'created_by' => $creator->id,
                'currency' => 'USD',
                'amount' => 10000.00,
                'supplier_name' => 'Supplier Co.',
                'goods_description' => 'Industrial equipment',
                'port_of_entry' => 'Aden Port',
                'notes' => null,
                'status' => $status,
                'current_owner_role' => UserRole::DATA_ENTRY,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    private function validPayload(): array
    {
        return [
            'merchant_id' => $this->merchant->id,
            'currency' => 'USD',
            'amount' => 50000,
            'supplier_name' => 'Global Imports Ltd.',
            'goods_description' => 'Medical equipment',
            'port_of_entry' => 'Hodeidah Port',
            'notes' => 'Urgent shipment',
        ];
    }

    // ─── AC-1: POST /api/requests ──────────────────────────────────────────────

    public function test_data_entry_can_create_draft_request(): void
    {
        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/requests', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', RequestStatus::DRAFT->value)
            ->assertJsonStructure(['data' => ['id', 'reference_number', 'status']]);

        $this->assertDatabaseHas('import_requests', [
            'bank_id' => $this->bank->id,
            'created_by' => $this->dataEntry->id,
            'status' => RequestStatus::DRAFT->value,
            'supplier_name' => 'Global Imports Ltd.',
        ]);
    }

    public function test_created_request_has_auto_generated_reference_number(): void
    {
        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/requests', $this->validPayload());

        $response->assertStatus(201);
        $referenceNumber = $response->json('data.reference_number');
        $year = now()->format('Y');
        $this->assertMatchesRegularExpression("/^YFH-{$year}-\d{6}$/", $referenceNumber);
    }

    public function test_created_request_sets_bank_id_from_user(): void
    {
        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/requests', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.bank_id', $this->bank->id);
    }

    public function test_create_response_includes_created_by_user(): void
    {
        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/requests', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.created_by', $this->dataEntry->id)
            ->assertJsonPath('data.created_by_user.id', $this->dataEntry->id)
            ->assertJsonPath('data.created_by_user.name', $this->dataEntry->name);
    }

    public function test_unauthenticated_user_cannot_create_request(): void
    {
        $this->postJson('/api/requests', $this->validPayload())
            ->assertUnauthorized();
    }

    // ─── AC-2: PUT /api/requests/{id} — editable states ───────────────────────

    public function test_data_entry_can_update_own_bank_draft_request(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry);

        $response = $this->actingAs($this->dataEntry)
            ->putJson("/api/requests/{$request->id}", array_merge($this->validPayload(), ['supplier_name' => 'Updated Supplier']));

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('import_requests', [
            'id' => $request->id,
            'supplier_name' => 'Updated Supplier',
            'last_updated_by' => $this->dataEntry->id,
        ]);
    }

    public function test_update_response_includes_last_updated_by_user(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry);

        $response = $this->actingAs($this->dataEntry)
            ->putJson("/api/requests/{$request->id}", $this->validPayload());

        $response->assertOk()
            ->assertJsonPath('data.last_updated_by', $this->dataEntry->id)
            ->assertJsonPath('data.last_updated_by_user.id', $this->dataEntry->id)
            ->assertJsonPath('data.last_updated_by_user.name', $this->dataEntry->name);
    }

    public function test_any_bank_data_entry_can_update_bank_owned_draft(): void
    {
        $anotherDataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($this->bank, $this->dataEntry);

        $response = $this->actingAs($anotherDataEntry)
            ->putJson("/api/requests/{$request->id}", $this->validPayload());

        $response->assertOk();
        $this->assertDatabaseHas('import_requests', [
            'id' => $request->id,
            'last_updated_by' => $anotherDataEntry->id,
        ]);
    }

    public function test_update_sets_last_updated_by_to_current_user(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry);

        $this->actingAs($this->dataEntry)
            ->putJson("/api/requests/{$request->id}", $this->validPayload());

        $this->assertDatabaseHas('import_requests', [
            'id' => $request->id,
            'last_updated_by' => $this->dataEntry->id,
        ]);
    }

    public function test_data_entry_can_update_draft_rejected_internal_request(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::DRAFT_REJECTED_INTERNAL);

        $response = $this->actingAs($this->dataEntry)
            ->putJson("/api/requests/{$request->id}", $this->validPayload());

        $response->assertOk();
    }

    // ─── AC-3: WORKFLOW_LOCKED_STATE on non-editable ──────────────────────────

    public function test_update_returns_422_locked_state_when_submitted(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::SUBMITTED);

        $response = $this->actingAs($this->dataEntry)
            ->putJson("/api/requests/{$request->id}", $this->validPayload());

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'WORKFLOW_LOCKED_STATE');
    }

    public function test_update_returns_422_locked_state_when_bank_approved(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_APPROVED);

        $response = $this->actingAs($this->dataEntry)
            ->putJson("/api/requests/{$request->id}", $this->validPayload());

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'WORKFLOW_LOCKED_STATE');
    }

    // ─── AC-4: DELETE /api/requests/{id} ─────────────────────────────────────

    public function test_data_entry_can_delete_draft_request(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry);

        $response = $this->actingAs($this->dataEntry)
            ->deleteJson("/api/requests/{$request->id}");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSoftDeleted('import_requests', ['id' => $request->id]);
    }

    public function test_delete_returns_422_locked_state_when_submitted(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::SUBMITTED);

        $response = $this->actingAs($this->dataEntry)
            ->deleteJson("/api/requests/{$request->id}");

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'WORKFLOW_LOCKED_STATE');
    }

    public function test_delete_returns_422_locked_state_when_bank_review(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);

        $response = $this->actingAs($this->dataEntry)
            ->deleteJson("/api/requests/{$request->id}");

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'WORKFLOW_LOCKED_STATE');
    }

    // ─── AC-5: WORKFLOW_IMMUTABLE_STATE on terminal statuses ──────────────────

    public function test_update_returns_403_immutable_state_when_executive_rejected(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::EXECUTIVE_REJECTED);

        $response = $this->actingAs($this->dataEntry)
            ->putJson("/api/requests/{$request->id}", $this->validPayload());

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'WORKFLOW_IMMUTABLE_STATE')
            ->assertJsonPath('current_status', RequestStatus::EXECUTIVE_REJECTED->value);
    }

    public function test_update_returns_403_immutable_state_when_customs_declaration_issued(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::CUSTOMS_DECLARATION_ISSUED);

        $response = $this->actingAs($this->dataEntry)
            ->putJson("/api/requests/{$request->id}", $this->validPayload());

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'WORKFLOW_IMMUTABLE_STATE')
            ->assertJsonPath('current_status', RequestStatus::CUSTOMS_DECLARATION_ISSUED->value);
    }

    public function test_update_returns_403_immutable_state_when_completed(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::COMPLETED);

        $response = $this->actingAs($this->dataEntry)
            ->putJson("/api/requests/{$request->id}", $this->validPayload());

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'WORKFLOW_IMMUTABLE_STATE')
            ->assertJsonPath('current_status', RequestStatus::COMPLETED->value);
    }

    public function test_delete_returns_403_immutable_state_when_executive_rejected(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::EXECUTIVE_REJECTED);

        $response = $this->actingAs($this->dataEntry)
            ->deleteJson("/api/requests/{$request->id}");

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'WORKFLOW_IMMUTABLE_STATE')
            ->assertJsonPath('current_status', RequestStatus::EXECUTIVE_REJECTED->value);
    }

    public function test_delete_returns_403_immutable_state_when_completed(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::COMPLETED);

        $response = $this->actingAs($this->dataEntry)
            ->deleteJson("/api/requests/{$request->id}");

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'WORKFLOW_IMMUTABLE_STATE')
            ->assertJsonPath('current_status', RequestStatus::COMPLETED->value);
    }

    public function test_delete_returns_403_immutable_state_when_customs_declaration_issued(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::CUSTOMS_DECLARATION_ISSUED);

        $response = $this->actingAs($this->dataEntry)
            ->deleteJson("/api/requests/{$request->id}");

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'WORKFLOW_IMMUTABLE_STATE')
            ->assertJsonPath('current_status', RequestStatus::CUSTOMS_DECLARATION_ISSUED->value);
    }

    // ─── Role enforcement ─────────────────────────────────────────────────────

    public function test_non_data_entry_same_bank_user_cannot_update_draft(): void
    {
        $bankReviewer = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $request = $this->makeRequest($this->bank, $this->dataEntry);

        $response = $this->actingAs($bankReviewer)
            ->putJson("/api/requests/{$request->id}", $this->validPayload());

        $response->assertStatus(403);
    }

    // ─── Org-scoping ──────────────────────────────────────────────────────────

    public function test_data_entry_cannot_update_request_from_other_bank(): void
    {
        $request = $this->makeRequest($this->otherBank, $this->otherDataEntry);

        $response = $this->actingAs($this->dataEntry)
            ->putJson("/api/requests/{$request->id}", $this->validPayload());

        $response->assertStatus(403);
    }

    public function test_data_entry_cannot_delete_request_from_other_bank(): void
    {
        $request = $this->makeRequest($this->otherBank, $this->otherDataEntry);

        $response = $this->actingAs($this->dataEntry)
            ->deleteJson("/api/requests/{$request->id}");

        $response->assertStatus(403);
    }

    public function test_index_returns_only_own_bank_requests(): void
    {
        $this->makeRequest($this->bank, $this->dataEntry);
        $this->makeRequest($this->otherBank, $this->otherDataEntry);

        $response = $this->actingAs($this->dataEntry)
            ->getJson('/api/requests');

        $response->assertOk();
        // Paginated resource collection is wrapped: data.data[] contains the items
        $items = $response->json('data.data') ?? $response->json('data');
        $bankIds = collect($items)->pluck('bank_id')->unique()->values()->all();
        $this->assertNotEmpty($bankIds, 'Expected at least one request in response');
        $this->assertEquals([$this->bank->id], $bankIds);
    }

    // ─── AC-6: List resource fields for parity (Story 7.3) ───────────────────

    public function test_index_list_resource_includes_merchant_goods_type_invoice_number(): void
    {
        app()->instance('workflow.transition.active', true);
        try {
            $req = ImportRequest::query()->create([
                'bank_id' => $this->bank->id,
                'merchant_id' => $this->merchant->id,
                'created_by' => $this->dataEntry->id,
                'currency' => 'USD',
                'amount' => 10000.00,
                'supplier_name' => 'Parity Supplier',
                'goods_description' => 'Medical goods',
                'port_of_entry' => 'Aden Port',
                'goods_type' => 'أجهزة طبية',
                'invoice_number' => 'INV-2026-001',
                'status' => RequestStatus::DRAFT,
                'current_owner_role' => UserRole::DATA_ENTRY,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }

        $response = $this->actingAs($this->dataEntry)->getJson('/api/requests');

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        $item = collect($items)->firstWhere('id', $req->id);

        $this->assertNotNull($item, 'Request must appear in list');
        $this->assertArrayHasKey('merchant', $item);
        $this->assertArrayHasKey('goods_type', $item);
        $this->assertArrayHasKey('invoice_number', $item);
        $this->assertEquals('أجهزة طبية', $item['goods_type']);
        $this->assertEquals('INV-2026-001', $item['invoice_number']);
        $this->assertNotNull($item['merchant']);
        $this->assertEquals($this->merchant->id, $item['merchant']['id']);
    }

    public function test_index_list_resource_merchant_null_when_no_merchant(): void
    {
        app()->instance('workflow.transition.active', true);
        try {
            $req = ImportRequest::query()->create([
                'bank_id' => $this->bank->id,
                'merchant_id' => null,
                'created_by' => $this->dataEntry->id,
                'currency' => 'USD',
                'amount' => 5000.00,
                'supplier_name' => 'Direct Supplier',
                'goods_description' => 'Electronics',
                'port_of_entry' => 'Hodeidah',
                'status' => RequestStatus::DRAFT,
                'current_owner_role' => UserRole::DATA_ENTRY,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }

        $response = $this->actingAs($this->dataEntry)->getJson('/api/requests');

        $response->assertOk();
        $items = $response->json('data.data') ?? $response->json('data');
        $item = collect($items)->firstWhere('id', $req->id);

        $this->assertNotNull($item, 'Request must appear in list');
        $this->assertNull($item['merchant']);
        $this->assertNull($item['goods_type']);
        $this->assertNull($item['invoice_number']);
    }

    public function test_index_applies_currency_filter_and_searches_invoice_and_merchant(): void
    {
        $matching = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::SUBMITTED);
        $matching->update([
            'currency' => 'EUR',
            'invoice_number' => 'INV-SEARCH-001',
            'supplier_name' => 'Fallback Supplier',
            'merchant_id' => $this->merchant->id,
        ]);

        $other = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::SUBMITTED);
        $other->update([
            'currency' => 'USD',
            'invoice_number' => 'INV-OTHER-002',
            'supplier_name' => 'Another Supplier',
        ]);

        $response = $this->actingAs($this->dataEntry)
            ->getJson('/api/requests?currency=EUR&search=INV-SEARCH-001');

        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $matching->id);

        $merchantSearchResponse = $this->actingAs($this->dataEntry)
            ->getJson('/api/requests?search='.urlencode($this->merchant->name));

        $merchantSearchResponse->assertOk();
        $ids = collect($merchantSearchResponse->json('data.data'))->pluck('id')->all();

        $this->assertContains($matching->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_index_returns_status_totals_for_filtered_scope(): void
    {
        $draft = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::DRAFT);
        $submitted = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::SUBMITTED);
        $this->makeRequest($this->otherBank, $this->otherDataEntry, RequestStatus::COMPLETED);

        $response = $this->actingAs($this->dataEntry)->getJson('/api/requests');

        $response->assertOk()
            ->assertJsonPath('data.meta.status_totals.DRAFT', 1)
            ->assertJsonPath('data.meta.status_totals.SUBMITTED', 1);

        $this->assertNull($response->json('data.meta.status_totals.COMPLETED'));
        $this->assertNotNull($draft);
        $this->assertNotNull($submitted);
    }

    // ─── AC-7: Actor name objects in show response (Story 7.4) ────────────────

    public function test_show_includes_created_by_user_name(): void
    {
        $req = $this->makeRequest($this->bank, $this->dataEntry);

        $response = $this->actingAs($this->dataEntry)
            ->getJson("/api/requests/{$req->id}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('created_by_user', $data);
        $this->assertNotNull($data['created_by_user']);
        $this->assertEquals($this->dataEntry->id, $data['created_by_user']['id']);
        $this->assertEquals($this->dataEntry->name, $data['created_by_user']['name']);
    }

    public function test_show_submitted_by_user_null_when_not_yet_submitted(): void
    {
        $req = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::DRAFT);

        $response = $this->actingAs($this->dataEntry)
            ->getJson("/api/requests/{$req->id}");

        $response->assertOk();
        // submitted_by is null on a DRAFT, so submitted_by_user must also be null
        $this->assertNull($response->json('data.submitted_by'));
        $this->assertNull($response->json('data.submitted_by_user'));
    }

    public function test_show_includes_support_reviewed_by_user_name(): void
    {
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::WAITING_FOR_SWIFT);

        app()->instance('workflow.transition.active', true);
        try {
            $request->forceFill([
                'support_reviewed_by' => $this->supportReviewer->id,
            ])->save();
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }

        $response = $this->actingAs($this->dataEntry)
            ->getJson("/api/requests/{$request->id}");

        $response->assertOk()
            ->assertJsonPath('data.support_reviewed_by', $this->supportReviewer->id)
            ->assertJsonPath('data.support_reviewed_by_user.id', $this->supportReviewer->id)
            ->assertJsonPath('data.support_reviewed_by_user.name', $this->supportReviewer->name)
            ->assertJsonPath('data.support_reviewer.id', $this->supportReviewer->id);
    }

    public function test_show_includes_all_actor_user_keys(): void
    {
        $req = $this->makeRequest($this->bank, $this->dataEntry);

        $response = $this->actingAs($this->dataEntry)
            ->getJson("/api/requests/{$req->id}");

        $response->assertOk();
        $keys = [
            'created_by_user',
            'last_updated_by_user',
            'submitted_by_user',
            'reviewed_by_user',
            'approved_by_user',
            'rejected_by_user',
            'resubmitted_by_user',
            'support_reviewed_by_user',
            'swift_uploaded_by_user',
            'internal_reviewer',
            'support_reviewer',
            'support_claimed_by',
        ];
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $response->json('data'), "Missing key: {$key}");
        }
    }

    public function test_show_actor_user_fields_are_null_when_actions_not_yet_taken(): void
    {
        $req = $this->makeRequest($this->bank, $this->dataEntry);

        $response = $this->actingAs($this->dataEntry)
            ->getJson("/api/requests/{$req->id}");

        $response->assertOk();
        $nullableActors = [
            'last_updated_by_user',
            'submitted_by_user',
            'reviewed_by_user',
            'approved_by_user',
            'rejected_by_user',
            'resubmitted_by_user',
            'support_reviewed_by_user',
            'swift_uploaded_by_user',
        ];
        foreach ($nullableActors as $key) {
            $this->assertNull($response->json("data.{$key}"), "{$key} should be null on a fresh DRAFT");
        }
    }

    // ─── Clone endpoint: POST /api/requests/{id}/clone ────────────────────────

    /** @dataProvider terminalRejectedStatuses */
    public function test_data_entry_can_clone_terminal_rejected_request(RequestStatus $status): void
    {
        $source = $this->makeRequest($this->bank, $this->dataEntry, $status);

        $response = $this->actingAs($this->dataEntry)
            ->postJson("/api/requests/{$source->id}/clone");

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', RequestStatus::DRAFT->value);

        $cloned = ImportRequest::find($response->json('data.id'));
        $this->assertNotNull($cloned);
        $this->assertNotEquals($source->id, $cloned->id);
        $this->assertNotEquals($source->reference_number, $cloned->reference_number);
    }

    public static function terminalRejectedStatuses(): array
    {
        return [
            'BANK_REJECTED' => [RequestStatus::BANK_REJECTED],
            'SUPPORT_REJECTED' => [RequestStatus::SUPPORT_REJECTED],
            'EXECUTIVE_REJECTED' => [RequestStatus::EXECUTIVE_REJECTED],
        ];
    }

    public function test_clone_copies_wizard_fields_correctly(): void
    {
        $source = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::EXECUTIVE_REJECTED);
        $source->forceFill([
            'supplier_name' => 'Clone Supplier',
            'goods_description' => 'Clone goods',
            'port_of_entry' => 'Clone Port',
            'currency' => 'EUR',
            'amount' => 99999.99,
            'notes' => 'Clone notes',
            'goods_type' => 'Machinery',
            'payment_terms' => 'LC',
            'invoice_number' => 'INV-999',
            'origin_country' => 'Germany',
            'arrival_port' => 'Aden',
            'shipping_port' => 'Hamburg',
            'customs_office' => 'Aden Customs',
            'bl_number' => 'BL-999',
        ])->saveQuietly();

        $response = $this->actingAs($this->dataEntry)
            ->postJson("/api/requests/{$source->id}/clone");

        $response->assertStatus(201);
        $clonedId = $response->json('data.id');
        $cloned = ImportRequest::find($clonedId);

        $this->assertEquals('Clone Supplier', $cloned->supplier_name);
        $this->assertEquals('Clone goods', $cloned->goods_description);
        $this->assertEquals('Clone Port', $cloned->port_of_entry);
        $this->assertEquals('EUR', $cloned->currency->value);
        $this->assertEquals('INV-999', $cloned->invoice_number);
        $this->assertEquals('Germany', $cloned->origin_country);
    }

    public function test_clone_does_not_copy_documents(): void
    {
        $source = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REJECTED);

        $response = $this->actingAs($this->dataEntry)
            ->postJson("/api/requests/{$source->id}/clone");

        $response->assertStatus(201);
        $clonedId = $response->json('data.id');

        $this->assertDatabaseMissing('request_documents', ['request_id' => $clonedId]);
    }

    public function test_clone_increments_revision_count(): void
    {
        $source = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REJECTED);
        $source->forceFill(['revision_count' => 3])->saveQuietly();

        $response = $this->actingAs($this->dataEntry)
            ->postJson("/api/requests/{$source->id}/clone");

        $response->assertStatus(201);
        $cloned = ImportRequest::find($response->json('data.id'));
        $this->assertEquals(4, $cloned->revision_count);
    }

    public function test_clone_creates_fresh_stage_history_entry(): void
    {
        $source = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REJECTED);

        $response = $this->actingAs($this->dataEntry)
            ->postJson("/api/requests/{$source->id}/clone");

        $response->assertStatus(201);
        $clonedId = $response->json('data.id');

        $this->assertDatabaseHas('request_stage_history', [
            'request_id' => $clonedId,
            'action' => 'create',
        ]);

        $historyCount = \App\Models\RequestStageHistory::where('request_id', $clonedId)->count();
        $this->assertEquals(1, $historyCount);
    }

    public function test_clone_writes_audit_log_with_cloned_from(): void
    {
        $source = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::SUPPORT_REJECTED);

        $response = $this->actingAs($this->dataEntry)
            ->postJson("/api/requests/{$source->id}/clone");

        $response->assertStatus(201);
        $clonedId = $response->json('data.id');

        $audit = \App\Models\AuditLog::where('action', 'REQUEST_CREATED')
            ->where('subject_id', $clonedId)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('import_request', $audit->subject_type);
        $this->assertEquals($source->id, $audit->metadata['cloned_from']);
        $this->assertEquals($source->reference_number, $audit->metadata['source_reference_number']);
    }

    public function test_clone_rejects_non_terminal_source(): void
    {
        $source = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::DRAFT);

        $this->actingAs($this->dataEntry)
            ->postJson("/api/requests/{$source->id}/clone")
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'WORKFLOW_FORBIDDEN');
    }

    public function test_clone_rejects_cross_bank_actor(): void
    {
        $source = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REJECTED);

        $this->actingAs($this->otherDataEntry)
            ->postJson("/api/requests/{$source->id}/clone")
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'WORKFLOW_FORBIDDEN');
    }

    public function test_clone_rejects_wrong_role(): void
    {
        $source = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REJECTED);

        $this->actingAs($this->supportReviewer)
            ->postJson("/api/requests/{$source->id}/clone")
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'WORKFLOW_FORBIDDEN');
    }

    public function test_clone_rejects_inactive_user(): void
    {
        $source = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REJECTED);
        $this->dataEntry->forceFill(['is_active' => false])->saveQuietly();

        $this->actingAs($this->dataEntry)
            ->postJson("/api/requests/{$source->id}/clone")
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'WORKFLOW_FORBIDDEN');
    }

    public function test_bank_admin_can_clone_terminal_rejected_request(): void
    {
        $bankAdmin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);
        $source = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::EXECUTIVE_REJECTED);

        $response = $this->actingAs($bankAdmin)
            ->postJson("/api/requests/{$source->id}/clone");

        $response->assertStatus(201)
            ->assertJsonPath('data.status', RequestStatus::DRAFT->value);
    }

    public function test_clone_sets_created_by_to_actor(): void
    {
        $source = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REJECTED);

        $response = $this->actingAs($this->dataEntry)
            ->postJson("/api/requests/{$source->id}/clone");

        $response->assertStatus(201);
        $this->assertEquals($this->dataEntry->id, $response->json('data.created_by'));
    }

    public function test_clone_generates_unique_reference_number(): void
    {
        $source = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REJECTED);

        $response = $this->actingAs($this->dataEntry)
            ->postJson("/api/requests/{$source->id}/clone");

        $response->assertStatus(201);
        $year = now()->format('Y');
        $refNum = $response->json('data.reference_number');
        $this->assertMatchesRegularExpression("/^YFH-{$year}-\d{6}$/", $refNum);
        $this->assertNotEquals($source->reference_number, $refNum);
    }
}

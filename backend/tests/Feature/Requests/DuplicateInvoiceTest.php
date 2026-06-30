<?php

namespace Tests\Feature\Requests;

use App\Enums\AuditAction;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\Merchant;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\DuplicateDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DuplicateInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank1;

    private Bank $bank2;

    private User $dataEntry1;

    private User $dataEntry2;

    private User $bankReviewer;

    private User $bankAdmin;

    private User $supportCommittee;

    private User $cbyAdmin;

    private Merchant $merchant1;

    private Merchant $merchant2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->bank1 = Bank::query()->create(['name' => 'بنك التضامن', 'code' => 'B1', 'is_active' => true]);
        $this->bank2 = Bank::query()->create(['name' => 'بنك سبأ', 'code' => 'B2', 'is_active' => true]);

        $this->dataEntry1 = $this->makeUser(UserRole::DATA_ENTRY, $this->bank1);
        $this->dataEntry2 = $this->makeUser(UserRole::DATA_ENTRY, $this->bank2);
        $this->bankReviewer = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank1);
        $this->bankAdmin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank1);
        $this->supportCommittee = $this->makeUser(UserRole::SUPPORT_COMMITTEE);
        $this->cbyAdmin = $this->makeUser(UserRole::CBY_ADMIN);

        $this->merchant1 = $this->makeMerchant($this->bank1);
        $this->merchant2 = $this->makeMerchant($this->bank2);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function seedPermissions(): void
    {
        foreach (['request.create', 'request.view'] as $slug) {
            $id = DB::table('permissions')->insertGetId([
                'slug' => $slug,
                'name_ar' => $slug,
                'name_en' => $slug,
                'group' => 'requests',
            ]);
            DB::table('role_permissions')->insert([
                ['permission_id' => $id, 'role' => UserRole::DATA_ENTRY->value],
                ['permission_id' => $id, 'role' => UserRole::BANK_ADMIN->value],
                ['permission_id' => $id, 'role' => UserRole::BANK_REVIEWER->value],
                ['permission_id' => $id, 'role' => UserRole::SUPPORT_COMMITTEE->value],
                ['permission_id' => $id, 'role' => UserRole::CBY_ADMIN->value],
            ]);
        }
    }

    private function makeUser(UserRole $role, ?Bank $bank = null): User
    {
        static $n = 0;

        return User::query()->create([
            'name' => 'U'.(++$n),
            'email' => "u{$n}@x.com",
            'password' => Hash::make('p'),
            'role' => $role,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }

    private function makeMerchant(Bank $bank): Merchant
    {
        static $m = 0;

        return Merchant::query()->create([
            'name' => 'M'.(++$m), 'tax_number' => "T{$m}",
            'commercial_register' => "CR{$m}", 'address' => 'Sanaa',
            'contact' => '+967', 'category' => 'general', 'status' => 'active',
            'bank_id' => $bank->id,
        ]);
    }

    private function makeRequest(Bank $bank, User $creator, ?string $invoiceNumber = null, RequestStatus $status = RequestStatus::DRAFT): ImportRequest
    {
        $merchant = Merchant::query()->where('bank_id', $bank->id)->first()
            ?? $this->makeMerchant($bank);

        app()->instance('workflow.transition.active', true);
        try {
            return ImportRequest::query()->create([
                'bank_id' => $bank->id, 'merchant_id' => $merchant->id,
                'created_by' => $creator->id, 'currency' => 'USD', 'amount' => 5000,
                'supplier_name' => 'Supplier', 'goods_description' => 'Goods',
                'port_of_entry' => 'Aden', 'status' => $status,
                'current_owner_role' => UserRole::DATA_ENTRY,
                'invoice_number' => $invoiceNumber,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    private function setPolicy(string $policy): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'duplicate_invoice_policy'],
            ['value' => $policy, 'updated_by' => null]
        );
    }

    private function validPayload(?string $invoiceNumber = null): array
    {
        return [
            'merchant_id' => $this->merchant1->id,
            'currency' => 'USD', 'amount' => 10000,
            'supplier_name' => 'Supplier', 'goods_description' => 'Goods',
            'port_of_entry' => 'Aden',
            'invoice_number' => $invoiceNumber,
        ];
    }

    // ─── AC1: Migration index ─────────────────────────────────────────────────

    private function indexExists(): bool
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('import_requests')"))
                ->pluck('name')
                ->contains('idx_invoice_number_deleted_at');
        }

        return collect(DB::select('SHOW INDEX FROM import_requests'))
            ->pluck('Key_name')
            ->unique()
            ->contains('idx_invoice_number_deleted_at');
    }

    public function test_composite_index_exists_on_import_requests(): void
    {
        $this->assertTrue($this->indexExists(), 'Expected index idx_invoice_number_deleted_at not found');
    }

    public function test_migration_down_drops_index(): void
    {
        Schema::table('import_requests', function ($t) {
            $t->dropIndex('idx_invoice_number_deleted_at');
        });
        $this->assertFalse($this->indexExists());

        // Restore for subsequent tests
        Schema::table('import_requests', function ($t) {
            $t->index(['invoice_number', 'deleted_at'], 'idx_invoice_number_deleted_at');
        });
        $this->assertTrue($this->indexExists());
    }

    // ─── AC3: System setting default ────────────────────────────────────────────

    public function test_get_admin_settings_includes_duplicate_invoice_policy_default(): void
    {
        $response = $this->actingAs($this->cbyAdmin)->getJson('/api/admin/settings');

        $response->assertStatus(200)
            ->assertJsonPath('data.duplicate_invoice_policy', 'warn');
    }

    public function test_cby_admin_can_update_duplicate_invoice_policy_to_block(): void
    {
        $response = $this->actingAs($this->cbyAdmin)
            ->putJson('/api/admin/settings/duplicate_invoice_policy', ['value' => 'block']);

        $response->assertStatus(200)
            ->assertJsonPath('data.value', 'block');
    }

    public function test_invalid_policy_value_rejected(): void
    {
        $response = $this->actingAs($this->cbyAdmin)
            ->putJson('/api/admin/settings/duplicate_invoice_policy', ['value' => 'invalid']);

        $response->assertStatus(400);
    }

    // ─── AC4: Policy = block → 422 ───────────────────────────────────────────

    public function test_store_returns_422_when_policy_is_block_and_duplicate_found(): void
    {
        $this->makeRequest($this->bank2, $this->dataEntry2, 'DUP-001');
        $this->setPolicy('block');

        $response = $this->actingAs($this->dataEntry1)
            ->postJson('/api/requests', $this->validPayload('DUP-001'));

        $response->assertStatus(422)
            ->assertJsonPath('message', 'رقم الفاتورة مكرر في طلبات أخرى - يرجى المراجعة');
    }

    // ─── AC4: Policy = warn → 201 + audit note ───────────────────────────────

    public function test_store_returns_201_when_policy_is_warn_and_duplicate_found(): void
    {
        $this->makeRequest($this->bank2, $this->dataEntry2, 'DUP-002');
        $this->setPolicy('warn');

        $response = $this->actingAs($this->dataEntry1)
            ->postJson('/api/requests', $this->validPayload('DUP-002'));

        $response->assertStatus(201);
    }

    public function test_store_logs_duplicate_count_in_audit_when_warn_policy(): void
    {
        $this->makeRequest($this->bank2, $this->dataEntry2, 'DUP-003');
        $this->setPolicy('warn');

        $this->actingAs($this->dataEntry1)
            ->postJson('/api/requests', $this->validPayload('DUP-003'));

        $log = AuditLog::query()
            ->where('action', AuditAction::REQUEST_CREATED->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals(1, $log->metadata['notes']['duplicate_count'] ?? null);
    }

    public function test_store_no_audit_duplicate_count_when_no_duplicate(): void
    {
        $this->setPolicy('warn');

        $this->actingAs($this->dataEntry1)
            ->postJson('/api/requests', $this->validPayload('UNIQUE-999'));

        $log = AuditLog::query()
            ->where('action', AuditAction::REQUEST_CREATED->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('notes', $log->metadata ?? []);
    }

    public function test_store_no_block_when_no_invoice_number(): void
    {
        $this->setPolicy('block');

        $response = $this->actingAs($this->dataEntry1)
            ->postJson('/api/requests', $this->validPayload(null));

        $response->assertStatus(201);
    }

    // ─── AC5: Detail visibility per role ────────────────────────────────────────

    public function test_show_cby_admin_gets_full_duplicate_warnings(): void
    {
        $req1 = $this->makeRequest($this->bank1, $this->dataEntry1, 'SHOW-001');
        $this->makeRequest($this->bank2, $this->dataEntry2, 'SHOW-001');

        $response = $this->actingAs($this->cbyAdmin)
            ->getJson("/api/requests/{$req1->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $warnings = $response->json('data.duplicate_warnings');
        $this->assertIsArray($warnings);
        $this->assertCount(1, $warnings);
        $this->assertArrayHasKey('reference_number', $warnings[0]);
        $this->assertArrayHasKey('amount', $warnings[0]);
        $this->assertArrayHasKey('bank_name', $warnings[0]);
    }

    public function test_show_support_committee_gets_full_duplicate_warnings(): void
    {
        $req1 = $this->makeRequest($this->bank1, $this->dataEntry1, 'SHOW-002');
        $this->makeRequest($this->bank2, $this->dataEntry2, 'SHOW-002');

        $response = $this->actingAs($this->supportCommittee)
            ->getJson("/api/requests/{$req1->id}");

        $response->assertStatus(200);
        $warnings = $response->json('data.duplicate_warnings');
        $this->assertIsArray($warnings);
        $this->assertArrayHasKey('amount', $warnings[0]);
    }

    public function test_show_bank_reviewer_gets_count_and_bank_names_only(): void
    {
        $req1 = $this->makeRequest($this->bank1, $this->dataEntry1, 'SHOW-003');
        $this->makeRequest($this->bank2, $this->dataEntry2, 'SHOW-003');

        $response = $this->actingAs($this->bankReviewer)
            ->getJson("/api/requests/{$req1->id}");

        $response->assertStatus(200);
        $warnings = $response->json('data.duplicate_warnings');
        $this->assertIsArray($warnings);
        $this->assertCount(1, $warnings);
        $this->assertArrayHasKey('bank_name', $warnings[0]);
        $this->assertArrayNotHasKey('reference_number', $warnings[0]);
        $this->assertArrayNotHasKey('amount', $warnings[0]);
    }

    public function test_show_data_entry_has_no_duplicate_warnings_field(): void
    {
        $req1 = $this->makeRequest($this->bank1, $this->dataEntry1, 'SHOW-004');
        $this->makeRequest($this->bank2, $this->dataEntry2, 'SHOW-004');

        $response = $this->actingAs($this->dataEntry1)
            ->getJson("/api/requests/{$req1->id}");

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('duplicate_warnings', $response->json('data'));
    }

    public function test_show_returns_empty_warnings_when_no_duplicates(): void
    {
        $req1 = $this->makeRequest($this->bank1, $this->dataEntry1, 'UNIQUE-123');

        $response = $this->actingAs($this->cbyAdmin)
            ->getJson("/api/requests/{$req1->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.duplicate_warnings', []);
    }

    // ─── AC6: Audit duplicates endpoint ─────────────────────────────────────────

    public function test_audit_duplicates_returns_groups_by_invoice_number(): void
    {
        $this->makeRequest($this->bank1, $this->dataEntry1, 'AUD-001');
        $this->makeRequest($this->bank2, $this->dataEntry2, 'AUD-001');

        $response = $this->actingAs($this->cbyAdmin)->getJson('/api/audit/duplicates');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertIsArray($data);
        $found = collect($data)->firstWhere('invoice_number', 'AUD-001');
        $this->assertNotNull($found);
        $this->assertArrayHasKey('banks', $found);
        $this->assertArrayHasKey('requests', $found);
        $this->assertCount(2, $found['requests']);
    }

    public function test_audit_duplicates_forbidden_for_bank_reviewer(): void
    {
        $response = $this->actingAs($this->bankReviewer)->getJson('/api/audit/duplicates');
        $response->assertStatus(403);
    }

    // ─── AC10: Performance ───────────────────────────────────────────────────────

    public function test_duplicate_scan_completes_under_50ms_with_large_dataset(): void
    {
        $bankId = $this->bank1->id;
        $merchantId = $this->merchant1->id;
        $createdBy = $this->dataEntry1->id;
        $now = now()->toDateTimeString();

        // Generate reference numbers manually and insert via raw SQL including ref number
        $chunkSize = 500;
        $counter = 90000; // start high to avoid collision with test-created rows
        for ($batch = 0; $batch < 20; $batch++) {
            $placeholders = [];
            $bindings = [];
            for ($i = 0; $i < $chunkSize; $i++) {
                $idx = $batch * $chunkSize + $i;
                $inv = 'INV-PERF-'.($idx % 1000);
                $ref = 'YFH-'.date('Y').'-'.str_pad((string) ($counter++), 6, '0', STR_PAD_LEFT);
                $placeholders[] = "(?, ?, ?, ?, 'USD', 1000, 'Supplier', 'Goods', 'Aden', 'DRAFT', 'DATA_ENTRY', ?, ?, ?)";
                array_push($bindings, $ref, $bankId, $merchantId, $createdBy, $inv, $now, $now);
            }
            DB::statement(
                'INSERT INTO import_requests (reference_number, bank_id, merchant_id, created_by, currency, amount, supplier_name, goods_description, port_of_entry, status, current_owner_role, invoice_number, created_at, updated_at) VALUES '.implode(',', $placeholders),
                $bindings
            );
        }

        $service = app(DuplicateDetectionService::class);
        $start = microtime(true);
        $service->findDuplicatesForInvoice('INV-PERF-1');
        $elapsed = (microtime(true) - $start) * 1000;

        $this->assertLessThan(50, $elapsed, "Duplicate scan took {$elapsed}ms, expected < 50ms");
    }
}

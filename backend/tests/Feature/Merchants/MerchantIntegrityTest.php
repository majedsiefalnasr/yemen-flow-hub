<?php

namespace Tests\Feature\Merchants;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\Merchant;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MerchantIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;

    private Bank $otherBank;

    private User $bankAdmin;

    private User $cbyadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bank = Bank::query()->create(['name' => 'Bank A', 'code' => 'BKA', 'is_active' => true]);
        $this->otherBank = Bank::query()->create(['name' => 'Bank B', 'code' => 'BKB', 'is_active' => true]);
        $this->bankAdmin = User::query()->create([
            'name' => 'Bank Admin',
            'email' => 'ba@test.com',
            'password' => Hash::make('password'),
            'role' => UserRole::BANK_ADMIN->value,
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);
        $this->cbyadmin = User::query()->create([
            'name' => 'CBY Admin',
            'email' => 'cby@test.com',
            'password' => Hash::make('password'),
            'role' => UserRole::CBY_ADMIN->value,
            'bank_id' => null,
            'is_active' => true,
        ]);

        $permissionId = Permission::query()->insertGetId([
            'slug' => 'merchants.manage',
            'name_ar' => 'إدارة المستوردين',
            'name_en' => 'Manage importers',
            'group' => 'admin',
        ]);
        DB::table('role_permissions')->insert([
            ['permission_id' => $permissionId, 'role' => UserRole::CBY_ADMIN->value],
            ['permission_id' => $permissionId, 'role' => UserRole::BANK_ADMIN->value],
        ]);
    }

    private function makeMerchant(array $overrides = []): Merchant
    {
        return Merchant::query()->create(array_merge([
            'bank_id' => $this->bank->id,
            'name' => 'Test Merchant',
            'tax_number' => 'TX-'.uniqid(),
            'status' => 'ACTIVE',
            'version' => 1,
            'created_by' => $this->bankAdmin->id,
        ], $overrides));
    }

    private function createImportRequest(Merchant $merchant, string $status = 'SUBMITTED'): void
    {
        DB::table('import_requests')->insert([
            'reference_number' => 'YFH-'.uniqid(),
            'bank_id' => $merchant->bank_id,
            'merchant_id' => $merchant->id,
            'status' => $status,
            'created_by' => $this->bankAdmin->id,
            'currency' => 'USD',
            'amount' => 1000,
            'supplier_name' => 'Supplier',
            'goods_description' => 'Goods',
            'port_of_entry' => 'Aden',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ─── Tax Number Uniqueness (AC1) ─────────────────────────────────────────

    public function test_duplicate_tax_number_on_create_returns_business_error(): void
    {
        $this->makeMerchant(['tax_number' => 'TX-DUPE-001']);

        $this->actingAs($this->bankAdmin)->postJson('/api/v1/merchants', [
            'name' => 'Duplicate',
            'tax_number' => 'TX-DUPE-001',
        ])->assertConflict()
            ->assertJsonPath('error.code', 'MERCHANT_TAX_NUMBER_EXISTS');
    }

    public function test_duplicate_tax_number_on_update_returns_business_error(): void
    {
        $this->makeMerchant(['tax_number' => 'TX-TAKEN-001']);
        $merchant = $this->makeMerchant(['tax_number' => 'TX-MINE-001']);

        $this->actingAs($this->bankAdmin)->putJson("/api/v1/merchants/{$merchant->id}", [
            'tax_number' => 'TX-TAKEN-001',
            'version' => 1,
        ])->assertConflict()
            ->assertJsonPath('error.code', 'MERCHANT_TAX_NUMBER_EXISTS');
    }

    public function test_same_tax_number_on_own_record_succeeds(): void
    {
        $merchant = $this->makeMerchant(['tax_number' => 'TX-SELF-001']);

        $this->actingAs($this->bankAdmin)->putJson("/api/v1/merchants/{$merchant->id}", [
            'tax_number' => 'TX-SELF-001',
            'name' => 'Updated Name',
            'version' => 1,
        ])->assertOk();
    }

    // ─── Active Request Suspend Guard (AC2) ──────────────────────────────────

    public function test_cannot_suspend_merchant_with_active_requests(): void
    {
        $merchant = $this->makeMerchant();
        $this->createImportRequest($merchant, 'SUBMITTED');

        $this->actingAs($this->bankAdmin)->putJson("/api/v1/merchants/{$merchant->id}", [
            'status' => 'SUSPENDED',
            'version' => 1,
        ])->assertConflict()
            ->assertJsonPath('error.code', 'MERCHANT_HAS_ACTIVE_REQUESTS');
    }

    public function test_can_suspend_merchant_with_only_terminal_requests(): void
    {
        $merchant = $this->makeMerchant();
        $this->createImportRequest($merchant, 'COMPLETED');
        $this->createImportRequest($merchant, 'BANK_REJECTED');

        $this->actingAs($this->bankAdmin)->putJson("/api/v1/merchants/{$merchant->id}", [
            'status' => 'SUSPENDED',
            'version' => 1,
        ])->assertOk()
            ->assertJsonPath('data.status', 'SUSPENDED');
    }

    public function test_can_suspend_merchant_with_no_requests(): void
    {
        $merchant = $this->makeMerchant();

        $this->actingAs($this->bankAdmin)->putJson("/api/v1/merchants/{$merchant->id}", [
            'status' => 'SUSPENDED',
            'version' => 1,
        ])->assertOk()
            ->assertJsonPath('data.status', 'SUSPENDED');
    }

    // ─── Bank Immutability Guard (AC3) ───────────────────────────────────────

    public function test_bank_change_blocked_after_first_request(): void
    {
        $merchant = $this->makeMerchant();
        $this->createImportRequest($merchant, 'COMPLETED');

        $this->actingAs($this->cbyadmin)->putJson("/api/v1/merchants/{$merchant->id}", [
            'bank_id' => $this->otherBank->id,
            'version' => 1,
        ])->assertConflict()
            ->assertJsonPath('error.code', 'MERCHANT_BANK_IMMUTABLE');
    }

    public function test_bank_change_allowed_before_first_request(): void
    {
        $merchant = $this->makeMerchant();

        $this->actingAs($this->cbyadmin)->putJson("/api/v1/merchants/{$merchant->id}", [
            'bank_id' => $this->otherBank->id,
            'version' => 1,
        ])->assertOk()
            ->assertJsonPath('data.bank_id', $this->otherBank->id);
    }

    // ─── Out of Scope Guard (AC4) ───────────────────────────────────────────

    public function test_bank_admin_cannot_view_other_bank_merchant(): void
    {
        $merchant = $this->makeMerchant([
            'bank_id' => $this->otherBank->id,
            'created_by' => $this->cbyadmin->id,
        ]);

        $this->actingAs($this->bankAdmin)
            ->getJson("/api/v1/merchants/{$merchant->id}")
            ->assertForbidden();
    }

    // ─── Audit on Integrity Blocks ──────────────────────────────────────────

    public function test_integrity_block_does_not_create_spurious_audit(): void
    {
        $this->makeMerchant(['tax_number' => 'TX-AUDIT-DUP']);

        $beforeCount = DB::table('audit_logs')->count();

        $this->actingAs($this->bankAdmin)->postJson('/api/v1/merchants', [
            'name' => 'Duplicate',
            'tax_number' => 'TX-AUDIT-DUP',
        ])->assertConflict();

        $this->assertSame($beforeCount, DB::table('audit_logs')->count());
    }
}

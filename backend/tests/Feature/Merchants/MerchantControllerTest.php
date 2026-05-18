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

class MerchantControllerTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;
    private Bank $otherBank;
    private User $bankAdmin;
    private User $cbyadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bank = $this->makeBank('YCB');
        $this->otherBank = $this->makeBank('OTH');
        $this->bankAdmin = $this->makeUser(UserRole::BANK_ADMIN, $this->bank);
        $this->cbyadmin = $this->makeUser(UserRole::CBY_ADMIN, null);

        $this->seedMerchantsPermission();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeBank(string $code): Bank
    {
        return Bank::query()->create([
            'name' => "Bank {$code}",
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function makeUser(UserRole $role, ?Bank $bank): User
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

    private function makeMerchant(Bank $bank, array $overrides = []): Merchant
    {
        return Merchant::query()->create(array_merge([
            'bank_id' => $bank->id,
            'name' => 'تاجر الاختبار',
            'commercial_register' => 'CR-' . uniqid(),
            'tax_number' => 'TX-' . uniqid(),
            'is_active' => true,
            'created_by' => $this->bankAdmin->id,
        ], $overrides));
    }

    private function seedMerchantsPermission(): void
    {
        $permissionId = Permission::query()->insertGetId([
            'slug' => 'merchants.manage',
            'name_ar' => 'إدارة التجار',
            'name_en' => 'Manage merchants',
            'group' => 'admin',
        ]);

        DB::table('role_permissions')->insert([
            ['permission_id' => $permissionId, 'role' => UserRole::CBY_ADMIN->value],
            ['permission_id' => $permissionId, 'role' => UserRole::BANK_ADMIN->value],
        ]);
    }

    // ─── GET /api/merchants ───────────────────────────────────────────────────

    public function test_bank_admin_lists_only_own_bank_merchants(): void
    {
        $own = $this->makeMerchant($this->bank);
        $this->makeMerchant($this->otherBank);

        $response = $this->actingAs($this->bankAdmin)->getJson('/api/merchants');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($own->id));
        $this->assertCount(1, $ids);
    }

    public function test_cby_admin_lists_all_merchants(): void
    {
        $this->makeMerchant($this->bank);
        $this->makeMerchant($this->otherBank);

        $response = $this->actingAs($this->cbyadmin)->getJson('/api/merchants');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_bank_admin_cannot_filter_another_banks_merchants(): void
    {
        $this->makeMerchant($this->otherBank);

        $response = $this->actingAs($this->bankAdmin)->getJson('/api/merchants?bank_id=' . $this->otherBank->id);

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_unauthenticated_user_cannot_list_merchants(): void
    {
        $this->getJson('/api/merchants')->assertUnauthorized();
    }

    public function test_data_entry_can_list_own_bank_merchants(): void
    {
        // DATA_ENTRY uses merchant list to select merchants in request form
        $seedDataEntryPermission = function () {
            $permId = Permission::query()->insertGetId([
                'slug' => 'request.create',
                'name_ar' => 'إنشاء طلب',
                'name_en' => 'Create request',
                'group' => 'requests',
            ]);
            DB::table('role_permissions')->insert(['permission_id' => $permId, 'role' => UserRole::DATA_ENTRY->value]);
        };
        $seedDataEntryPermission();

        $this->makeMerchant($this->bank);
        $dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);

        // viewAny allows all active users — DATA_ENTRY can read but not write
        $this->actingAs($dataEntry)->getJson('/api/merchants')->assertOk();
    }

    // ─── POST /api/merchants ──────────────────────────────────────────────────

    public function test_bank_admin_creates_merchant_for_own_bank(): void
    {
        $payload = [
            'name' => 'شركة التقنية للاستيراد',
            'commercial_register' => 'CR-12345',
            'tax_number' => 'TX-99999',
            'address' => 'صنعاء، شارع التحرير',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->bankAdmin)->postJson('/api/merchants', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('merchants', [
            'name' => 'شركة التقنية للاستيراد',
            'bank_id' => $this->bank->id,
            'created_by' => $this->bankAdmin->id,
        ]);
    }

    public function test_bank_admin_store_enforces_own_bank_regardless_of_payload(): void
    {
        $response = $this->actingAs($this->bankAdmin)->postJson('/api/merchants', [
            'name' => 'تاجر آخر',
            'bank_id' => $this->otherBank->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('merchants', [
            'name' => 'تاجر آخر',
            'bank_id' => $this->bank->id,
        ]);
    }

    public function test_create_merchant_requires_name(): void
    {
        $this->actingAs($this->bankAdmin)
            ->postJson('/api/merchants', ['commercial_register' => 'CR-000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    // ─── PUT /api/merchants/{id} ──────────────────────────────────────────────

    public function test_bank_admin_updates_own_bank_merchant(): void
    {
        $merchant = $this->makeMerchant($this->bank);

        $response = $this->actingAs($this->bankAdmin)->putJson("/api/merchants/{$merchant->id}", [
            'name' => 'اسم محدّث',
            'is_active' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'اسم محدّث');
        $this->assertDatabaseHas('merchants', ['id' => $merchant->id, 'name' => 'اسم محدّث']);
    }

    public function test_bank_admin_cannot_update_other_bank_merchant(): void
    {
        $merchant = $this->makeMerchant($this->otherBank);

        $this->actingAs($this->bankAdmin)
            ->putJson("/api/merchants/{$merchant->id}", ['name' => 'Hacked', 'is_active' => true])
            ->assertForbidden();
    }

    // ─── Suspend (PUT is_active: false) ───────────────────────────────────────

    public function test_bank_admin_suspends_merchant(): void
    {
        $merchant = $this->makeMerchant($this->bank, ['is_active' => true]);

        $response = $this->actingAs($this->bankAdmin)->putJson("/api/merchants/{$merchant->id}", [
            'name' => $merchant->name,
            'is_active' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas('merchants', ['id' => $merchant->id, 'is_active' => false]);
    }

    public function test_bank_admin_reactivates_suspended_merchant(): void
    {
        $merchant = $this->makeMerchant($this->bank, ['is_active' => false]);

        $response = $this->actingAs($this->bankAdmin)->putJson("/api/merchants/{$merchant->id}", [
            'name' => $merchant->name,
            'is_active' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.is_active', true);
    }

    // ─── Search filter ────────────────────────────────────────────────────────

    public function test_bank_admin_can_search_own_merchants_by_commercial_register(): void
    {
        $this->makeMerchant($this->bank, ['commercial_register' => 'CR-UNIQUE-001']);
        $this->makeMerchant($this->bank, ['commercial_register' => 'CR-OTHER-002']);

        $response = $this->actingAs($this->bankAdmin)->getJson('/api/merchants?search=UNIQUE-001');

        $response->assertOk();
        $registers = collect($response->json('data'))->pluck('commercial_register');
        $this->assertTrue($registers->contains('CR-UNIQUE-001'));
        $this->assertFalse($registers->contains('CR-OTHER-002'));
    }
}

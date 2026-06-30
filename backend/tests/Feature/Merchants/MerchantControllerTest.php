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
            'tax_number' => 'TX-'.uniqid(),
            'status' => 'ACTIVE',
            'version' => 1,
            'created_by' => $this->bankAdmin->id,
        ], $overrides));
    }

    private function seedMerchantsPermission(): void
    {
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

    // ─── GET /api/v1/merchants ───────────────────────────────────────────────

    public function test_bank_admin_lists_only_own_bank_merchants(): void
    {
        $own = $this->makeMerchant($this->bank);
        $this->makeMerchant($this->otherBank);

        $response = $this->actingAs($this->bankAdmin)->getJson('/api/v1/merchants');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($own->id));
        $this->assertCount(1, $ids);
    }

    public function test_cby_admin_lists_all_merchants(): void
    {
        $this->makeMerchant($this->bank);
        $this->makeMerchant($this->otherBank);

        $response = $this->actingAs($this->cbyadmin)->getJson('/api/v1/merchants');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_list_returns_data_and_meta_envelope(): void
    {
        $this->makeMerchant($this->bank);

        $response = $this->actingAs($this->bankAdmin)->getJson('/api/v1/merchants');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'bank_id', 'name', 'tax_number', 'status', 'version', 'created_at', 'updated_at']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_pagination_default_25_max_100(): void
    {
        $response = $this->actingAs($this->bankAdmin)->getJson('/api/v1/merchants');
        $response->assertOk()->assertJsonPath('meta.per_page', 25);

        $response = $this->actingAs($this->bankAdmin)->getJson('/api/v1/merchants?per_page=200');
        $response->assertOk()->assertJsonPath('meta.per_page', 100);
    }

    public function test_search_filter_matches_name_and_tax_number(): void
    {
        $this->makeMerchant($this->bank, ['name' => 'شركة الأمل', 'tax_number' => 'TX-HOPE-001']);
        $this->makeMerchant($this->bank, ['name' => 'شركة السلام', 'tax_number' => 'TX-PEACE-002']);

        $response = $this->actingAs($this->bankAdmin)->getJson('/api/v1/merchants?search=HOPE');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_status_filter(): void
    {
        $this->makeMerchant($this->bank, ['status' => 'ACTIVE']);
        $this->makeMerchant($this->bank, ['status' => 'SUSPENDED']);

        $response = $this->actingAs($this->bankAdmin)->getJson('/api/v1/merchants?status=SUSPENDED');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('SUSPENDED', $response->json('data.0.status'));
    }

    public function test_bank_id_filter_for_global_user(): void
    {
        $this->makeMerchant($this->bank);
        $this->makeMerchant($this->otherBank);

        $response = $this->actingAs($this->cbyadmin)->getJson('/api/v1/merchants?bank_id='.$this->bank->id);
        $response->assertOk();
        $bankIds = collect($response->json('data'))->pluck('bank_id')->unique();
        $this->assertCount(1, $bankIds);
        $this->assertSame($this->bank->id, $bankIds->first());
    }

    public function test_bank_user_ignores_bank_id_filter(): void
    {
        $this->makeMerchant($this->bank);
        $this->makeMerchant($this->otherBank);

        $response = $this->actingAs($this->bankAdmin)->getJson('/api/v1/merchants?bank_id='.$this->otherBank->id);
        $response->assertOk();
        $bankIds = collect($response->json('data'))->pluck('bank_id')->unique();
        $this->assertTrue($bankIds->every(fn ($id) => $id === $this->bank->id));
    }

    public function test_unauthenticated_user_cannot_list(): void
    {
        $this->getJson('/api/v1/merchants')->assertUnauthorized();
    }

    // ─── POST /api/v1/merchants ──────────────────────────────────────────────

    public function test_bank_admin_creates_merchant(): void
    {
        $payload = [
            'name' => 'شركة التقنية للاستيراد',
            'tax_number' => 'TX-CREATE-001',
            'tax_card_expiry' => '2027-06-01',
            'address' => 'صنعاء',
            'phone' => '+967771234567',
        ];

        $response = $this->actingAs($this->bankAdmin)->postJson('/api/v1/merchants', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'شركة التقنية للاستيراد')
            ->assertJsonPath('data.bank_id', $this->bank->id)
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.status', 'ACTIVE');
    }

    public function test_bank_admin_store_forces_own_bank(): void
    {
        $response = $this->actingAs($this->bankAdmin)->postJson('/api/v1/merchants', [
            'name' => 'تاجر',
            'tax_number' => 'TX-FORCE-001',
            'bank_id' => $this->otherBank->id,
        ]);

        $response->assertCreated();
        $this->assertSame($this->bank->id, $response->json('data.bank_id'));
    }

    public function test_cby_admin_must_provide_bank_id(): void
    {
        $this->actingAs($this->cbyadmin)->postJson('/api/v1/merchants', [
            'name' => 'تاجر بدون بنك',
            'tax_number' => 'TX-NO-BANK',
        ])->assertUnprocessable();
    }

    public function test_cby_admin_creates_for_selected_bank(): void
    {
        $response = $this->actingAs($this->cbyadmin)->postJson('/api/v1/merchants', [
            'name' => 'تاجر مركزي',
            'tax_number' => 'TX-CBY-001',
            'bank_id' => $this->otherBank->id,
        ]);

        $response->assertCreated()->assertJsonPath('data.bank_id', $this->otherBank->id);
    }

    public function test_create_requires_name_and_tax_number(): void
    {
        $this->actingAs($this->bankAdmin)
            ->postJson('/api/v1/merchants', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'tax_number']);
    }

    // ─── PUT /api/v1/merchants/{id} ──────────────────────────────────────────

    public function test_update_with_version_check(): void
    {
        $merchant = $this->makeMerchant($this->bank);

        $response = $this->actingAs($this->bankAdmin)->putJson("/api/v1/merchants/{$merchant->id}", [
            'name' => 'اسم محدّث',
            'version' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'اسم محدّث')
            ->assertJsonPath('data.version', 2);
    }

    public function test_stale_version_returns_409(): void
    {
        $merchant = $this->makeMerchant($this->bank);

        $this->actingAs($this->bankAdmin)->putJson("/api/v1/merchants/{$merchant->id}", [
            'name' => 'اسم محدّث',
            'version' => 99,
        ])->assertConflict()
            ->assertJsonPath('error.code', 'STALE_RESOURCE');
    }

    public function test_update_requires_version(): void
    {
        $merchant = $this->makeMerchant($this->bank);

        $this->actingAs($this->bankAdmin)->putJson("/api/v1/merchants/{$merchant->id}", [
            'name' => 'اسم',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['version']);
    }

    public function test_bank_admin_cannot_update_other_bank_merchant(): void
    {
        $merchant = $this->makeMerchant($this->otherBank);

        $this->actingAs($this->bankAdmin)
            ->putJson("/api/v1/merchants/{$merchant->id}", ['name' => 'Hacked', 'version' => 1])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'MERCHANT_OUT_OF_SCOPE');
    }

    // ─── DELETE /api/v1/merchants/{id} ───────────────────────────────────────

    public function test_soft_delete_merchant(): void
    {
        $merchant = $this->makeMerchant($this->bank);

        $this->actingAs($this->bankAdmin)->deleteJson("/api/v1/merchants/{$merchant->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('merchants', ['id' => $merchant->id]);
    }

    public function test_bank_admin_cannot_delete_other_bank_merchant(): void
    {
        $merchant = $this->makeMerchant($this->otherBank);

        $this->actingAs($this->bankAdmin)
            ->deleteJson("/api/v1/merchants/{$merchant->id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'MERCHANT_OUT_OF_SCOPE');
    }

    // ─── Audit ──────────────────────────────────────────────────────────────

    public function test_create_is_audited(): void
    {
        $this->actingAs($this->bankAdmin)->postJson('/api/v1/merchants', [
            'name' => 'Audited Merchant',
            'tax_number' => 'TX-AUDIT-001',
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->bankAdmin->id,
            'action' => 'GOVERNANCE_CREATED',
            'subject_type' => Merchant::class,
        ]);
    }

    public function test_update_is_audited(): void
    {
        $merchant = $this->makeMerchant($this->bank);

        $this->actingAs($this->bankAdmin)->putJson("/api/v1/merchants/{$merchant->id}", [
            'name' => 'Updated',
            'version' => 1,
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->bankAdmin->id,
            'action' => 'GOVERNANCE_UPDATED',
            'subject_type' => Merchant::class,
        ]);
    }
}

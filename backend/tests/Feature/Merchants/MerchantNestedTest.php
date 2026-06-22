<?php

namespace Tests\Feature\Merchants;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MerchantNestedTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;

    private User $bankAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bank = Bank::query()->create(['name' => 'Bank Test', 'code' => 'TST', 'is_active' => true]);
        $this->bankAdmin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role' => UserRole::BANK_ADMIN->value,
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);

        $permissionId = Permission::query()->insertGetId([
            'slug' => 'merchants.manage',
            'name_ar' => 'إدارة المستوردين',
            'name_en' => 'Manage importers',
            'group' => 'admin',
        ]);
        DB::table('role_permissions')->insert([
            ['permission_id' => $permissionId, 'role' => UserRole::BANK_ADMIN->value],
        ]);
    }

    public function test_create_merchant_with_owners_and_companies(): void
    {
        $payload = [
            'name' => 'شركة مع مالكين',
            'tax_number' => 'TX-NESTED-001',
            'owners' => [
                ['name' => 'مالك أول', 'ownership_percentage' => 60],
                ['name' => 'مالك ثاني', 'ownership_percentage' => 40],
            ],
            'companies' => [
                [
                    'name' => 'شركة فرعية',
                    'commercial_registration_number' => 'CR-NESTED-001',
                    'commercial_registration_expiry' => '2028-01-01',
                    'is_active' => true,
                ],
            ],
        ];

        $response = $this->actingAs($this->bankAdmin)->postJson('/api/v1/merchants', $payload);

        $response->assertCreated();
        $this->assertCount(2, $response->json('data.owners'));
        $this->assertCount(1, $response->json('data.companies'));
        $this->assertEquals(60, $response->json('data.owners.0.ownership_percentage'));
        $this->assertSame('CR-NESTED-001', $response->json('data.companies.0.commercial_registration_number'));
    }

    public function test_update_replaces_owners_and_companies(): void
    {
        $merchant = Merchant::query()->create([
            'bank_id' => $this->bank->id,
            'name' => 'Original',
            'tax_number' => 'TX-REPLACE-001',
            'status' => 'ACTIVE',
            'version' => 1,
            'created_by' => $this->bankAdmin->id,
        ]);
        $merchant->owners()->create(['name' => 'Old Owner', 'ownership_percentage' => 100]);
        $merchant->companies()->create([
            'name' => 'Old Company',
            'commercial_registration_number' => 'CR-OLD-001',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->bankAdmin)->putJson("/api/v1/merchants/{$merchant->id}", [
            'version' => 1,
            'owners' => [
                ['name' => 'New Owner', 'ownership_percentage' => 75],
            ],
            'companies' => [
                ['name' => 'New Company', 'commercial_registration_number' => 'CR-NEW-001', 'is_active' => true],
                ['name' => 'Second Company', 'commercial_registration_number' => 'CR-NEW-002', 'is_active' => false],
            ],
        ]);

        $response->assertOk();
        $this->assertCount(1, $response->json('data.owners'));
        $this->assertCount(2, $response->json('data.companies'));
        $this->assertSame('New Owner', $response->json('data.owners.0.name'));
        $this->assertDatabaseMissing('merchant_owners', ['name' => 'Old Owner']);
    }

    public function test_ownership_percentage_validated_0_to_100(): void
    {
        $this->actingAs($this->bankAdmin)->postJson('/api/v1/merchants', [
            'name' => 'Bad Percentage',
            'tax_number' => 'TX-PCT-001',
            'owners' => [
                ['name' => 'Over', 'ownership_percentage' => 150],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['owners.0.ownership_percentage']);

        $this->actingAs($this->bankAdmin)->postJson('/api/v1/merchants', [
            'name' => 'Negative Percentage',
            'tax_number' => 'TX-PCT-002',
            'owners' => [
                ['name' => 'Negative', 'ownership_percentage' => -5],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['owners.0.ownership_percentage']);
    }

    public function test_duplicate_commercial_registration_returns_business_error(): void
    {
        MerchantCompany::query()->create([
            'merchant_id' => Merchant::query()->create([
                'bank_id' => $this->bank->id,
                'name' => 'Existing',
                'tax_number' => 'TX-DUP-CR-001',
                'status' => 'ACTIVE',
                'version' => 1,
                'created_by' => $this->bankAdmin->id,
            ])->id,
            'name' => 'Existing Company',
            'commercial_registration_number' => 'CR-TAKEN-001',
            'is_active' => true,
        ]);

        $this->actingAs($this->bankAdmin)->postJson('/api/v1/merchants', [
            'name' => 'New Merchant',
            'tax_number' => 'TX-DUP-CR-002',
            'companies' => [
                ['name' => 'Duplicate Company', 'commercial_registration_number' => 'CR-TAKEN-001', 'is_active' => true],
            ],
        ])->assertConflict()
            ->assertJsonPath('error.code', 'COMMERCIAL_REGISTRATION_EXISTS');
    }

    public function test_transaction_rollback_on_nested_failure(): void
    {
        $initialCount = Merchant::query()->count();

        $this->actingAs($this->bankAdmin)->postJson('/api/v1/merchants', [
            'name' => 'Will Fail',
            'tax_number' => 'TX-ROLLBACK-001',
            'owners' => [
                ['name' => 'Valid', 'ownership_percentage' => 50],
                ['ownership_percentage' => 30],
            ],
        ])->assertUnprocessable();

        $this->assertSame($initialCount, Merchant::query()->count());
    }

    public function test_show_includes_nested_owners_and_companies(): void
    {
        $merchant = Merchant::query()->create([
            'bank_id' => $this->bank->id,
            'name' => 'Detailed',
            'tax_number' => 'TX-SHOW-001',
            'status' => 'ACTIVE',
            'version' => 1,
            'created_by' => $this->bankAdmin->id,
        ]);
        $merchant->owners()->create(['name' => 'Owner Show', 'ownership_percentage' => 100]);
        $merchant->companies()->create([
            'name' => 'Company Show',
            'commercial_registration_number' => 'CR-SHOW-001',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->bankAdmin)->getJson("/api/v1/merchants/{$merchant->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data.owners')
            ->assertJsonCount(1, 'data.companies')
            ->assertJsonPath('data.owners.0.name', 'Owner Show');
    }
}

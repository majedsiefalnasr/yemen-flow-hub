<?php

namespace Tests\Feature\Financing;

use App\Enums\CoverageType;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\Merchant;
use App\Models\Trader;
use App\Models\User;
use App\Services\DuplicateDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FinancingPreventionTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank1;

    private Bank $bank2;

    private User $dataEntry1;

    private User $dataEntry2;

    private Merchant $merchant1;

    private Merchant $merchant2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCreatePermission();

        $this->bank1 = Bank::query()->create(['name' => 'Bank One', 'code' => 'B1', 'is_active' => true]);
        $this->bank2 = Bank::query()->create(['name' => 'Bank Two', 'code' => 'B2', 'is_active' => true]);
        $this->dataEntry1 = $this->makeUser(UserRole::DATA_ENTRY, $this->bank1);
        $this->dataEntry2 = $this->makeUser(UserRole::DATA_ENTRY, $this->bank2);
        $this->merchant1 = $this->makeMerchant($this->bank1);
        $this->merchant2 = $this->makeMerchant($this->bank2);
    }

    public function test_store_returns_financing_limit_exceeded_when_global_capacity_overflows(): void
    {
        $trader = Trader::factory()->create(['tax_number' => 'TAX-STORE-1']);
        $this->makeSubmittedRequest($this->bank2, $this->dataEntry2, $trader, 'INV-STORE-1', 70);

        $response = $this->actingAs($this->dataEntry1)->postJson('/api/requests', $this->validPayload($trader, [
            'invoice_number' => 'INV-STORE-1',
            'request_percentage' => 40,
            'coverage_type' => CoverageType::PARTIAL->value,
        ]));

        $response->assertUnprocessable()
            ->assertJsonPath('error_code', 'FINANCING_LIMIT_EXCEEDED');
    }

    public function test_submit_returns_financing_limit_exceeded_and_does_not_transition(): void
    {
        $trader = Trader::factory()->create(['tax_number' => 'TAX-SUBMIT-1']);
        $this->makeSubmittedRequest($this->bank2, $this->dataEntry2, $trader, 'INV-SUBMIT-1', 70);

        $draft = $this->createDraftRequest($this->bank1, $this->dataEntry1, $trader, 'INV-SUBMIT-1', 40);
        $historyBefore = DB::table('request_stage_history')->count();
        $auditBefore = AuditLog::query()->count();

        $this->actingAs($this->dataEntry1)
            ->postJson("/api/workflow/{$draft->id}/submit")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'FINANCING_LIMIT_EXCEEDED');

        $this->assertDatabaseHas('import_requests', [
            'id' => $draft->id,
            'status' => RequestStatus::DRAFT->value,
        ]);
        $this->assertSame($historyBefore, DB::table('request_stage_history')->count());
        $this->assertSame($auditBefore, AuditLog::query()->count());
    }

    public function test_submit_returns_duplicate_invoice_mismatch_for_currency(): void
    {
        $trader = Trader::factory()->create(['tax_number' => 'TAX-MISMATCH-1']);
        $this->createDraftRequest($this->bank2, $this->dataEntry2, $trader, 'INV-MISMATCH-1', 25, 'USD', 5000);

        $draft = $this->createDraftRequest($this->bank1, $this->dataEntry1, $trader, 'INV-MISMATCH-1', 25, 'EUR', 5000);

        $this->actingAs($this->dataEntry1)
            ->postJson("/api/workflow/{$draft->id}/submit")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'DUPLICATE_INVOICE_MISMATCH')
            ->assertJsonMissing(['reference_number' => true]);
    }

    public function test_three_consistent_partials_succeed_and_fourth_percent_is_rejected(): void
    {
        $trader = Trader::factory()->create(['tax_number' => 'TAX-MULTI-1']);

        $first = $this->createDraftRequest($this->bank1, $this->dataEntry1, $trader, 'INV-MULTI-1', 40);
        $this->actingAs($this->dataEntry1)->postJson("/api/workflow/{$first->id}/submit")->assertOk();

        $second = $this->createDraftRequest($this->bank2, $this->dataEntry2, $trader, 'INV-MULTI-1', 35);
        $this->actingAs($this->dataEntry2)->postJson("/api/workflow/{$second->id}/submit")->assertOk();

        $thirdBank = Bank::query()->create(['name' => 'Bank Three', 'code' => 'B3', 'is_active' => true]);
        $dataEntry3 = $this->makeUser(UserRole::DATA_ENTRY, $thirdBank);
        $this->makeMerchant($thirdBank);
        $third = $this->createDraftRequest($thirdBank, $dataEntry3, $trader, 'INV-MULTI-1', 25);
        $this->actingAs($dataEntry3)->postJson("/api/workflow/{$third->id}/submit")->assertOk();

        $fourth = $this->createDraftRequest($this->bank1, $this->dataEntry1, $trader, 'INV-MULTI-1', 1);
        $this->actingAs($this->dataEntry1)
            ->postJson("/api/workflow/{$fourth->id}/submit")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'FINANCING_LIMIT_EXCEEDED');
    }

    public function test_duplicate_detection_reporting_methods_remain_unchanged(): void
    {
        $service = app(DuplicateDetectionService::class);
        $this->makeSubmittedRequest(
            $this->bank1,
            $this->dataEntry1,
            Trader::factory()->create(['tax_number' => 'TAX-DETECT-1']),
            'INV-DETECT-1',
            50,
        );
        $this->makeSubmittedRequest(
            $this->bank2,
            $this->dataEntry2,
            Trader::factory()->create(['tax_number' => 'TAX-DETECT-2']),
            'INV-DETECT-1',
            40,
        );

        $this->assertCount(2, $service->findDuplicatesForInvoice('INV-DETECT-1'));
        $this->assertTrue($service->findDuplicateGroups()->has('INV-DETECT-1'));
    }

    public function test_guard_error_response_does_not_leak_foreign_request_details(): void
    {
        $trader = Trader::factory()->create(['tax_number' => 'TAX-LEAK-1']);
        $existing = $this->createDraftRequest($this->bank2, $this->dataEntry2, $trader, 'INV-LEAK-1', 25, 'USD', 5000);
        $draft = $this->createDraftRequest($this->bank1, $this->dataEntry1, $trader, 'INV-LEAK-1', 25, 'EUR', 5000);

        $response = $this->actingAs($this->dataEntry1)
            ->postJson("/api/workflow/{$draft->id}/submit")
            ->assertUnprocessable();

        $body = json_encode($response->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($existing->reference_number, $body);
        $this->assertStringNotContainsString((string) $existing->bank_id, $body);
    }

    private function seedCreatePermission(): void
    {
        $id = DB::table('permissions')->insertGetId([
            'slug' => 'request.create',
            'name_ar' => 'Create',
            'name_en' => 'Create',
            'group' => 'requests',
        ]);

        DB::table('role_permissions')->insert([
            'permission_id' => $id,
            'role' => UserRole::DATA_ENTRY->value,
        ]);
    }

    private function makeUser(UserRole $role, Bank $bank): User
    {
        static $counter = 0;
        $counter++;

        return User::query()->create([
            'name' => "User {$counter}",
            'email' => "user{$counter}@prevention.test",
            'password' => Hash::make('password'),
            'role' => $role,
            'bank_id' => $bank->id,
            'is_active' => true,
        ]);
    }

    private function makeMerchant(Bank $bank): Merchant
    {
        static $counter = 0;
        $counter++;

        return Merchant::query()->create([
            'name' => "Merchant {$counter}",
            'tax_number' => "M-TAX-{$counter}",
            'commercial_register' => "CR-{$counter}",
            'address' => 'Sanaa',
            'contact' => '+967',
            'category' => 'general',
            'status' => 'active',
            'bank_id' => $bank->id,
        ]);
    }

    private function validPayload(Trader $trader, array $overrides = []): array
    {
        return [
            'merchant_id' => $this->merchant1->id,
            'trader_id' => $trader->id,
            'currency' => 'USD',
            'amount' => 5000,
            'supplier_name' => 'Supplier',
            'goods_description' => 'Goods',
            'port_of_entry' => 'Aden',
            'invoice_number' => 'INV-BASE',
            'invoice_currency' => 'USD',
            'total_invoice_amount' => 5000,
            'request_percentage' => 25,
            'coverage_type' => CoverageType::PARTIAL->value,
            ...$overrides,
        ];
    }

    private function createDraftRequest(
        Bank $bank,
        User $creator,
        Trader $trader,
        string $invoiceNumber,
        float $percentage,
        string $invoiceCurrency = 'USD',
        float|int $totalInvoiceAmount = 5000,
    ): ImportRequest {
        app()->instance('workflow.transition.active', true);
        try {
            return ImportRequest::query()->create([
                'bank_id' => $bank->id,
                'merchant_id' => Merchant::query()->where('bank_id', $bank->id)->value('id'),
                'trader_id' => $trader->id,
                'created_by' => $creator->id,
                'currency' => 'USD',
                'amount' => 5000,
                'supplier_name' => 'Supplier',
                'goods_description' => 'Goods',
                'port_of_entry' => 'Aden',
                'goods_type' => 'مواد غذائية',
                'payment_terms' => 'LC',
                'invoice_date' => now()->subDays(2)->toDateString(),
                'origin_country' => 'اليمن',
                'arrival_port' => 'ميناء عدن',
                'customs_office' => 'جمارك عدن',
                'status' => RequestStatus::DRAFT,
                'current_owner_role' => UserRole::DATA_ENTRY,
                'invoice_number' => $invoiceNumber,
                'invoice_currency' => $invoiceCurrency,
                'total_invoice_amount' => $totalInvoiceAmount,
                'request_percentage' => $percentage,
                'coverage_type' => CoverageType::PARTIAL,
                'trader_snapshot_name' => $trader->trader_name,
                'trader_snapshot_tax_number' => $trader->tax_number,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    private function makeSubmittedRequest(
        Bank $bank,
        User $creator,
        Trader $trader,
        string $invoiceNumber,
        float $percentage,
    ): ImportRequest {
        $draft = $this->createDraftRequest($bank, $creator, $trader, $invoiceNumber, $percentage);
        $this->actingAs($creator)->postJson("/api/workflow/{$draft->id}/submit")->assertOk();

        return $draft->refresh();
    }
}

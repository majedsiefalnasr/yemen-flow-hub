<?php

namespace Tests\Unit\Services;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Exceptions\DuplicateInvoiceMismatchException;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\Merchant;
use App\Models\User;
use App\Services\DuplicateDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DuplicateDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private DuplicateDetectionService $service;

    private Bank $bank1;

    private Bank $bank2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DuplicateDetectionService;
        $this->bank1 = Bank::query()->create(['name' => 'بنك التضامن', 'code' => 'B1', 'is_active' => true]);
        $this->bank2 = Bank::query()->create(['name' => 'بنك سبأ', 'code' => 'B2', 'is_active' => true]);
    }

    private function makeUser(Bank $bank): User
    {
        static $i = 0;

        return User::query()->create([
            'name' => 'User '.(++$i),
            'email' => "u{$i}@test.com",
            'password' => Hash::make('pass'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $bank->id,
            'is_active' => true,
        ]);
    }

    private function makeRequest(Bank $bank, ?string $invoiceNumber = null, bool $deleted = false): ImportRequest
    {
        $merchant = Merchant::query()->create([
            'name' => 'Merchant', 'tax_number' => uniqid('TX'),
            'commercial_register' => uniqid('CR'), 'address' => 'Sanaa',
            'contact' => '+967', 'category' => 'general', 'status' => 'active',
            'bank_id' => $bank->id,
        ]);
        app()->instance('workflow.transition.active', true);
        try {
            $req = ImportRequest::query()->create([
                'bank_id' => $bank->id, 'merchant_id' => $merchant->id,
                'created_by' => $this->makeUser($bank)->id,
                'currency' => 'USD', 'amount' => 1000,
                'supplier_name' => 'Supplier', 'goods_description' => 'Goods',
                'port_of_entry' => 'Aden', 'status' => RequestStatus::DRAFT,
                'current_owner_role' => UserRole::DATA_ENTRY,
                'invoice_number' => $invoiceNumber,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
        if ($deleted) {
            $req->delete();
        }

        return $req;
    }

    public function test_finds_duplicates_across_banks(): void
    {
        $this->makeRequest($this->bank1, 'INV-001');
        $this->makeRequest($this->bank2, 'INV-001');

        $results = $this->service->findDuplicatesForInvoice('INV-001');

        $this->assertCount(2, $results);
    }

    public function test_excludes_specified_request_id(): void
    {
        $req1 = $this->makeRequest($this->bank1, 'INV-002');
        $this->makeRequest($this->bank2, 'INV-002');

        $results = $this->service->findDuplicatesForInvoice('INV-002', $req1->id);

        $this->assertCount(1, $results);
        $this->assertNotEquals($req1->id, $results->first()->id);
    }

    public function test_excludes_soft_deleted_rows(): void
    {
        $this->makeRequest($this->bank1, 'INV-003');
        $this->makeRequest($this->bank2, 'INV-003', deleted: true);

        $results = $this->service->findDuplicatesForInvoice('INV-003');

        $this->assertCount(1, $results);
    }

    public function test_returns_required_fields(): void
    {
        $this->makeRequest($this->bank1, 'INV-004');

        $result = $this->service->findDuplicatesForInvoice('INV-004')->first();

        $this->assertNotNull($result->id);
        $this->assertNotNull($result->bank_id);
        $this->assertNotNull($result->amount);
        $this->assertNotNull($result->currency);
        $this->assertNotNull($result->created_at);
        $this->assertNotNull($result->status);
    }

    public function test_returns_empty_collection_when_no_duplicates(): void
    {
        $results = $this->service->findDuplicatesForInvoice('INV-NONE');
        $this->assertCount(0, $results);
    }

    public function test_find_duplicate_groups_returns_groups(): void
    {
        $this->makeRequest($this->bank1, 'INV-G1');
        $this->makeRequest($this->bank2, 'INV-G1');
        $this->makeRequest($this->bank1, 'INV-G2');
        $this->makeRequest($this->bank2, 'INV-G2');
        $this->makeRequest($this->bank1, 'INV-UNIQUE'); // not a duplicate

        $groups = $this->service->findDuplicateGroups();

        $this->assertCount(2, $groups);
        $this->assertTrue($groups->has('INV-G1'));
        $this->assertTrue($groups->has('INV-G2'));
        $this->assertFalse($groups->has('INV-UNIQUE'));
    }

    public function test_find_duplicate_groups_excludes_soft_deleted(): void
    {
        $this->makeRequest($this->bank1, 'INV-D1');
        $this->makeRequest($this->bank2, 'INV-D1', deleted: true);

        $groups = $this->service->findDuplicateGroups();

        $this->assertFalse($groups->has('INV-D1'));
    }

    public function test_find_duplicate_groups_returns_empty_when_none(): void
    {
        $groups = $this->service->findDuplicateGroups();
        $this->assertCount(0, $groups);
    }

    public function test_assert_invoice_key_consistency_passes_for_matching_snapshot(): void
    {
        $this->makeFinancingRequest($this->bank1, 'TAX-100', 'INV-100', 'USD', '5000.00');

        $this->service->assertInvoiceKeyConsistency([
            'trader_snapshot_tax_number' => 'TAX-100',
            'invoice_number' => 'INV-100',
            'invoice_currency' => 'USD',
            'total_invoice_amount' => 5000,
        ]);

        $this->assertTrue(true);
    }

    public function test_assert_invoice_key_consistency_rejects_mismatched_currency(): void
    {
        $this->makeFinancingRequest($this->bank1, 'TAX-101', 'INV-101', 'USD', '5000.00');

        $this->expectException(DuplicateInvoiceMismatchException::class);

        $this->service->assertInvoiceKeyConsistency([
            'trader_snapshot_tax_number' => 'TAX-101',
            'invoice_number' => 'INV-101',
            'invoice_currency' => 'EUR',
            'total_invoice_amount' => 5000,
        ]);
    }

    public function test_assert_invoice_key_consistency_rejects_mismatched_total(): void
    {
        $this->makeFinancingRequest($this->bank1, 'TAX-102', 'INV-102', 'USD', '5000.00');

        $this->expectException(DuplicateInvoiceMismatchException::class);

        $this->service->assertInvoiceKeyConsistency([
            'trader_snapshot_tax_number' => 'TAX-102',
            'invoice_number' => 'INV-102',
            'invoice_currency' => 'USD',
            'total_invoice_amount' => 6000,
        ]);
    }

    private function makeFinancingRequest(
        Bank $bank,
        string $taxNumber,
        string $invoiceNumber,
        string $invoiceCurrency,
        string $totalInvoiceAmount,
    ): ImportRequest {
        app()->instance('workflow.transition.active', true);
        try {
            return ImportRequest::query()->create([
                'bank_id' => $bank->id,
                'created_by' => $this->makeUser($bank)->id,
                'currency' => 'USD',
                'amount' => 1000,
                'supplier_name' => 'Supplier',
                'goods_description' => 'Goods',
                'port_of_entry' => 'Aden',
                'status' => RequestStatus::DRAFT,
                'current_owner_role' => UserRole::DATA_ENTRY,
                'invoice_number' => $invoiceNumber,
                'trader_snapshot_tax_number' => $taxNumber,
                'invoice_currency' => $invoiceCurrency,
                'total_invoice_amount' => $totalInvoiceAmount,
                'request_percentage' => 25,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }
}

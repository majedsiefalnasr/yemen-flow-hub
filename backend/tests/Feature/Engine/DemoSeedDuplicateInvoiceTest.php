<?php

namespace Tests\Feature\Engine;

use App\Models\EngineRequest;
use App\Services\Workflow\DuplicateInvoiceChecker;
use Database\Seeders\BankSeeder;
use Database\Seeders\Catalog\SeederCatalog;
use Database\Seeders\EngineRequestAnchorSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ImportFinancingWorkflowSeeder;
use Database\Seeders\MerchantSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkflowActionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Duplicate invoice coverage per spec § Bank-specific invoice transformation
 * and § Direct-insert anchors: only the A023 pair intentionally shares a
 * normalized invoice key; every other seeded anchor is clean.
 */
class DemoSeedDuplicateInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            GovernanceSeeder::class,
            ReferenceDataSeeder::class,
            WorkflowActionSeeder::class,
            ImportFinancingWorkflowSeeder::class,
            BankSeeder::class,
            UserSeeder::class,
            MerchantSeeder::class,
            EngineRequestAnchorSeeder::class,
        ]);
    }

    public function test_a023_pair_shares_normalized_invoice_key(): void
    {
        $ybrd = EngineRequest::query()->where('reference', SeederCatalog::ANCHOR_DUPLICATE_YBRD)->firstOrFail();
        $tiib = EngineRequest::query()->where('reference', SeederCatalog::ANCHOR_DUPLICATE_TIIB)->firstOrFail();

        $this->assertSame($ybrd->invoice_number_normalized, $tiib->invoice_number_normalized);
    }

    public function test_checker_flags_duplicate_for_a023_pair(): void
    {
        $ybrd = EngineRequest::query()->where('reference', SeederCatalog::ANCHOR_DUPLICATE_YBRD)->firstOrFail();

        $result = app(DuplicateInvoiceChecker::class)->check($ybrd->invoice_number, $ybrd->id);

        $this->assertNotNull($result);
        $this->assertSame('DUPLICATE_INVOICE', $result['code']);

        $duplicateRefs = collect($result['duplicates'])->pluck('reference');
        $this->assertTrue($duplicateRefs->contains(SeederCatalog::ANCHOR_DUPLICATE_TIIB));
    }

    public function test_normal_anchor_samples_have_no_duplicate_warning(): void
    {
        $submitted = EngineRequest::query()->where('reference', SeederCatalog::ANCHOR_SUBMITTED_NOTIFICATION)->firstOrFail();

        $result = app(DuplicateInvoiceChecker::class)->check($submitted->invoice_number, $submitted->id);

        $this->assertNull($result);
    }

    public function test_normal_invoice_numbers_use_bank_specific_prefix(): void
    {
        $ybrdBase = EngineRequest::query()->where('reference', 'ENG-2026-YBRD-A001')->firstOrFail();
        $tiibBase = EngineRequest::query()->where('reference', 'ENG-2026-TIIB-A001')->firstOrFail();

        $this->assertStringStartsWith('INV-YBRD-', $ybrdBase->invoice_number);
        $this->assertStringStartsWith('INV-TIIB-', $tiibBase->invoice_number);
        $this->assertNotSame($ybrdBase->invoice_number_normalized, $tiibBase->invoice_number_normalized);
    }
}

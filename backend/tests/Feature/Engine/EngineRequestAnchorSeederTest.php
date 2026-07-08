<?php

namespace Tests\Feature\Engine;

use App\Models\EngineRequest;
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

class EngineRequestAnchorSeederTest extends TestCase
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
        ]);
    }

    public function test_seeds_exactly_56_anchors(): void
    {
        $this->seed(EngineRequestAnchorSeeder::class);

        $this->assertSame(SeederCatalog::ANCHOR_COUNT, EngineRequest::query()->count());
    }

    public function test_seeds_28_anchors_per_bank(): void
    {
        $this->seed(EngineRequestAnchorSeeder::class);

        $ybrdCount = EngineRequest::query()->where('reference', 'like', 'ENG-2026-YBRD-A%')->count();
        $tiibCount = EngineRequest::query()->where('reference', 'like', 'ENG-2026-TIIB-A%')->count();

        $this->assertSame(28, $ybrdCount);
        $this->assertSame(28, $tiibCount);
    }

    public function test_hook_constant_references_exist(): void
    {
        $this->seed(EngineRequestAnchorSeeder::class);

        $this->assertDatabaseHas('engine_requests', ['reference' => SeederCatalog::ANCHOR_SUBMITTED_NOTIFICATION]);
        $this->assertDatabaseHas('engine_requests', ['reference' => SeederCatalog::ANCHOR_SUPPORT_CLAIM_ACTIVE]);
        $this->assertDatabaseHas('engine_requests', ['reference' => SeederCatalog::ANCHOR_SCAN_PENDING]);
        $this->assertDatabaseHas('engine_requests', ['reference' => SeederCatalog::ANCHOR_DUPLICATE_YBRD]);
        $this->assertDatabaseHas('engine_requests', ['reference' => SeederCatalog::ANCHOR_DUPLICATE_TIIB]);
    }

    public function test_duplicate_pair_shares_normalized_invoice(): void
    {
        $this->seed(EngineRequestAnchorSeeder::class);

        $ybrd = EngineRequest::query()->where('reference', SeederCatalog::ANCHOR_DUPLICATE_YBRD)->firstOrFail();
        $tiib = EngineRequest::query()->where('reference', SeederCatalog::ANCHOR_DUPLICATE_TIIB)->firstOrFail();

        $this->assertSame($ybrd->invoice_number_normalized, $tiib->invoice_number_normalized);
    }

    public function test_rerun_is_idempotent(): void
    {
        $this->seed(EngineRequestAnchorSeeder::class);
        $this->seed(EngineRequestAnchorSeeder::class);

        $this->assertSame(SeederCatalog::ANCHOR_COUNT, EngineRequest::query()->count());
    }
}

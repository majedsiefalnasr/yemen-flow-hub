<?php

namespace Tests\Feature\Engine;

use App\Models\EngineRequest;
use Database\Seeders\BankSeeder;
use Database\Seeders\Catalog\SeederCatalog;
use Database\Seeders\EngineRequestAnchorSeeder;
use Database\Seeders\EngineRequestBulkSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ImportFinancingWorkflowSeeder;
use Database\Seeders\MerchantSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkflowActionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class EngineRequestBulkSeederTest extends TestCase
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

    public function test_skips_bulk_seeding_under_minimal_size(): void
    {
        config(['demo.seed_size' => 'minimal']);

        $this->seed(EngineRequestBulkSeeder::class);

        $this->assertSame(0, EngineRequest::query()->count());
    }

    #[Group('full-seed')]
    public function test_seeds_exactly_250_bulk_requests_under_full_size(): void
    {
        config(['demo.seed_size' => 'full']);

        $this->seed(EngineRequestBulkSeeder::class);

        $this->assertSame(SeederCatalog::BULK_COUNT, EngineRequest::query()->count());
    }

    #[Group('full-seed')]
    public function test_full_seed_totals_306_with_anchors(): void
    {
        config(['demo.seed_size' => 'full']);

        $this->seed(EngineRequestAnchorSeeder::class);
        $this->seed(EngineRequestBulkSeeder::class);

        $this->assertSame(SeederCatalog::TOTAL_COUNT, EngineRequest::query()->count());
    }

    #[Group('full-seed')]
    public function test_bulk_references_split_evenly_per_bank(): void
    {
        config(['demo.seed_size' => 'full']);

        $this->seed(EngineRequestBulkSeeder::class);

        $ybrdCount = EngineRequest::query()->where('reference', 'like', 'ENG-2026-YBRD-B%')->count();
        $tiibCount = EngineRequest::query()->where('reference', 'like', 'ENG-2026-TIIB-B%')->count();

        $this->assertSame(125, $ybrdCount);
        $this->assertSame(125, $tiibCount);
    }
}

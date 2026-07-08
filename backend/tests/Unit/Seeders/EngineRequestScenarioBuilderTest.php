<?php

namespace Tests\Unit\Seeders;

use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Merchant;
use App\Models\User;
use Database\Seeders\BankSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ImportFinancingWorkflowSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\Support\EngineRequestScenarioBuilder;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkflowActionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Smoke tests for EngineRequestScenarioBuilder against real catalog specs.
 */
class EngineRequestScenarioBuilderTest extends TestCase
{
    use RefreshDatabase;

    private EngineRequestScenarioBuilder $builder;

    private Bank $bank;

    private Merchant $merchant;

    private User $creator;

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
        ]);

        $this->builder = app(EngineRequestScenarioBuilder::class);
        $this->bank = Bank::query()->where('code', 'YBRD')->firstOrFail();
        $this->merchant = Merchant::create([
            'bank_id' => $this->bank->id,
            'name' => 'Test Merchant',
            'tax_number' => '12345',
        ]);
        $this->creator = User::query()->where('email', 'entry@ybrd.com.ye')->firstOrFail();
    }

    public function test_build_anchor_creates_valid_create_stage_request(): void
    {
        $catalog = require base_path('database/seeders/catalog/anchor-catalog.php');
        $spec = collect($catalog)->firstWhere('reference', 'ENG-2026-YBRD-A001');

        $request = $this->builder->buildAnchor($spec, $this->bank, $this->merchant, $this->creator);

        $this->assertSame('ENG-2026-YBRD-A001', $request->reference);
        $this->assertSame('ACTIVE', $request->status);
        $this->assertSame('INV-YBRD-10000', $request->invoice_number);
        $this->assertNotNull($request->invoice_number_normalized);
        $this->assertEquals(120000, (float) $request->amount);
        $this->assertDatabaseCount('workflow_history', 1);
    }

    public function test_build_anchor_creates_valid_completed_terminal_request(): void
    {
        $catalog = require base_path('database/seeders/catalog/anchor-catalog.php');
        $spec = collect($catalog)->firstWhere('reference', 'ENG-2026-YBRD-A014');

        $request = $this->builder->buildAnchor($spec, $this->bank, $this->merchant, $this->creator);

        $this->assertSame('CLOSED', $request->status);
        $this->assertNull($request->claimed_by);
    }

    public function test_build_anchor_is_idempotent_on_rerun(): void
    {
        $catalog = require base_path('database/seeders/catalog/anchor-catalog.php');
        $spec = collect($catalog)->firstWhere('reference', 'ENG-2026-YBRD-A001');

        $first = $this->builder->buildAnchor($spec, $this->bank, $this->merchant, $this->creator);
        $second = $this->builder->buildAnchor($spec, $this->bank, $this->merchant, $this->creator);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, EngineRequest::query()->where('reference', 'ENG-2026-YBRD-A001')->count());
        $this->assertDatabaseCount('workflow_history', 1);
    }

    public function test_build_anchor_with_claim_active_sets_claim_columns(): void
    {
        $catalog = require base_path('database/seeders/catalog/anchor-catalog.php');
        $spec = collect($catalog)->firstWhere('reference', 'ENG-2026-YBRD-A021');

        $request = $this->builder->buildAnchor($spec, $this->bank, $this->merchant, $this->creator);

        $this->assertNotNull($request->claimed_by);
        $this->assertNotNull($request->claim_expires_at);
        $this->assertTrue($request->claim_expires_at->isFuture());
    }

    public function test_build_anchor_with_document_replaced_seeds_superseded_and_active_versions(): void
    {
        $catalog = require base_path('database/seeders/catalog/anchor-catalog.php');
        $spec = collect($catalog)->firstWhere('reference', 'ENG-2026-YBRD-A028');

        $request = $this->builder->buildAnchor($spec, $this->bank, $this->merchant, $this->creator);

        $this->assertDatabaseCount('engine_request_documents', 2);
        $this->assertDatabaseHas('engine_request_documents', [
            'request_id' => $request->id,
            'version' => 1,
            'status' => 'superseded',
        ]);
        $this->assertDatabaseHas('engine_request_documents', [
            'request_id' => $request->id,
            'version' => 2,
            'status' => 'active',
        ]);
    }

    public function test_build_bulk_creates_valid_request(): void
    {
        $request = $this->builder->buildBulk(
            'ENG-2026-YBRD-B001',
            'create_active',
            $this->bank,
            $this->merchant,
            $this->creator,
            Carbon::now()->subDays(3),
        );

        $this->assertSame('ENG-2026-YBRD-B001', $request->reference);
        $this->assertSame('CREATE', $request->currentStage->code);
        $this->assertSame('ACTIVE', $request->status);
    }

    public function test_build_bulk_abandoned_via_api_sets_abandoned_status(): void
    {
        $request = $this->builder->buildBulk(
            'ENG-2026-YBRD-B999',
            'abandoned_via_api',
            $this->bank,
            $this->merchant,
            $this->creator,
            Carbon::now()->subDays(5),
        );

        $this->assertSame('ABANDONED', $request->status);
        $this->assertNull($request->claimed_by);
    }

    public function test_apply_duplicate_pair_shares_normalized_invoice(): void
    {
        $catalog = require base_path('database/seeders/catalog/anchor-catalog.php');
        $ybrdSpec = collect($catalog)->firstWhere('reference', 'ENG-2026-YBRD-A023');
        $tiibSpec = collect($catalog)->firstWhere('reference', 'ENG-2026-TIIB-A023');

        $tiibBank = Bank::query()->where('code', 'TIIB')->firstOrFail();
        $tiibMerchant = Merchant::create(['bank_id' => $tiibBank->id, 'name' => 'TIIB Merchant', 'tax_number' => '67890']);
        $tiibCreator = User::query()->where('email', 'entry@tiib.com.ye')->firstOrFail();

        $ybrdRequest = $this->builder->buildAnchor($ybrdSpec, $this->bank, $this->merchant, $this->creator);
        $tiibRequest = $this->builder->buildAnchor($tiibSpec, $tiibBank, $tiibMerchant, $tiibCreator);

        $this->builder->applyDuplicatePair($ybrdRequest, $tiibRequest, 'INV-DUP-SEED-001');

        $ybrdRequest->refresh();
        $tiibRequest->refresh();

        $this->assertSame($ybrdRequest->invoice_number_normalized, $tiibRequest->invoice_number_normalized);
    }
}

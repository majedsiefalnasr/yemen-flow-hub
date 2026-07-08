<?php

namespace Tests\Feature\Engine;

use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Merchant;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Database\Seeders\BankSeeder;
use Database\Seeders\Catalog\SeederCatalog;
use Database\Seeders\EngineAuxiliaryDemoSeeder;
use Database\Seeders\EngineRequestAnchorSeeder;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ImportFinancingWorkflowSeeder;
use Database\Seeders\MerchantSeeder;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkflowActionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Idempotency coverage per spec § Idempotency: clean seed, second run,
 * partial anchor set, pre-existing non-demo request, missing auxiliary
 * backfill.
 */
class DemoSeedIdempotencyTest extends TestCase
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
            NotificationTemplateSeeder::class,
        ]);
    }

    public function test_second_anchor_seed_run_does_not_duplicate_references(): void
    {
        $this->seed(EngineRequestAnchorSeeder::class);
        $this->seed(EngineRequestAnchorSeeder::class);

        $this->assertSame(SeederCatalog::ANCHOR_COUNT, EngineRequest::query()->count());
        $this->assertSame(
            SeederCatalog::ANCHOR_COUNT,
            EngineRequest::query()->distinct('reference')->count('reference')
        );
    }

    public function test_preexisting_non_demo_request_is_not_touched_by_anchor_seeding(): void
    {
        $bank = Bank::query()->where('code', 'YBRD')->firstOrFail();
        $merchant = Merchant::query()->where('bank_id', $bank->id)->firstOrFail();
        $creator = User::query()->where('bank_id', $bank->id)->firstOrFail();

        $definition = WorkflowDefinition::query()->where('code', 'IMPORT_FINANCING')->firstOrFail();
        $version = $definition->versions()->firstOrFail();
        $createStage = $version->stages()->where('code', 'CREATE')->firstOrFail();

        $nonDemo = EngineRequest::query()->create([
            'workflow_version_id' => $version->id,
            'current_stage_id' => $createStage->id,
            'reference' => 'MANUAL-QA-0001',
            'status' => 'ACTIVE',
            'created_by' => $creator->id,
            'bank_id' => $bank->id,
            'merchant_id' => $merchant->id,
            'data' => [],
            'version' => 1,
        ]);

        $this->seed(EngineRequestAnchorSeeder::class);

        $this->assertDatabaseHas('engine_requests', ['id' => $nonDemo->id, 'reference' => 'MANUAL-QA-0001']);
        $this->assertSame(SeederCatalog::ANCHOR_COUNT + 1, EngineRequest::query()->count());
    }

    public function test_missing_auxiliary_rows_backfill_after_anchors_seeded_late(): void
    {
        $this->seed(EngineRequestAnchorSeeder::class);
        $this->seed(EngineAuxiliaryDemoSeeder::class);

        $this->assertGreaterThan(0, DB::table('engine_notifications')->count());
        $this->assertGreaterThan(0, DB::table('email_deliveries')->count());

        // Rerunning auxiliary after anchors already exist must not duplicate rows.
        $notificationCountBefore = DB::table('engine_notifications')->count();
        $emailCountBefore = DB::table('email_deliveries')->count();

        $this->seed(EngineAuxiliaryDemoSeeder::class);

        $this->assertSame($notificationCountBefore, DB::table('engine_notifications')->count());
        $this->assertSame($emailCountBefore, DB::table('email_deliveries')->count());
    }

    public function test_demo_references_identifiable_by_pattern(): void
    {
        $this->seed(EngineRequestAnchorSeeder::class);

        $nonMatching = EngineRequest::query()
            ->where('reference', 'not like', 'ENG-2026-%')
            ->count();

        $this->assertSame(0, $nonMatching);
    }
}

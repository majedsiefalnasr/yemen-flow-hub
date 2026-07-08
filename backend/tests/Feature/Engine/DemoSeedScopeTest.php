<?php

namespace Tests\Feature\Engine;

use App\Models\EngineRequest;
use App\Models\User;
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
 * DataScope coverage per spec § DataScope demo coverage (WP-7): bank
 * isolation and cross-bank duplicate masking on the seeded anchor set.
 */
class DemoSeedScopeTest extends TestCase
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

    public function test_bank_user_sees_only_own_bank_requests(): void
    {
        $ybrdEntry = User::query()->where('email', 'entry@ybrd.com.ye')->firstOrFail();

        $visible = EngineRequest::query()->forUser($ybrdEntry)->pluck('reference');

        $this->assertTrue($visible->every(fn ($ref) => str_starts_with($ref, 'ENG-2026-YBRD-')));
        $this->assertGreaterThan(0, $visible->count());
    }

    public function test_bank_user_does_not_see_other_bank_requests(): void
    {
        $ybrdEntry = User::query()->where('email', 'entry@ybrd.com.ye')->firstOrFail();

        $visible = EngineRequest::query()->forUser($ybrdEntry)->pluck('reference');

        $this->assertFalse($visible->contains(fn ($ref) => str_starts_with($ref, 'ENG-2026-TIIB-')));
    }

    public function test_national_committee_user_sees_requests_across_both_banks(): void
    {
        $director = User::query()->where('email', 'director@cby.gov.ye')->firstOrFail();

        $visible = EngineRequest::query()->forUser($director)->pluck('reference');

        $this->assertTrue($visible->contains(fn ($ref) => str_starts_with($ref, 'ENG-2026-YBRD-')));
        $this->assertTrue($visible->contains(fn ($ref) => str_starts_with($ref, 'ENG-2026-TIIB-')));
    }

    public function test_cross_bank_duplicate_pair_visible_within_each_banks_own_scope(): void
    {
        $ybrdEntry = User::query()->where('email', 'entry@ybrd.com.ye')->firstOrFail();
        $tiibEntry = User::query()->where('email', 'entry@tiib.com.ye')->firstOrFail();

        $ybrdVisible = EngineRequest::query()->forUser($ybrdEntry)->pluck('reference');
        $tiibVisible = EngineRequest::query()->forUser($tiibEntry)->pluck('reference');

        $this->assertTrue($ybrdVisible->contains(SeederCatalog::ANCHOR_DUPLICATE_YBRD));
        $this->assertFalse($ybrdVisible->contains(SeederCatalog::ANCHOR_DUPLICATE_TIIB));

        $this->assertTrue($tiibVisible->contains(SeederCatalog::ANCHOR_DUPLICATE_TIIB));
        $this->assertFalse($tiibVisible->contains(SeederCatalog::ANCHOR_DUPLICATE_YBRD));
    }

    public function test_both_banks_have_seeded_requests(): void
    {
        $ybrdCount = EngineRequest::query()->where('reference', 'like', 'ENG-2026-YBRD-%')->count();
        $tiibCount = EngineRequest::query()->where('reference', 'like', 'ENG-2026-TIIB-%')->count();

        $this->assertSame(28, $ybrdCount);
        $this->assertSame(28, $tiibCount);
    }
}

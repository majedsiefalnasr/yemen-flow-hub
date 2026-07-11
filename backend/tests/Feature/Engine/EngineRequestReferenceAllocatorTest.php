<?php

namespace Tests\Feature\Engine;

use App\Enums\StageAccessLevel;
use App\Enums\WorkflowVersionState;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Merchant;
use App\Models\Organization;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Guards API-003 / API-003b: request creation must allocate a monotonically
 * increasing, unique reference regardless of digit width. The reference is
 * produced by EngineRequestReferenceAllocator from an atomic per-year sequence
 * row (API-003b), which replaced the old MAX(CAST(suffix))+1 derivation over
 * engine_requests — that derivation raced every concurrent creator on the same
 * unique-index gap (deadlocks under load) and, before the numeric cast, also
 * mis-ordered a 7-digit suffix below a 6-digit one. These tests pin the current
 * contract: correct 6→7 digit width transition, and derivation from the
 * sequence row rather than a scan of existing references. Extreme-contention
 * deadlock-freedom is proven separately by `perf:load-scenario --concurrency`
 * against real MySQL (the SQLite suite cannot reproduce InnoDB contention).
 */
class EngineRequestReferenceAllocatorTest extends TestCase
{
    use RefreshDatabase;

    private User $executor;

    private WorkflowVersion $version;

    private WorkflowStage $initialStage;

    private Bank $bank;

    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);
        $this->setUpWorkflow();
    }

    private function setUpWorkflow(): void
    {
        $bankOrg = Organization::where('code', 'commercial_banks')->first();

        $this->bank = Bank::create([
            'name' => 'Ref Allocator Bank',
            'code' => 'RAB',
            'is_active' => true,
            'organization_id' => $bankOrg->id,
        ]);

        $entryRole = Role::where('code', 'intake')->first();
        $entryTeam = Team::where('code', 'entry')->first();

        $this->executor = User::create([
            'name' => 'Ref Allocator Executor',
            'email' => 'executor@refallocator.test',
            'password' => bcrypt('password'),
            'bank_id' => $this->bank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $this->executor->teams()->attach($entryTeam);
        $this->executor->roles()->attach($entryRole);

        $this->merchant = Merchant::create([
            'bank_id' => $this->bank->id,
            'name' => 'Ref Allocator Merchant',
            'tax_number' => '111222333',
            'status' => 'ACTIVE',
        ]);

        $definition = WorkflowDefinition::create([
            'code' => 'REF_ALLOCATOR_WF',
            'name' => 'Ref Allocator Workflow',
            'is_active' => true,
        ]);

        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'state' => WorkflowVersionState::PUBLISHED,
            'published_at' => now(),
            'version' => 1,
        ]);

        $this->initialStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'INTAKE',
            'name' => 'Intake',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'sla_duration_minutes' => 60,
            'version' => 1,
        ]);

        StagePermission::create([
            'stage_id' => $this->initialStage->id,
            'organization_id' => $bankOrg->id,
            'role_id' => $entryRole->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Intake',
            'version' => 1,
        ]);
    }

    private function createRequest(array $data = []): TestResponse
    {
        return $this->actingAs($this->executor)->postJson('/api/v1/engine-requests', array_merge([
            'workflow_version_id' => $this->version->id,
            'merchant_id' => $this->merchant->id,
            'data' => ['amount' => 100, 'currency' => 'USD'],
        ], $data));
    }

    public function test_allocates_sequential_reference_past_six_digit_boundary(): void
    {
        $year = now()->year;

        // API-003b: the reference now derives from the per-year sequence row,
        // not from MAX() over engine_requests. Set the sequence to 999999 so the
        // next allocation must roll into the 7th digit. sprintf('%06d', 1000000)
        // is '1000000' (7 chars) — the pad is a MINIMUM width, so the reference
        // correctly widens instead of truncating or wrapping. This is the
        // width-transition the old MAX(CAST)-based derivation got wrong; the
        // sequence allocator is correct at any digit width by construction.
        DB::table('engine_request_reference_sequences')->updateOrInsert(
            ['year' => (string) $year],
            ['last_value' => 999999, 'created_at' => now(), 'updated_at' => now()],
        );

        $response = $this->createRequest();

        $response->assertCreated();
        $newReference = $response->json('data.reference');

        $this->assertSame(
            sprintf('ENG-%d-%06d', $year, 1000000),
            $newReference,
            'Next reference must continue the numeric sequence past the 6-digit width, not truncate or wrap.'
        );
    }

    public function test_reference_derives_from_sequence_row_not_existing_rows(): void
    {
        $year = now()->year;

        // A high-numbered engine_requests reference must NOT influence the next
        // allocation — the sequence row is the single source of truth. This is
        // the structural guarantee that removes the old MAX()-scan race entirely.
        EngineRequest::withoutEvents(function () use ($year) {
            EngineRequest::create([
                'reference' => sprintf('ENG-%d-%06d', $year, 500000),
                'workflow_version_id' => $this->version->id,
                'current_stage_id' => $this->initialStage->id,
                'stage_entered_at' => now(),
                'status' => 'ACTIVE',
                'created_by' => $this->executor->id,
                'bank_id' => $this->bank->id,
                'data' => [],
                'version' => 1,
            ]);
        });

        // Sequence row still at its seeded baseline (0), so the next reference is
        // 1 — independent of the 500000-numbered row sitting in engine_requests.
        $response = $this->createRequest();

        $response->assertCreated();
        $this->assertSame(
            sprintf('ENG-%d-%06d', $year, 1),
            $response->json('data.reference'),
            'Allocation must come from the sequence row, not a scan of existing references.'
        );
    }

    public function test_concurrent_style_retries_still_yield_unique_references(): void
    {
        // Sequential creates exercise the same retry-on-duplicate-key path a
        // true concurrent race would hit; each must resolve to a distinct ref.
        $refs = [];
        for ($i = 0; $i < 5; $i++) {
            $response = $this->createRequest(['data' => ['amount' => 100 + $i]]);
            $response->assertCreated();
            $refs[] = $response->json('data.reference');
        }

        $this->assertCount(5, array_unique($refs));
    }
}

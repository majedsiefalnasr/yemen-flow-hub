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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Guards API-003: createWithUniqueReference() must allocate a monotonically
 * increasing sequence regardless of digit width. The pre-fix implementation
 * used a lexicographic string MAX('reference'), which mis-orders a 7-digit
 * suffix below any existing 6-digit one ('1000000' < '999999' as strings) —
 * once the yearly sequence crosses 999999, every subsequent create recomputes
 * the same stale max and permanently fails REFERENCE_ALLOCATION_FAILED.
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

        // Seed a 6-digit reference (999999) plus a run of 7-digit references
        // immediately above it — cheap stand-in for a real year that crossed
        // the boundary. A lexicographic string MAX() compares '999999' and
        // '1000000'+ as different-length strings and always picks '999999'
        // (the shorter string sorts higher: '9' > '1' at the first differing
        // position), so every create re-derives sequence 1000000 regardless
        // of how many 7-digit rows already exist. The single retry-with-
        // attempt-offset in createWithUniqueReference can paper over exactly
        // one stale collision, so this seeds enough 7-digit rows (6) to
        // exhaust the method's full 5-attempt retry budget and force the
        // permanent REFERENCE_ALLOCATION_FAILED the finding describes.
        EngineRequest::withoutEvents(function () use ($year) {
            foreach (array_merge([999999], range(1000000, 1000005)) as $sequence) {
                EngineRequest::create([
                    'reference' => sprintf('ENG-%d-%06d', $year, $sequence),
                    'workflow_version_id' => $this->version->id,
                    'current_stage_id' => $this->initialStage->id,
                    'stage_entered_at' => now(),
                    'status' => 'ACTIVE',
                    'created_by' => $this->executor->id,
                    'bank_id' => $this->bank->id,
                    'data' => [],
                    'version' => 1,
                ]);
            }
        });

        $response = $this->createRequest();

        $response->assertCreated();
        $newReference = $response->json('data.reference');

        $this->assertSame(
            sprintf('ENG-%d-%06d', $year, 1000006),
            $newReference,
            'Next reference must continue the numeric sequence past the 6-digit width, not silently collide or regress.'
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

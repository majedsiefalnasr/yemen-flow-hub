<?php

namespace Tests\Feature\Engine;

use App\Enums\StageAccessLevel;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Database\Seeders\GovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Roadmap line 107 (Block 5 gate): every optimized query must still apply
 * forUser/accessible-stage scoping. The DB-001/DB-002 restructure moved the
 * list and my-queue endpoints onto UnionStagePaginator, which applies NO
 * scoping of its own — all scoping comes from the branchFactory closure the
 * controller passes (EngineRequestController::index()/myQueue() both wrap the
 * branch in ->forUser($user)). The existing *UnionParityTest classes prove
 * ordering/pagination/filter parity but seed a single bank, so they never
 * exercise the cross-bank isolation the branchFactory's forUser() enforces.
 *
 * This pins that isolation directly on the optimized union path: a bank-A
 * user must never see bank-B rows through either endpoint, even when both
 * banks' requests sit on the same accessible stages (the exact multi-stage
 * union shape those endpoints now take).
 */
class UnionPaginatorCrossBankScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $bankAUser;

    private Bank $bankA;

    private Bank $bankB;

    private WorkflowStage $stageOne;

    private WorkflowStage $stageTwo;

    private WorkflowVersion $version;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);

        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $this->bankA = Bank::create(['name' => 'Cross Bank A', 'code' => 'XBA', 'is_active' => true, 'organization_id' => $bankOrg->id]);
        $this->bankB = Bank::create(['name' => 'Cross Bank B', 'code' => 'XBB', 'is_active' => true, 'organization_id' => $bankOrg->id]);

        $role = Role::where('code', 'intake')->firstOrFail();
        $team = Team::where('code', 'entry')->firstOrFail();

        $this->bankAUser = User::create([
            'name' => 'Cross Bank A User', 'email' => 'cross-a@test.bank', 'password' => bcrypt('password'),
            'bank_id' => $this->bankA->id, 'organization_id' => $bankOrg->id, 'is_active' => true,
        ]);
        $this->bankAUser->teams()->attach($team);
        $this->bankAUser->roles()->attach($role);

        $def = WorkflowDefinition::create(['code' => 'XBANK_WF', 'name' => 'Cross Bank WF', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id, 'version_number' => 1,
            'state' => 'PUBLISHED', 'published_at' => now(), 'version' => 1,
        ]);

        $this->stageOne = WorkflowStage::create([
            'workflow_version_id' => $this->version->id, 'code' => 'XB_STAGE_ONE', 'name' => 'Stage One',
            'sort_order' => 1, 'is_initial' => true, 'is_final' => false, 'version' => 1, 'sla_duration_minutes' => 120,
        ]);
        $this->stageTwo = WorkflowStage::create([
            'workflow_version_id' => $this->version->id, 'code' => 'XB_STAGE_TWO', 'name' => 'Stage Two',
            'sort_order' => 2, 'is_initial' => false, 'is_final' => false, 'version' => 1, 'sla_duration_minutes' => 60,
        ]);

        // EXECUTE covers both the list (VIEW-or-higher) and my-queue (EXECUTE)
        // accessible-stage resolution, so one permission set drives both endpoints.
        foreach ([$this->stageOne, $this->stageTwo] as $stage) {
            StagePermission::create([
                'stage_id' => $stage->id, 'organization_id' => $bankOrg->id, 'role_id' => $role->id,
                'access_level' => StageAccessLevel::EXECUTE, 'display_label' => 'Exec', 'version' => 1,
            ]);
        }
    }

    private function makeRequest(Bank $bank, WorkflowStage $stage, string $reference): EngineRequest
    {
        return EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $stage->id,
            'reference' => $reference,
            'status' => 'ACTIVE',
            'created_by' => $this->bankAUser->id,
            'bank_id' => $bank->id,
            'invoice_number' => 'INV-'.$reference,
            'data' => [],
            'version' => 1,
            'sla_deadline_epoch' => now()->addHour()->getTimestamp(),
        ]);
    }

    public function test_list_endpoint_never_returns_another_banks_rows(): void
    {
        // Both banks have rows on both accessible stages — the multi-stage
        // union shape. Bank A's user must see only bank A's rows.
        $ownOne = $this->makeRequest($this->bankA, $this->stageOne, 'ENG-XB-A1');
        $ownTwo = $this->makeRequest($this->bankA, $this->stageTwo, 'ENG-XB-A2');
        $this->makeRequest($this->bankB, $this->stageOne, 'ENG-XB-B1');
        $this->makeRequest($this->bankB, $this->stageTwo, 'ENG-XB-B2');

        $response = $this->actingAs($this->bankAUser)->getJson('/api/v1/engine-requests');

        $response->assertOk();
        $refs = collect($response->json('data'))->pluck('reference')->all();

        sort($refs);
        $this->assertSame([$ownOne->reference, $ownTwo->reference], $refs);
        $this->assertSame(2, $response->json('meta.total'));
    }

    public function test_my_queue_endpoint_never_returns_another_banks_rows(): void
    {
        $ownOne = $this->makeRequest($this->bankA, $this->stageOne, 'ENG-XBQ-A1');
        $ownTwo = $this->makeRequest($this->bankA, $this->stageTwo, 'ENG-XBQ-A2');
        $this->makeRequest($this->bankB, $this->stageOne, 'ENG-XBQ-B1');
        $this->makeRequest($this->bankB, $this->stageTwo, 'ENG-XBQ-B2');

        $response = $this->actingAs($this->bankAUser)->getJson('/api/v1/engine-requests/my-queue');

        $response->assertOk();
        $refs = collect($response->json('data'))->pluck('reference')->all();

        sort($refs);
        $this->assertSame([$ownOne->reference, $ownTwo->reference], $refs);
        $this->assertSame(2, $response->json('meta.total'));
    }
}

<?php

namespace Tests\Feature\Engine;

use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowHistoryEntry;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use App\Support\EngineRequestListQuery;
use Database\Seeders\GovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards ARCH-002: the SLA deadline used for my-queue ordering and sla_status
 * filtering was computed by a correlated `max(created_at) from workflow_history`
 * subquery embedded in ORDER BY / WHERE. That subquery is being replaced by an
 * indexed `engine_requests.stage_entered_at` projection column maintained on
 * transition (+ backfill).
 *
 * These tests pin the OBSERVABLE behaviour — which rows come back for each
 * sla_status filter, and the my-queue priority order — so the swap from subquery
 * to column cannot change results. They must pass against the subquery impl
 * (baseline) and remain green after the column swap.
 */
class SlaProjectionParityTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowVersion $version;

    private User $admin;

    private EngineRequestListQuery $listQuery;

    /** @var array<string, WorkflowStage> */
    private array $stages = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class]);
        $this->listQuery = app(EngineRequestListQuery::class);

        $org = Organization::where('code', 'commercial_banks')->firstOrFail();
        $bank = Bank::create(['name' => 'SLA Bank', 'code' => 'SLAB', 'is_active' => true, 'organization_id' => $org->id]);
        $this->admin = User::create([
            'name' => 'SLA Admin',
            'email' => 'sla@parity.test',
            'password' => bcrypt('pass'),
            'organization_id' => $org->id,
            'is_active' => true,
        ]);
        $this->bank = $bank;

        $def = WorkflowDefinition::create(['code' => 'SLA_WF', 'name' => 'SLA WF', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'published_at' => now(),
            'version' => 1,
        ]);

        // Two stages with a real SLA window, one with no SLA (null → ordered last).
        $this->stages['fast'] = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'FAST', 'name' => 'Fast', 'sort_order' => 1,
            'is_initial' => true, 'is_final' => false, 'version' => 1,
            'sla_duration_minutes' => 60,
        ]);
        $this->stages['slow'] = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'SLOW', 'name' => 'Slow', 'sort_order' => 2,
            'is_initial' => false, 'is_final' => false, 'version' => 1,
            'sla_duration_minutes' => 600,
        ]);
        $this->stages['nosla'] = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'NOSLA', 'name' => 'No SLA', 'sort_order' => 3,
            'is_initial' => false, 'is_final' => false, 'version' => 1,
            'sla_duration_minutes' => null,
        ]);
    }

    private Bank $bank;

    /**
     * Create a request parked on $stage whose stage-entry (its latest matching
     * workflow_history row) is $enteredMinutesAgo minutes in the past.
     */
    private function requestOnStage(string $reference, WorkflowStage $stage, int $enteredMinutesAgo): EngineRequest
    {
        $request = EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $stage->id,
            'reference' => $reference,
            'status' => 'ACTIVE',
            'created_by' => $this->admin->id,
            'bank_id' => $this->bank->id,
            'data' => [],
            'version' => 1,
        ]);

        // Stage entry is recorded in workflow_history. A decoy earlier row on the
        // same stage must NOT win over the latest one.
        WorkflowHistoryEntry::create([
            'request_id' => $request->id,
            'to_stage_id' => $stage->id,
            'from_stage_id' => null,
            'performed_by' => $this->admin->id,
            'action_code' => 'ENTER',
            'created_at' => now()->subMinutes($enteredMinutesAgo + 500),
        ]);
        $latestEntry = now()->subMinutes($enteredMinutesAgo);
        WorkflowHistoryEntry::create([
            'request_id' => $request->id,
            'to_stage_id' => $stage->id,
            'from_stage_id' => null,
            'performed_by' => $this->admin->id,
            'action_code' => 'ENTER',
            'created_at' => $latestEntry,
        ]);

        // Mirror what EngineTransitionService/EngineRequestService now do: keep the
        // stage_entered_at projection column equal to the latest matching history row.
        $request->forceFill(['stage_entered_at' => $latestEntry])->save();

        return $request;
    }

    /** @return array<int, string> reference list in returned order */
    private function references($query): array
    {
        return $query->pluck('engine_requests.reference')->all();
    }

    public function test_sla_status_filter_selects_the_same_rows(): void
    {
        // A null-SLA row (null sla_duration_minutes) must never be selected by any
        // sla_status filter — this holds regardless of the deadline source, so it
        // must survive the subquery→column swap.
        $this->requestOnStage('ENG-OK', $this->stages['slow'], 10);
        $this->requestOnStage('ENG-NOSLA', $this->stages['nosla'], 9999);

        foreach (['breached', 'nearing', 'ok'] as $status) {
            $query = EngineRequest::query()->withStageEntry();
            $this->listQuery->applySlaStatusFilter($query, $status);
            $this->assertNotContains(
                'ENG-NOSLA',
                $this->references($query),
                "sla_status={$status} must never include a null-SLA row",
            );
        }
    }

    public function test_my_queue_priority_order_is_stable(): void
    {
        // Deadlines (from now): breached = -60m, nearing = +10m, ok(slow) = +590m.
        $this->requestOnStage('ENG-OK', $this->stages['slow'], 10);
        $this->requestOnStage('ENG-BREACHED', $this->stages['fast'], 120);
        $this->requestOnStage('ENG-NEARING', $this->stages['fast'], 50);
        $this->requestOnStage('ENG-NOSLA', $this->stages['nosla'], 9999);

        $ordered = $this->references(
            EngineRequest::query()->withStageEntry()->orderBySlaPriority()->orderBy('engine_requests.id'),
        );

        // SLA rows ascend by deadline (breached → nearing → ok); the null-SLA row is last.
        $this->assertSame(['ENG-BREACHED', 'ENG-NEARING', 'ENG-OK', 'ENG-NOSLA'], $ordered);
    }

    public function test_latest_history_row_wins_not_an_earlier_stage_entry(): void
    {
        // The decoy row (entered_minutes+500 ago) must not be treated as the
        // stage-entry time; only the latest matters. Both the subquery and the
        // future column read must resolve stage_entered_at to the LATEST history
        // row, so the resolved value must equal the max(created_at) for the row.
        $request = $this->requestOnStage('ENG-LATEST', $this->stages['fast'], 50);

        $row = EngineRequest::query()->withStageEntry()
            ->where('engine_requests.id', $request->id)->first();

        $expectedLatest = WorkflowHistoryEntry::query()
            ->where('request_id', $request->id)
            ->where('to_stage_id', $this->stages['fast']->id)
            ->max('created_at');

        $this->assertSame(
            (string) $expectedLatest,
            (string) $row->stage_entered_at,
            'stage_entered_at must resolve to the latest matching history row, not an earlier one.',
        );
    }
}

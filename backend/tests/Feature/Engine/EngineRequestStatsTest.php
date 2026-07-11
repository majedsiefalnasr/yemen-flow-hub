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
use App\Models\WorkflowHistoryEntry;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use App\Services\Workflow\EngineRequestStatsService;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EngineRequestStatsTest extends TestCase
{
    use RefreshDatabase;

    private User $executor;

    private Bank $bank;

    private WorkflowVersion $version;

    private WorkflowStage $stage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $entryRole = Role::where('code', 'intake')->firstOrFail();
        $entryTeam = Team::where('code', 'entry')->firstOrFail();

        $this->bank = Bank::create([
            'name' => 'Stats Bank',
            'code' => 'STB',
            'is_active' => true,
            'organization_id' => $bankOrg->id,
        ]);

        $this->executor = User::create([
            'name' => 'Executor',
            'email' => 'executor@stats.test',
            'password' => bcrypt('password'),
            'bank_id' => $this->bank->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $this->executor->teams()->attach($entryTeam);
        $this->executor->roles()->attach($entryRole);

        $def = WorkflowDefinition::create([
            'code' => 'STATS_WF',
            'name' => 'Stats Workflow',
            'is_active' => true,
        ]);

        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'published_at' => now(),
            'version' => 1,
        ]);

        $this->stage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'ENTRY',
            'name' => 'Entry',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'sla_duration_minutes' => 60,
            'version' => 1,
        ]);

        StagePermission::create([
            'stage_id' => $this->stage->id,
            'organization_id' => $bankOrg->id,
            'role_id' => $entryRole->id,
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'Entry View',
            'version' => 1,
        ]);

        StagePermission::create([
            'stage_id' => $this->stage->id,
            'organization_id' => $bankOrg->id,
            'role_id' => $entryRole->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Entry Execute',
            'version' => 1,
        ]);
    }

    private function seedRequest(
        string $reference,
        string $invoiceNumber = 'INV-DEFAULT',
        bool $breached = false,
        ?int $claimedBy = null,
        string $status = 'ACTIVE',
    ): EngineRequest {
        $request = EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $this->stage->id,
            'reference' => $reference,
            'status' => $status,
            'created_by' => $this->executor->id,
            'bank_id' => $this->bank->id,
            'invoice_number' => $invoiceNumber,
            'claimed_by' => $claimedBy,
            'data' => [],
            'version' => 1,
        ]);

        WorkflowHistoryEntry::create([
            'request_id' => $request->id,
            'from_stage_id' => null,
            'to_stage_id' => $this->stage->id,
            'performed_by' => $this->executor->id,
            'created_at' => $this->stageEnteredAt($breached),
        ]);

        return $request;
    }

    private function stageEnteredAt(bool $breached): string
    {
        $modifier = $breached ? '-3 hours' : '-5 minutes';

        return DB::selectOne("select datetime('now', '{$modifier}') as entered_at")->entered_at;
    }

    public function test_stats_reflects_full_scoped_dataset_not_current_page(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $breached = $i <= 5;
            $unclaimed = $i <= 3;
            $this->seedRequest(
                sprintf('ENG-2026-%05d', $i),
                sprintf('INV-%05d', $i),
                breached: $breached,
                claimedBy: $unclaimed ? null : $this->executor->id,
            );
        }

        $this->actingAs($this->executor)
            ->getJson('/api/v1/engine-requests/stats?scope=all')
            ->assertOk()
            ->assertJsonPath('data.total', 30)
            ->assertJsonPath('data.active', 30)
            ->assertJsonPath('data.breached_sla', 5)
            ->assertJsonPath('data.unclaimed_active', 3);
    }

    public function test_stats_honors_search_filter(): void
    {
        $this->seedRequest('ENG-ALPHA-001', 'INV-ALPHA');
        $this->seedRequest('ENG-BETA-001', 'INV-BETA');

        $this->actingAs($this->executor)
            ->getJson('/api/v1/engine-requests/stats?scope=all&search=INV-ALPHA')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_stats_queue_scope_matches_my_queue_visibility(): void
    {
        $this->seedRequest('ENG-QUEUE-001', 'INV-Q-1');
        $this->seedRequest('ENG-QUEUE-002', 'INV-Q-2', status: 'CLOSED');

        $stats = $this->actingAs($this->executor)
            ->getJson('/api/v1/engine-requests/stats?scope=queue')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['total', 'active', 'breached_sla', 'nearing_sla', 'unclaimed_active', 'by_status'],
            ]);

        $queue = $this->actingAs($this->executor)
            ->getJson('/api/v1/engine-requests/my-queue')
            ->assertOk();

        $this->assertSame($queue->json('meta.total'), $stats->json('data.total'));
    }

    /**
     * API-UI-001 regression: the by_status grouped pass must project only the
     * grouped column and aggregates. The suite runs on SQLite, which ignores
     * ONLY_FULL_GROUP_BY, so a nonaggregated `engine_requests.*` projection
     * beside `GROUP BY status` passes here yet raises MySQL 1055 (a 500 that
     * drove the client into a retry storm). Asserting on `aggregate()`'s own
     * compiled SQL keeps the guard engine-independent.
     */
    public function test_by_status_grouped_query_does_not_project_nonaggregated_columns(): void
    {
        $service = app(EngineRequestStatsService::class);
        $request = Request::create('/api/v1/engine-requests/stats', 'GET', ['scope' => 'all']);

        // Normalize away engine-specific identifier quoting (` for MySQL, " for
        // SQLite) so the assertions hold on any driver the suite runs under.
        $normalize = fn (string $sql): string => strtolower(str_replace(['`', '"'], '', $sql));

        $captured = [];
        DB::listen(function ($query) use (&$captured, $normalize): void {
            $sql = $normalize($query->sql);
            if (str_contains($sql, 'group by')) {
                $captured[] = $sql;
            }
        });

        $service->aggregate($this->executor, $request, 'all');

        $grouped = collect($captured)->first(fn (string $sql) => str_contains($sql, 'group by engine_requests.status'));
        $this->assertNotNull($grouped, 'aggregate() did not run the GROUP BY status pass.');
        $this->assertStringNotContainsString('engine_requests.*', $grouped, 'Grouped stats query still projects engine_requests.* alongside GROUP BY status (MySQL 1055).');
        $this->assertStringNotContainsString('stage_entered_at', $grouped, 'Grouped stats query still projects the stage-entry column alongside GROUP BY status.');
    }

    /**
     * API-UI-001 regression: the SLA "nearing" window built a `MAX(1, CAST(x AS
     * INTEGER))` expression that only parses on SQLite; on MySQL it raised 1064
     * (scalar MAX / CAST-INTEGER are SQLite-only), so the nearing_sla metric — and
     * thus the whole stats endpoint — 500'd. The window is now resolved through the
     * driver-branched EngineRequest::nearingWindowSql(). Every SLA filter must run
     * on whichever engine the suite uses, and the endpoint must stay 200.
     */
    public function test_stats_endpoint_survives_sla_status_filters(): void
    {
        $this->seedRequest('ENG-SLA-BREACHED', 'INV-SLA-1', breached: true);
        $this->seedRequest('ENG-SLA-OK', 'INV-SLA-2');

        foreach (['breached', 'nearing', 'ok'] as $slaStatus) {
            $this->actingAs($this->executor)
                ->getJson("/api/v1/engine-requests/stats?scope=all&sla_status={$slaStatus}")
                ->assertOk()
                ->assertJsonStructure(['data' => ['total', 'breached_sla', 'nearing_sla']]);
        }
    }
}

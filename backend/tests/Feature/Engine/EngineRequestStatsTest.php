<?php

namespace Tests\Feature\Engine;

use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
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
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'role' => UserRole::DATA_ENTRY,
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
}

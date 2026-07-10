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

class MyQueueUnionParityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Bank $bank;

    private WorkflowVersion $version;

    private WorkflowStage $stageOne;

    private WorkflowStage $stageTwo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);

        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $this->bank = Bank::create(['name' => 'MyQueue Union Bank', 'code' => 'MQUB', 'is_active' => true, 'organization_id' => $bankOrg->id]);

        $role = Role::where('code', 'intake')->firstOrFail();
        $team = Team::where('code', 'entry')->firstOrFail();

        $this->user = User::create([
            'name' => 'MyQueue Union User', 'email' => 'myqueue-union@test.bank', 'password' => bcrypt('password'),
            'bank_id' => $this->bank->id, 'organization_id' => $bankOrg->id, 'is_active' => true,
        ]);
        $this->user->teams()->attach($team);
        $this->user->roles()->attach($role);

        $def = WorkflowDefinition::create(['code' => 'MQ_UNION_WF', 'name' => 'MyQueue Union WF', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id, 'version_number' => 1,
            'state' => 'PUBLISHED', 'published_at' => now(), 'version' => 1,
        ]);

        $this->stageOne = WorkflowStage::create([
            'workflow_version_id' => $this->version->id, 'code' => 'MQ_STAGE_ONE', 'name' => 'Stage One',
            'sort_order' => 1, 'is_initial' => true, 'is_final' => false, 'version' => 1,
            'sla_duration_minutes' => 120,
        ]);
        $this->stageTwo = WorkflowStage::create([
            'workflow_version_id' => $this->version->id, 'code' => 'MQ_STAGE_TWO', 'name' => 'Stage Two',
            'sort_order' => 2, 'is_initial' => false, 'is_final' => false, 'version' => 1,
            'sla_duration_minutes' => 60,
        ]);

        foreach ([$this->stageOne, $this->stageTwo] as $stage) {
            StagePermission::create([
                'stage_id' => $stage->id, 'organization_id' => $bankOrg->id, 'role_id' => $role->id,
                'access_level' => StageAccessLevel::EXECUTE, 'display_label' => 'Exec', 'version' => 1,
            ]);
        }
    }

    private function makeRequest(WorkflowStage $stage, string $reference, int $slaDeadlineEpoch): EngineRequest
    {
        return EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $stage->id,
            'reference' => $reference,
            'status' => 'ACTIVE',
            'created_by' => $this->user->id,
            'bank_id' => $this->bank->id,
            'invoice_number' => 'INV-'.$reference,
            'data' => [],
            'version' => 1,
            'sla_deadline_epoch' => $slaDeadlineEpoch,
        ]);
    }

    public function test_my_queue_across_two_accessible_stages_orders_by_sla_priority(): void
    {
        $breachedOnStageOne = $this->makeRequest($this->stageOne, 'ENG-MQ-BREACH', now()->subHour()->getTimestamp());
        $nearFutureOnStageTwo = $this->makeRequest($this->stageTwo, 'ENG-MQ-NEAR', now()->addMinutes(10)->getTimestamp());
        $farFutureOnStageOne = $this->makeRequest($this->stageOne, 'ENG-MQ-FAR', now()->addDay()->getTimestamp());

        $response = $this->actingAs($this->user)->getJson('/api/v1/engine-requests/my-queue');

        $response->assertOk();
        $refs = collect($response->json('data'))->pluck('reference')->all();

        $this->assertSame(
            [$breachedOnStageOne->reference, $nearFutureOnStageTwo->reference, $farFutureOnStageOne->reference],
            $refs,
        );
        $this->assertSame(3, $response->json('meta.total'));
    }

    public function test_my_queue_pagination_meta_is_correct_across_two_stages(): void
    {
        foreach (range(1, 5) as $i) {
            $this->makeRequest($this->stageOne, "ENG-MQ-PAGE-{$i}", now()->addHours($i)->getTimestamp());
        }
        foreach (range(1, 5) as $i) {
            $this->makeRequest($this->stageTwo, "ENG-MQ-PAGE-T{$i}", now()->addHours($i + 10)->getTimestamp());
        }

        $response = $this->actingAs($this->user)->getJson('/api/v1/engine-requests/my-queue?per_page=4');

        $response->assertOk();
        $this->assertSame(10, $response->json('meta.total'));
        $this->assertSame(3, $response->json('meta.last_page'));
        $this->assertCount(4, $response->json('data'));
    }

    public function test_my_queue_search_filter_still_applies_across_stage_branches(): void
    {
        $this->makeRequest($this->stageOne, 'ENG-MQ-FINDME', now()->addHour()->getTimestamp())
            ->forceFill(['invoice_number' => 'INV-FINDME-999'])->save();
        $this->makeRequest($this->stageTwo, 'ENG-MQ-OTHER', now()->addHour()->getTimestamp());

        $response = $this->actingAs($this->user)->getJson('/api/v1/engine-requests/my-queue?search=FINDME-999');

        $response->assertOk();
        $refs = collect($response->json('data'))->pluck('reference')->all();
        $this->assertSame(['ENG-MQ-FINDME'], $refs);
    }
}

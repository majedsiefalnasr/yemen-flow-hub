<?php

namespace Tests\Feature\Engine;

use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\FieldDefinition;
use App\Models\FieldGroup;
use App\Models\Merchant;
use App\Models\Organization;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Services\Workflow\EngineRequestService;
use App\Services\Workflow\EngineTransitionService;
use Database\Seeders\GovernanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DB-001/DB-002 follow-up: guards the new maintained `sla_deadline_epoch`
 * column. Proves (1) EngineRequestService::create() and
 * EngineTransitionService::execute() both populate it correctly from the
 * stage's sla_duration_minutes at the moment the request enters that stage,
 * (2) a null-SLA stage leaves it null, and (3) orderBySlaPriority()'s
 * observable ordering is identical whether the deadline comes from the fast
 * (column) path or the COALESCE fallback (SlaProjectionParityTest already
 * covers the fallback path directly).
 */
class SlaDeadlineEpochColumnTest extends TestCase
{
    use RefreshDatabase;

    private User $executor;

    private Bank $bank;

    private Merchant $merchant;

    private WorkflowVersion $version;

    private WorkflowStage $startStage;

    private WorkflowStage $slaStage;

    private WorkflowStage $noSlaStage;

    private WorkflowTransition $toSla;

    private WorkflowTransition $toNoSla;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GovernanceSeeder::class);

        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $this->bank = Bank::create(['name' => 'SLA Epoch Bank', 'code' => 'SLAEB', 'is_active' => true, 'organization_id' => $bankOrg->id]);

        $role = Role::where('code', 'intake')->firstOrFail();
        $team = Team::where('code', 'entry')->firstOrFail();

        $this->executor = User::create([
            'name' => 'SLA Epoch Executor', 'email' => 'sla-epoch@test.bank', 'password' => bcrypt('password'),
            'bank_id' => $this->bank->id, 'organization_id' => $bankOrg->id, 'is_active' => true,
        ]);
        $this->executor->teams()->attach($team);
        $this->executor->roles()->attach($role);

        $this->merchant = Merchant::create([
            'bank_id' => $this->bank->id, 'name' => 'SLA Epoch Merchant', 'tax_number' => 'TAX-SLA-EPOCH', 'status' => 'ACTIVE',
        ]);

        $def = WorkflowDefinition::create(['code' => 'SLA_EPOCH_WF', 'name' => 'SLA Epoch WF', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id, 'version_number' => 1,
            'state' => 'PUBLISHED', 'published_at' => now(), 'version' => 1,
        ]);

        $this->startStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id, 'code' => 'START', 'name' => 'Start',
            'sort_order' => 1, 'is_initial' => true, 'is_final' => false, 'version' => 1,
            'sla_duration_minutes' => 120,
        ]);
        $this->slaStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id, 'code' => 'SLA_STAGE', 'name' => 'SLA Stage',
            'sort_order' => 2, 'is_initial' => false, 'is_final' => false, 'version' => 1,
            'sla_duration_minutes' => 90,
        ]);
        $this->noSlaStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id, 'code' => 'NO_SLA_STAGE', 'name' => 'No SLA Stage',
            'sort_order' => 3, 'is_initial' => false, 'is_final' => false, 'version' => 1,
            'sla_duration_minutes' => null,
        ]);

        foreach ([$this->startStage, $this->slaStage, $this->noSlaStage] as $stage) {
            StagePermission::create([
                'stage_id' => $stage->id, 'organization_id' => $bankOrg->id, 'role_id' => $role->id,
                'access_level' => 'EXECUTE', 'display_label' => 'Exec', 'version' => 1,
            ]);
        }

        $toSlaAction = WorkflowAction::create(['code' => 'TO_SLA_STAGE', 'name' => 'To SLA Stage', 'kind' => 'APPROVE', 'is_active' => true, 'version' => 1]);
        $toNoSlaAction = WorkflowAction::create(['code' => 'TO_NO_SLA_STAGE', 'name' => 'To No SLA Stage', 'kind' => 'APPROVE', 'is_active' => true, 'version' => 1]);
        $this->toSla = WorkflowTransition::create([
            'workflow_version_id' => $this->version->id, 'from_stage_id' => $this->startStage->id,
            'to_stage_id' => $this->slaStage->id, 'action_id' => $toSlaAction->id, 'requires_comment' => false, 'version' => 1,
        ]);
        $this->toNoSla = WorkflowTransition::create([
            'workflow_version_id' => $this->version->id, 'from_stage_id' => $this->startStage->id,
            'to_stage_id' => $this->noSlaStage->id, 'action_id' => $toNoSlaAction->id, 'requires_comment' => false, 'version' => 1,
        ]);

        $group = FieldGroup::create(['workflow_version_id' => $this->version->id, 'name' => 'main', 'label' => 'Main', 'sort_order' => 1, 'version' => 1]);
        FieldDefinition::create([
            'workflow_version_id' => $this->version->id, 'field_group_id' => $group->id, 'key' => 'notes',
            'label' => 'Notes', 'type' => 'TEXT', 'is_required' => false, 'sort_order' => 1, 'version' => 1,
        ]);
    }

    public function test_create_populates_sla_deadline_epoch_from_initial_stage(): void
    {
        $before = now()->getTimestamp();

        $request = app(EngineRequestService::class)->create($this->version, ['data' => []], $this->executor);

        $expectedMin = $before + (120 * 60);
        $expectedMax = now()->getTimestamp() + (120 * 60);

        $this->assertNotNull($request->sla_deadline_epoch);
        $this->assertGreaterThanOrEqual($expectedMin, $request->sla_deadline_epoch);
        $this->assertLessThanOrEqual($expectedMax, $request->sla_deadline_epoch);
    }

    public function test_transition_populates_sla_deadline_epoch_from_the_target_stage(): void
    {
        $request = app(EngineRequestService::class)->create($this->version, ['data' => []], $this->executor);
        $before = now()->getTimestamp();

        $updated = app(EngineTransitionService::class)->execute(
            $request, $this->toSla->id, null, [], $request->version, $this->executor,
        );

        $expectedMin = $before + (90 * 60);
        $expectedMax = now()->getTimestamp() + (90 * 60);

        $this->assertNotNull($updated->sla_deadline_epoch);
        $this->assertGreaterThanOrEqual($expectedMin, $updated->sla_deadline_epoch);
        $this->assertLessThanOrEqual($expectedMax, $updated->sla_deadline_epoch);
    }

    public function test_transition_to_a_no_sla_stage_leaves_the_column_null(): void
    {
        $request = app(EngineRequestService::class)->create($this->version, ['data' => []], $this->executor);

        $updated = app(EngineTransitionService::class)->execute(
            $request, $this->toNoSla->id, null, [], $request->version, $this->executor,
        );

        $this->assertNull($updated->sla_deadline_epoch);
    }

    /**
     * orderBySlaPriority() orders directly on the raw sla_deadline_epoch
     * column (not the COALESCE-wrapped slaDeadlineEpochSql() expression —
     * see that scope's docblock for why: COALESCE defeats the index MySQL
     * would otherwise use for the sort). A genuinely-breached row (deadline
     * in the past) must still sort before a not-yet-breached row via that
     * raw column.
     */
    public function test_orders_by_the_raw_deadline_column_breached_first(): void
    {
        $breached = app(EngineRequestService::class)->create($this->version, ['data' => []], $this->executor);
        app(EngineTransitionService::class)->execute($breached, $this->toSla->id, null, [], $breached->version, $this->executor);
        $breached->refresh();
        $breached->forceFill(['sla_deadline_epoch' => now()->subHour()->getTimestamp()])->save();

        $ok = app(EngineRequestService::class)->create($this->version, ['data' => []], $this->executor);
        app(EngineTransitionService::class)->execute($ok, $this->toSla->id, null, [], $ok->version, $this->executor);
        $ok->refresh();
        $ok->forceFill(['sla_deadline_epoch' => now()->addDay()->getTimestamp()])->save();

        $ordered = EngineRequest::query()
            ->withStageEntry()
            ->whereIn('engine_requests.id', [$breached->id, $ok->id])
            ->orderBySlaPriority()
            ->orderBy('engine_requests.id')
            ->pluck('engine_requests.reference')
            ->all();

        $this->assertSame([$breached->reference, $ok->reference], $ordered, 'the breached row must sort before the not-yet-breached row');
    }

    /**
     * A row with a null sla_deadline_epoch despite its stage having an SLA
     * (should only happen pre-backfill / a hypothetical write-path bug —
     * both real write paths always populate it) sorts NULL-first in MySQL
     * ASC, i.e. as if maximally breached: the safe-by-default direction for
     * an operational queue, since a row whose deadline could not be computed
     * should surface, not silently sink to the bottom.
     */
    public function test_null_deadline_on_an_sla_stage_sorts_first_not_last(): void
    {
        $nullDeadline = app(EngineRequestService::class)->create($this->version, ['data' => []], $this->executor);
        app(EngineTransitionService::class)->execute($nullDeadline, $this->toSla->id, null, [], $nullDeadline->version, $this->executor);
        $nullDeadline->refresh();
        $nullDeadline->forceFill(['sla_deadline_epoch' => null])->save();

        $farFuture = app(EngineRequestService::class)->create($this->version, ['data' => []], $this->executor);
        app(EngineTransitionService::class)->execute($farFuture, $this->toSla->id, null, [], $farFuture->version, $this->executor);
        $farFuture->refresh();
        $farFuture->forceFill(['sla_deadline_epoch' => now()->addDay()->getTimestamp()])->save();

        $ordered = EngineRequest::query()
            ->withStageEntry()
            ->whereIn('engine_requests.id', [$nullDeadline->id, $farFuture->id])
            ->orderBySlaPriority()
            ->orderBy('engine_requests.id')
            ->pluck('engine_requests.reference')
            ->all();

        $this->assertSame([$nullDeadline->reference, $farFuture->reference], $ordered);
    }
}

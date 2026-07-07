<?php

namespace Tests\Feature\Engine;

use App\Enums\FinalOutcome;
use App\Enums\WorkflowActionKind;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ImportFinancingWorkflowSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\WorkflowActionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportFinancingTerminalOutcomeAuditTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $manifest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manifest = require base_path('tests/Fixtures/import-financing-v1-manifest.php');
    }

    public function test_import_financing_v1_terminal_transitions_match_publish_rules(): void
    {
        $this->seed([
            GovernanceSeeder::class,
            ReferenceDataSeeder::class,
            WorkflowActionSeeder::class,
            ImportFinancingWorkflowSeeder::class,
        ]);

        $version = WorkflowDefinition::query()
            ->where('code', 'IMPORT_FINANCING')
            ->firstOrFail()
            ->versions()
            ->firstOrFail();

        $stageById = $version->stages()->get()->keyBy('id');

        foreach ($version->transitions()->with('action')->get() as $transition) {
            $toStage = $stageById->get($transition->to_stage_id);
            if ($toStage === null || ! $toStage->is_final) {
                continue;
            }

            $kind = $transition->action->kind;
            $outcome = $toStage->final_outcome;

            if ($kind === WorkflowActionKind::REJECT) {
                $this->assertSame(
                    FinalOutcome::REJECTED,
                    $outcome,
                    "transition {$transition->id} ({$transition->action->code})"
                );
            }

            if (in_array($kind, [WorkflowActionKind::APPROVE, WorkflowActionKind::CLOSE], true)) {
                $this->assertSame(
                    FinalOutcome::COMPLETED,
                    $outcome,
                    "transition {$transition->id} ({$transition->action->code})"
                );
            }
        }
    }

    public function test_import_financing_v1_matches_manifest_terminal_stages(): void
    {
        $this->seed([
            GovernanceSeeder::class,
            ReferenceDataSeeder::class,
            WorkflowActionSeeder::class,
            ImportFinancingWorkflowSeeder::class,
        ]);

        $version = WorkflowDefinition::query()
            ->where('code', 'IMPORT_FINANCING')
            ->firstOrFail()
            ->versions()
            ->firstOrFail();

        foreach ($this->manifest['terminal_stage_codes'] as $scenario => $code) {
            $stage = WorkflowStage::query()
                ->where('workflow_version_id', $version->id)
                ->where('code', $code)
                ->first();

            $this->assertNotNull($stage, "missing terminal stage for {$scenario}");
            $this->assertTrue($stage->is_final, "{$code} must be final");
        }

        $support = WorkflowStage::query()
            ->where('workflow_version_id', $version->id)
            ->where('code', 'SUPPORT')
            ->firstOrFail();

        $this->assertTrue($support->requires_claim, 'SUPPORT requires_claim delta');
    }

    public function test_manifest_transition_count_matches_seeded_workflow(): void
    {
        $this->seed([
            GovernanceSeeder::class,
            ReferenceDataSeeder::class,
            WorkflowActionSeeder::class,
            ImportFinancingWorkflowSeeder::class,
        ]);

        $version = WorkflowDefinition::query()
            ->where('code', 'IMPORT_FINANCING')
            ->firstOrFail()
            ->versions()
            ->firstOrFail();

        $this->assertCount(
            count($this->manifest['transitions']),
            $version->transitions()->get(),
        );
    }
}

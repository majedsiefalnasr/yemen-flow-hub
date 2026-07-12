<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\WorkflowDefinition;
use App\Services\Workflow\WorkflowVersionValidator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * REGRESSION GATE (Phase E1): the canonical published workflow must satisfy
 * these expectations. Seeds V1 (the original WF-001 fixture) then publishes
 * the Phase B correction (PublishImportFinancingV2Command) so the "currently
 * published" version under test is V2, matching every other Phase B/D/E
 * suite's setup pattern (see PublishImportFinancingV2CommandTest and others).
 */
class Phase3WorkflowConfigurationProbeTest extends TestCase
{
    use RefreshDatabase;

    private function seedPublishedV2(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->artisan('workflow:publish-import-financing-v2', ['--publish' => true])->assertExitCode(0);
    }

    public function test_canonical_published_workflow_passes_current_validator(): void
    {
        $this->seedPublishedV2();
        $version = WorkflowDefinition::query()
            ->where('code', 'IMPORT_FINANCING')
            ->firstOrFail()
            ->versions()
            ->where('state', 'PUBLISHED')
            ->firstOrFail();

        $errors = app(WorkflowVersionValidator::class)->validate($version);

        $this->assertSame(
            [],
            $errors,
            'WF-001: canonical IMPORT_FINANCING workflow is published but fails current validation: '.json_encode($errors)
        );
    }

    public function test_canonical_rejection_transitions_require_comments(): void
    {
        $this->seedPublishedV2();
        $version = WorkflowDefinition::query()
            ->where('code', 'IMPORT_FINANCING')
            ->firstOrFail()
            ->versions()
            ->where('state', 'PUBLISHED')
            ->firstOrFail();

        $withoutComments = $version->transitions()
            ->with(['action:id,code,kind', 'fromStage:id,code', 'toStage:id,code'])
            ->get()
            ->filter(fn ($transition) => $transition->action?->kind?->value === 'REJECT' && ! $transition->requires_comment)
            ->map(fn ($transition) => "{$transition->fromStage?->code}:{$transition->action?->code}:{$transition->toStage?->code}")
            ->values()
            ->all();

        $this->assertSame(
            [],
            $withoutComments,
            'WF-001: canonical rejection transitions allow empty reasons: '.implode(', ', $withoutComments)
        );
    }

    public function test_canonical_self_loops_are_explicitly_intentional(): void
    {
        $this->seedPublishedV2();
        $version = WorkflowDefinition::query()
            ->where('code', 'IMPORT_FINANCING')
            ->firstOrFail()
            ->versions()
            ->where('state', 'PUBLISHED')
            ->firstOrFail();

        $unintentional = $version->transitions()
            ->with(['action:id,code', 'fromStage:id,code'])
            ->whereColumn('from_stage_id', 'to_stage_id')
            ->where(fn ($query) => $query->where('is_self_loop', false)->orWhereNull('is_self_loop'))
            ->get()
            ->map(fn ($transition) => "{$transition->fromStage?->code}:{$transition->action?->code}")
            ->values()
            ->all();

        $this->assertSame(
            [],
            $unintentional,
            'WF-001: canonical self-loop is not marked intentional: '.implode(', ', $unintentional)
        );
    }
}

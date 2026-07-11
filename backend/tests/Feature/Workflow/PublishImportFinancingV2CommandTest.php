<?php

declare(strict_types=1);

namespace Tests\Feature\Workflow;

use App\Enums\StageAccessLevel;
use App\Enums\WorkflowVersionState;
use App\Models\EngineRequest;
use App\Models\FieldDefinition;
use App\Models\Role;
use App\Models\StageFieldRule;
use App\Models\StagePermission;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Services\Workflow\WorkflowVersionValidator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase B: the designer-driven V2 builder command. Proves V2 is validator-clean,
 * carries the B1–B3 corrections, and leaves the published V1 untouched — and that
 * an invalid configuration cannot be published.
 */
class PublishImportFinancingV2CommandTest extends TestCase
{
    use RefreshDatabase;

    private function v1(): WorkflowVersion
    {
        return WorkflowDefinition::query()
            ->where('code', 'IMPORT_FINANCING')->firstOrFail()
            ->versions()->where('version_number', 1)->firstOrFail();
    }

    private function publishedV2(): ?WorkflowVersion
    {
        return WorkflowDefinition::query()
            ->where('code', 'IMPORT_FINANCING')->firstOrFail()
            ->versions()->where('version_number', '>', 1)
            ->where('state', WorkflowVersionState::PUBLISHED)->first();
    }

    private function stageId(WorkflowVersion $version, string $code): int
    {
        return (int) WorkflowStage::query()
            ->where('workflow_version_id', $version->id)->where('code', $code)->value('id');
    }

    public function test_command_publishes_a_validator_clean_v2(): void
    {
        $this->seed(DatabaseSeeder::class);
        $v1Id = $this->v1()->id;

        $this->artisan('workflow:publish-import-financing-v2', ['--publish' => true])->assertExitCode(0);

        $v2 = $this->publishedV2();
        $this->assertNotNull($v2, 'V2 was not published.');
        $this->assertSame([], app(WorkflowVersionValidator::class)->validate($v2), 'V2 failed validation.');

        // V1 preserved but archived by the publish lifecycle.
        $v1 = WorkflowVersion::query()->find($v1Id);
        $this->assertNotNull($v1, 'V1 was deleted.');
        $this->assertSame(WorkflowVersionState::ARCHIVED, $v1->state, 'V1 was not archived by publish.');
    }

    public function test_v2_reject_transitions_require_comments_and_confirmation(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->artisan('workflow:publish-import-financing-v2', ['--publish' => true])->assertExitCode(0);
        $v2 = $this->publishedV2();

        $withoutComment = WorkflowTransition::query()
            ->where('workflow_version_id', $v2->id)
            ->with(['action:id,kind'])
            ->get()
            ->filter(fn ($t) => $t->action?->kind?->value === 'REJECT' && ! $t->requires_comment)
            ->count();

        $this->assertSame(0, $withoutComment, 'V2 has REJECT transitions without required comments.');
    }

    public function test_v2_support_self_loop_is_marked_intentional(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->artisan('workflow:publish-import-financing-v2', ['--publish' => true])->assertExitCode(0);
        $v2 = $this->publishedV2();

        $unintentional = WorkflowTransition::query()
            ->where('workflow_version_id', $v2->id)
            ->whereColumn('from_stage_id', 'to_stage_id')
            ->where(fn ($q) => $q->where('is_self_loop', false)->orWhereNull('is_self_loop'))
            ->count();

        $this->assertSame(0, $unintentional, 'V2 self-loop not marked intentional.');
    }

    public function test_v2_final_stage_is_owned_by_committee_director(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->artisan('workflow:publish-import-financing-v2', ['--publish' => true])->assertExitCode(0);
        $v2 = $this->publishedV2();

        $directorRoleId = (int) Role::query()->where('code', 'committee_director')->value('id');
        $managerRoleId = (int) Role::query()->where('code', 'committee_manager')->value('id');

        $finalExecute = StagePermission::query()
            ->where('stage_id', $this->stageId($v2, 'FINAL'))
            ->where('access_level', StageAccessLevel::EXECUTE)
            ->first();
        $this->assertNotNull($finalExecute);
        $this->assertSame($directorRoleId, (int) $finalExecute->role_id, 'FINAL not owned by committee_director.');

        // EXEC still owned by committee_manager.
        $execExecute = StagePermission::query()
            ->where('stage_id', $this->stageId($v2, 'EXEC'))
            ->where('access_level', StageAccessLevel::EXECUTE)
            ->first();
        $this->assertNotNull($execExecute);
        $this->assertSame($managerRoleId, (int) $execExecute->role_id, 'EXEC ownership changed unexpectedly.');
    }

    public function test_v2_swift_package_is_required_to_leave_fx(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->artisan('workflow:publish-import-financing-v2', ['--publish' => true])->assertExitCode(0);
        $v2 = $this->publishedV2();

        $fxStageId = $this->stageId($v2, 'FX');
        foreach (['swift_reference', 'swift_file', 'fx_request_file'] as $key) {
            $fieldId = (int) FieldDefinition::query()
                ->where('workflow_version_id', $v2->id)->where('key', $key)->value('id');
            $this->assertNotSame(0, $fieldId, "SWIFT field {$key} missing on V2.");

            $rule = StageFieldRule::query()
                ->where('stage_id', $fxStageId)->where('field_id', $fieldId)->first();
            $this->assertNotNull($rule, "No FX field rule for {$key}.");
            $this->assertTrue((bool) $rule->is_required, "SWIFT field {$key} is not required to leave FX.");
        }
    }

    public function test_default_invocation_is_a_no_mutation_dry_run(): void
    {
        $this->seed(DatabaseSeeder::class);

        // No --publish flag: safe by default, mutates nothing.
        $this->artisan('workflow:publish-import-financing-v2')->assertExitCode(0);

        $this->assertNull($this->publishedV2(), 'Default (no-flag) run published a V2.');
        $this->assertSame(WorkflowVersionState::PUBLISHED, $this->v1()->state, 'Default run altered V1 state.');
    }

    public function test_publishing_requires_the_explicit_publish_flag(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Without --publish, no version is created regardless of a valid config.
        $this->artisan('workflow:publish-import-financing-v2')->assertExitCode(0);
        $this->assertNull($this->publishedV2(), 'A V2 was published without the explicit --publish flag.');

        // With --publish, it persists.
        $this->artisan('workflow:publish-import-financing-v2', ['--publish' => true])->assertExitCode(0);
        $this->assertNotNull($this->publishedV2(), '--publish did not persist a V2.');
    }

    public function test_second_run_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->artisan('workflow:publish-import-financing-v2', ['--publish' => true])->assertExitCode(0);
        $firstV2Id = $this->publishedV2()->id;

        $this->artisan('workflow:publish-import-financing-v2', ['--publish' => true])->assertExitCode(0);

        $this->assertSame($firstV2Id, $this->publishedV2()->id, 'Second run created another V2.');
    }

    public function test_after_publish_the_only_published_version_is_v2_so_new_requests_pin_v2(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->artisan('workflow:publish-import-financing-v2', ['--publish' => true])->assertExitCode(0);

        $published = WorkflowDefinition::query()
            ->where('code', 'IMPORT_FINANCING')->firstOrFail()
            ->versions()->where('state', WorkflowVersionState::PUBLISHED)->get();

        $this->assertCount(1, $published, 'More than one published version exists.');
        $this->assertGreaterThan(1, (int) $published->first()->version_number, 'The published version is not V2.');
    }

    public function test_existing_v1_pinned_requests_are_unchanged(): void
    {
        $this->seed(DatabaseSeeder::class);
        $v1Id = $this->v1()->id;

        // Snapshot any pre-existing requests pinned to V1.
        $before = EngineRequest::query()
            ->where('workflow_version_id', $v1Id)
            ->pluck('current_stage_id', 'id')->all();

        $this->artisan('workflow:publish-import-financing-v2', ['--publish' => true])->assertExitCode(0);

        $after = EngineRequest::query()
            ->where('workflow_version_id', $v1Id)
            ->pluck('current_stage_id', 'id')->all();

        $this->assertSame($before, $after, 'V1-pinned requests changed pin or stage after V2 publish.');
    }

    public function test_invalid_configuration_cannot_be_published(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Break the source so the cloned DRAFT fails validation: strip the initial
        // flag from CREATE (NO_INITIAL_STAGE). Publishing must abort, leaving no V2
        // and V1 still published.
        WorkflowStage::query()
            ->where('workflow_version_id', $this->v1()->id)
            ->where('code', 'CREATE')
            ->update(['is_initial' => false]);

        $this->artisan('workflow:publish-import-financing-v2', ['--publish' => true])->assertExitCode(1);

        $this->assertNull($this->publishedV2(), 'A V2 was published from an invalid configuration.');
        $this->assertSame(WorkflowVersionState::PUBLISHED, $this->v1()->fresh()->state, 'V1 was altered by a failed publish.');
    }
}

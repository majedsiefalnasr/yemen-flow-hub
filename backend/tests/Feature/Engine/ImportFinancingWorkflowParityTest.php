<?php

namespace Tests\Feature\Engine;

use App\Models\FieldDefinition;
use App\Models\FieldGroup;
use App\Models\Organization;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\Team;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ImportFinancingWorkflowSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\WorkflowActionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Workflow parity test: asserts the seeded Import Financing v1 workflow
 * exactly matches the checked-in manifest fixture.
 *
 * On failure, prints structured diff (missing/extra/changed) to help
 * identify discrepancies quickly.
 */
class ImportFinancingWorkflowParityTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $manifest;

    /** @var WorkflowDefinition */
    private WorkflowDefinition $definition;

    /** @var \Illuminate\Database\Eloquent\Collection<int, WorkflowStage> */
    private $stagesFromDb;

    /** @var \Illuminate\Database\Eloquent\Collection<int, WorkflowTransition> */
    private $transitionsFromDb;

    /** @var \Illuminate\Database\Eloquent\Collection<int, FieldGroup> */
    private $fieldGroupsFromDb;

    /** @var \Illuminate\Database\Eloquent\Collection<int, FieldDefinition> */
    private $fieldsFromDb;

    /** @var \Illuminate\Database\Eloquent\Collection<int, StagePermission> */
    private $permissionsFromDb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manifest = require base_path('tests/Fixtures/import-financing-v1-manifest.php');

        $this->seed([
            GovernanceSeeder::class,
            ReferenceDataSeeder::class,
            WorkflowActionSeeder::class,
            ImportFinancingWorkflowSeeder::class,
        ]);

        $this->definition = WorkflowDefinition::query()
            ->where('code', 'IMPORT_FINANCING')
            ->firstOrFail();

        $version = $this->definition->versions()->firstOrFail();

        $this->stagesFromDb = $version->stages()->get();
        $this->transitionsFromDb = $version->transitions()->with('action')->get();
        $this->fieldGroupsFromDb = $version->fieldGroups()->get();
        $this->fieldsFromDb = $version->fields()->get();

        // Gather all stage permissions through their stages
        $this->permissionsFromDb = StagePermission::query()
            ->whereIn('stage_id', $this->stagesFromDb->pluck('id'))
            ->with(['stage'])
            ->get();
        
        // Enrich permissions with org/team/role code data from their respective tables
        $this->permissionsFromDb = $this->permissionsFromDb->map(function ($perm) {
            $perm->organization_code = $perm->organization_id ? Organization::find($perm->organization_id)?->code : null;
            $perm->team_code = $perm->team_id ? Team::find($perm->team_id)?->code : null;
            $perm->role_code = $perm->role_id ? Role::find($perm->role_id)?->code : null;
            return $perm;
        });
    }

    public function test_import_financing_v1_workflow_definition_matches_manifest(): void
    {
        $this->assertSame(
            $this->manifest['definition_code'],
            $this->definition->code,
            'Definition code mismatch'
        );

        $this->assertSame(
            $this->manifest['definition_name'],
            $this->definition->name,
            'Definition name mismatch'
        );

        $version = $this->definition->versions()->firstOrFail();
        $this->assertSame(
            $this->manifest['version_number'],
            $version->version_number,
            'Version number mismatch'
        );
    }

    public function test_workflow_stages_match_manifest(): void
    {
        $manifestStages = collect($this->manifest['stages'])
            ->keyBy('code');

        $dbStages = $this->stagesFromDb->keyBy('code');

        $missing = array_diff_key($manifestStages->toArray(), $dbStages->toArray());
        $extra = array_diff_key($dbStages->toArray(), $manifestStages->toArray());

        if (! empty($missing) || ! empty($extra)) {
            $this->fail($this->formatDiff('Stages', $missing, $extra));
        }

        foreach ($manifestStages as $code => $manifestStage) {
            $dbStage = $dbStages[$code];

            $this->assertSame(
                $manifestStage['sort_order'],
                $dbStage->sort_order,
                "Stage {$code}: sort_order mismatch"
            );

            $this->assertSame(
                $manifestStage['is_initial'],
                (bool) $dbStage->is_initial,
                "Stage {$code}: is_initial mismatch"
            );

            $this->assertSame(
                $manifestStage['is_final'],
                (bool) $dbStage->is_final,
                "Stage {$code}: is_final mismatch"
            );

            $this->assertSame(
                $manifestStage['final_outcome'],
                $dbStage->final_outcome?->value,
                "Stage {$code}: final_outcome mismatch"
            );

            $this->assertSame(
                $manifestStage['requires_claim'],
                (bool) $dbStage->requires_claim,
                "Stage {$code}: requires_claim mismatch"
            );
        }
    }

    public function test_workflow_transitions_match_manifest(): void
    {
        $manifestTransitions = collect($this->manifest['transitions'])
            ->map(fn ($t) => "{$t['from']}→{$t['to']}:{$t['action']}")
            ->sort()
            ->values()
            ->all();

        $dbTransitions = $this->transitionsFromDb
            ->map(function ($t) {
                $fromCode = WorkflowStage::query()->find($t->from_stage_id)?->code;
                $toCode = WorkflowStage::query()->find($t->to_stage_id)?->code;

                return "{$fromCode}→{$toCode}:{$t->action->code}";
            })
            ->sort()
            ->values()
            ->all();

        $missing = array_diff($manifestTransitions, $dbTransitions);
        $extra = array_diff($dbTransitions, $manifestTransitions);

        if (! empty($missing) || ! empty($extra)) {
            $this->fail(
                "Transitions mismatch\n"
                . ($missing ? "Missing:\n  - " . implode("\n  - ", $missing) . "\n" : '')
                . ($extra ? "Extra:\n  - " . implode("\n  - ", $extra) . "\n" : '')
            );
        }

        $this->assertCount(
            count($this->manifest['transitions']),
            $this->transitionsFromDb,
            'Transition count mismatch'
        );
    }

    public function test_field_groups_match_manifest(): void
    {
        $manifestGroups = collect($this->manifest['field_groups'])
            ->keyBy('key');

        $dbGroups = $this->fieldGroupsFromDb->keyBy('name');

        $missing = array_diff_key($manifestGroups->toArray(), $dbGroups->toArray());
        $extra = array_diff_key($dbGroups->toArray(), $manifestGroups->toArray());

        if (! empty($missing) || ! empty($extra)) {
            $this->fail($this->formatDiff('Field Groups', $missing, $extra));
        }

        foreach ($manifestGroups as $key => $manifestGroup) {
            $dbGroup = $dbGroups[$key];

            $this->assertSame(
                $manifestGroup['sort_order'],
                $dbGroup->sort_order,
                "Field group {$key}: sort_order mismatch"
            );

            $this->assertSame(
                $manifestGroup['label'],
                $dbGroup->label,
                "Field group {$key}: label mismatch"
            );
        }
    }

    public function test_field_keys_match_manifest(): void
    {
        $manifestKeys = collect($this->manifest['field_keys'])->sort()->values()->all();
        $dbKeys = $this->fieldsFromDb->pluck('key')->sort()->values()->all();

        $missing = array_diff($manifestKeys, $dbKeys);
        $extra = array_diff($dbKeys, $manifestKeys);

        if (! empty($missing) || ! empty($extra)) {
            $this->fail(
                "Field keys mismatch\n"
                . ($missing ? "Missing (" . count($missing) . "):\n  - " . implode("\n  - ", $missing) . "\n" : '')
                . ($extra ? "Extra (" . count($extra) . "):\n  - " . implode("\n  - ", $extra) . "\n" : '')
            );
        }

        $this->assertCount(count($this->manifest['field_keys']), $dbKeys, 'Field key count mismatch');
    }

    public function test_required_on_create_fields_are_subset_of_all_fields(): void
    {
        $requiredKeys = collect($this->manifest['required_on_create']);
        $allKeys = collect($this->manifest['field_keys']);

        $missing = $requiredKeys->diff($allKeys);

        $this->assertTrue(
            $missing->isEmpty(),
            "Required-on-create fields not in field_keys: " . implode(', ', $missing->all())
        );

        $this->assertTrue(
            count($requiredKeys) <= count($allKeys),
            'required_on_create must be a subset of field_keys'
        );
    }

    public function test_stage_permissions_match_manifest(): void
    {
        $manifestPermissions = collect($this->manifest['stage_permissions'])
            ->map(fn ($p) => [
                'stage' => $p['stage'],
                'org' => $p['org'],
                'team' => $p['team'],
                'role' => $p['role'],
                'access' => $p['access'],
            ])
            ->sort()
            ->values()
            ->all();

        $dbPermissions = $this->permissionsFromDb
            ->map(function ($p) {
                return [
                    'stage' => $p->stage->code,
                    'org' => $p->organization_code,
                    'team' => $p->team_code,
                    'role' => $p->role_code,
                    'access' => $p->access_level->value,
                ];
            })
            ->sort()
            ->values()
            ->all();

        $this->assertCount(
            count($this->manifest['stage_permissions']),
            $this->permissionsFromDb,
            'Stage permission count mismatch'
        );

        foreach ($manifestPermissions as $i => $manifestPerm) {
            $dbPerm = $dbPermissions[$i] ?? null;

            $this->assertNotNull(
                $dbPerm,
                "Stage permission {$i} missing in DB: " . json_encode($manifestPerm)
            );

            $this->assertSame(
                $manifestPerm['stage'],
                $dbPerm['stage'],
                "Permission {$i}: stage mismatch"
            );

            $this->assertSame(
                $manifestPerm['org'],
                $dbPerm['org'],
                "Permission {$i}: org mismatch"
            );

            $this->assertSame(
                $manifestPerm['team'],
                $dbPerm['team'],
                "Permission {$i}: team mismatch"
            );

            $this->assertSame(
                $manifestPerm['role'],
                $dbPerm['role'],
                "Permission {$i}: role mismatch"
            );

            $this->assertSame(
                $manifestPerm['access'],
                $dbPerm['access'],
                "Permission {$i}: access mismatch"
            );
        }
    }

    /**
     * Format a structured diff for display on test failure.
     *
     * @param  string  $label
     * @param  array  $missing
     * @param  array  $extra
     * @return string
     */
    private function formatDiff(string $label, array $missing, array $extra): string
    {
        $msg = "{$label} mismatch\n";

        if (! empty($missing)) {
            $msg .= "Missing:\n";
            foreach ($missing as $key => $item) {
                $msg .= "  - {$key}\n";
            }
        }

        if (! empty($extra)) {
            $msg .= "Extra:\n";
            foreach ($extra as $key => $item) {
                $msg .= "  - {$key}\n";
            }
        }

        return $msg;
    }
}

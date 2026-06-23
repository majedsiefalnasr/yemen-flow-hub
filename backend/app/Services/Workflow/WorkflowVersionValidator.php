<?php

namespace App\Services\Workflow;

use App\Enums\FieldType;
use App\Enums\StageAccessLevel;
use App\Models\WorkflowVersion;

/**
 * Validate-before-publish (FR-WD9). Returns a list of displayable, field-tagged
 * errors; an empty list means the version is publishable. Pure read — no side
 * effects.
 *
 * Each error: ['code' => string, 'target' => string, 'message' => string].
 */
class WorkflowVersionValidator
{
    /**
     * @return array<int, array{code: string, target: string, message: string}>
     */
    public function validate(WorkflowVersion $version): array
    {
        $stages = $version->stages()->with('stagePermissions')->get();
        $transitions = $version->transitions()->get();
        $fields = $version->fieldDefinitions()->get();

        $errors = [];

        // ── Initial / final invariants ──────────────────────────────────────
        $initialCount = $stages->where('is_initial', true)->count();
        if ($initialCount === 0) {
            $errors[] = $this->error('NO_INITIAL_STAGE', 'stages', 'The workflow must have exactly one initial stage.');
        } elseif ($initialCount > 1) {
            $errors[] = $this->error('MULTIPLE_INITIAL_STAGES', 'stages', 'The workflow must have exactly one initial stage.');
        }

        if ($stages->where('is_final', true)->count() === 0) {
            $errors[] = $this->error('NO_FINAL_STAGE', 'stages', 'The workflow must have at least one final stage.');
        }

        // ── Duplicate stage codes / field keys (defensive; unique at DB too) ─
        $dupStageCodes = $stages->groupBy('code')->filter(fn ($g) => $g->count() > 1)->keys();
        foreach ($dupStageCodes as $code) {
            $errors[] = $this->error('DUPLICATE_STAGE_CODE', 'stages', "Duplicate stage code: {$code}.");
        }
        $dupFieldKeys = $fields->groupBy('key')->filter(fn ($g) => $g->count() > 1)->keys();
        foreach ($dupFieldKeys as $key) {
            $errors[] = $this->error('DUPLICATE_FIELD_KEY', 'fields', "Duplicate field key: {$key}.");
        }

        // ── Transition integrity ────────────────────────────────────────────
        $stageIds = $stages->pluck('id')->all();
        foreach ($transitions as $transition) {
            if (! in_array($transition->from_stage_id, $stageIds, true) || ! in_array($transition->to_stage_id, $stageIds, true)) {
                $errors[] = $this->error('TRANSITION_INVALID_STAGE', 'transitions', 'A transition references a stage outside this version.');
            }
        }

        // ── Non-final stages need ≥1 outgoing transition AND ≥1 executor ─────
        $transitionsByFrom = $transitions->groupBy('from_stage_id');
        foreach ($stages as $stage) {
            if ($stage->is_final) {
                continue;
            }

            if (($transitionsByFrom[$stage->id] ?? collect())->isEmpty()) {
                $errors[] = $this->error('STAGE_NO_OUTGOING_TRANSITION', "stage:{$stage->code}", "Non-final stage '{$stage->code}' has no outgoing transition.");
            }

            $hasExecutor = $stage->stagePermissions
                ->contains(fn ($permission) => $permission->access_level === StageAccessLevel::EXECUTE);
            if (! $hasExecutor) {
                $errors[] = $this->error('STAGE_NO_EXECUTOR', "stage:{$stage->code}", "Non-final stage '{$stage->code}' has no executor (an EXECUTE stage permission).");
            }
        }

        // ── DYNAMIC_SELECT field sources ────────────────────────────────────
        foreach ($fields as $field) {
            if ($field->type !== FieldType::DYNAMIC_SELECT) {
                continue;
            }
            if ($field->dynamic_source === null) {
                $errors[] = $this->error('FIELD_INVALID_SOURCE', "field:{$field->key}", "DYNAMIC_SELECT field '{$field->key}' has no dynamic source.");
            } elseif ($field->dynamic_source->value === 'REFERENCE_DATA' && $field->reference_table_id === null) {
                $errors[] = $this->error('FIELD_INVALID_SOURCE', "field:{$field->key}", "DYNAMIC_SELECT field '{$field->key}' uses REFERENCE_DATA but has no reference table.");
            }
        }

        return $errors;
    }

    /**
     * @return array{code: string, target: string, message: string}
     */
    private function error(string $code, string $target, string $message): array
    {
        return ['code' => $code, 'target' => $target, 'message' => $message];
    }
}

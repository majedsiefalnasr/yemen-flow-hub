<?php

namespace App\Services\Workflow;

use App\Models\FieldDefinition;
use App\Models\StageFieldRule;
use App\Models\WorkflowStage;
use Illuminate\Support\Collection;

/**
 * Single source of per-stage field enforcement (FR-WD7). Consumed by Epic 18.5
 * draft save (lenient on required) and transitions (required enforced on the
 * leaving action).
 *
 * Effective rule per field at a stage:
 *  - is_visible / is_editable: from the stage_field_rule if present, else true.
 *  - is_required: from the stage_field_rule if present, else the field's base
 *    is_required.
 *
 * Enforcement:
 *  - required (only when $enforceRequired): a visible+required field must have a
 *    non-empty value.
 *  - hidden: a non-visible field must NOT be present in the submitted data.
 *  - read-only: a non-editable field's submitted value must equal the previously
 *    stored value (no silent edits).
 *
 * Pure (`validateData`) so it is unit-testable without a DB.
 */
class StageFieldRuleValidator
{
    /**
     * DB-backed entry: load the stage's rules + the version's fields, then validate.
     *
     * @return array<string, string> map of field key => error message
     */
    public function validateStage(
        WorkflowStage $stage,
        array $data,
        array $previousData = [],
        bool $enforceRequired = false,
    ): array {
        $fields = FieldDefinition::query()
            ->where('workflow_version_id', $stage->workflow_version_id)
            ->get();
        $rules = $stage->stageFieldRules()->get()->keyBy('field_id');

        return $this->validateData($fields, $rules, $data, $previousData, $enforceRequired);
    }

    /**
     * Pure evaluation — synthetic fields/rules/data, no DB.
     *
     * @param  Collection<int, FieldDefinition>  $fields
     * @param  Collection<int, StageFieldRule>  $rulesByFieldId  keyed by field_id
     * @return array<string, string>
     */
    public function validateData(
        Collection $fields,
        Collection $rulesByFieldId,
        array $data,
        array $previousData = [],
        bool $enforceRequired = false,
    ): array {
        $errors = [];

        foreach ($fields as $field) {
            $rule = $rulesByFieldId->get($field->id);
            $isVisible = $rule?->is_visible ?? true;
            $isEditable = $rule?->is_editable ?? true;
            $isRequired = $rule?->is_required ?? (bool) $field->is_required;

            $present = array_key_exists($field->key, $data);
            $value = $data[$field->key] ?? null;

            if (! $isVisible) {
                if ($present) {
                    $errors[$field->key] = 'This field is not available at the current stage.';
                }

                continue;
            }

            if (! $isEditable && $present) {
                $previous = $previousData[$field->key] ?? null;
                if ($value !== $previous) {
                    $errors[$field->key] = 'This field is read-only at the current stage.';

                    continue;
                }
            }

            if ($enforceRequired && $isRequired && $this->isEmpty($value)) {
                $errors[$field->key] = 'This field is required.';
            }
        }

        return $errors;
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value)) {
            return trim($value) === '';
        }
        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }
}

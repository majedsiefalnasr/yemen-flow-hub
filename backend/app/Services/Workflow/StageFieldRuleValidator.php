<?php

namespace App\Services\Workflow;

use App\Enums\FieldType;
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

                continue;
            }

            // Skip constraint checks when field has no value
            if ($this->isEmpty($value)) {
                continue;
            }

            if ($error = $this->checkConstraints($field, $value)) {
                $errors[$field->key] = $error;
            }
        }

        return $errors;
    }

    private function checkConstraints(FieldDefinition $field, mixed $value): ?string
    {
        $type = $field->type instanceof FieldType ? $field->type : null;

        // Regex pattern (text/textarea fields)
        if ($field->regex_pattern !== null && is_string($value) && $value !== '') {
            if (! preg_match($field->regex_pattern, $value)) {
                return 'The value does not match the required format.';
            }
        }

        // Numeric min/max
        if (
            in_array($type, [FieldType::NUMBER, FieldType::CURRENCY], true)
            && is_numeric($value)
        ) {
            $numVal = (float) $value;
            if ($field->min_value !== null && $numVal < (float) $field->min_value) {
                return "The value must be at least {$field->min_value}.";
            }
            if ($field->max_value !== null && $numVal > (float) $field->max_value) {
                return "The value must not exceed {$field->max_value}.";
            }
        }

        // String length (text/textarea fields)
        if (
            in_array($type, [FieldType::TEXT, FieldType::TEXTAREA], true)
            && is_string($value)
        ) {
            $len = mb_strlen($value);
            if ($field->min_length !== null && $len < $field->min_length) {
                return "The value must be at least {$field->min_length} characters.";
            }
            if ($field->max_length !== null && $len > $field->max_length) {
                return "The value must not exceed {$field->max_length} characters.";
            }
        }

        // File constraints — value stored as ['mime' => '...', 'size_kb' => N]
        if ($type === FieldType::FILE && is_array($value)) {
            if (! empty($field->allowed_file_types)) {
                $mime = $value['mime'] ?? '';
                $mimeMap = [
                    'pdf' => 'application/pdf',
                    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'xls' => 'application/vnd.ms-excel',
                    'doc' => 'application/msword',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'png' => 'image/png',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                ];
                $allowedMimes = array_map(fn ($ext) => $mimeMap[$ext] ?? $ext, $field->allowed_file_types);
                if (! in_array($mime, $allowedMimes, true)) {
                    $exts = implode(', ', $field->allowed_file_types);

                    return "Only the following file types are allowed: {$exts}.";
                }
            }

            if ($field->max_file_size !== null) {
                $sizeKb = (int) ($value['size_kb'] ?? 0);
                if ($sizeKb > $field->max_file_size) {
                    return "The file must not exceed {$field->max_file_size} KB.";
                }
            }
        }

        return null;
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

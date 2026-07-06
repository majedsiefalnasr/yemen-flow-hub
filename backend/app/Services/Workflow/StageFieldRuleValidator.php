<?php

namespace App\Services\Workflow;

use App\Enums\FieldType;
use App\Models\EngineRequest;
use App\Models\EngineRequestDocument;
use App\Models\FieldDefinition;
use App\Models\StageFieldRule;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Support\TypedFieldValueValidator;
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
 *    non-empty value; required FILE fields additionally need ≥1 non-deleted
 *    document linked to the field on the same request (D10-N2 / F-7).
 *  - hidden: a non-visible field must NOT be present in the submitted data.
 *  - read-only: a non-editable field's submitted value must equal the previously
 *    stored value (no silent edits).
 *
 * Pure (`validateData`) so it is unit-testable without a DB.
 */
class StageFieldRuleValidator
{
    public function __construct(
        private DynamicFieldOptionsResolver $optionsResolver,
    ) {}

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
        ?User $actor = null,
        ?EngineRequest $request = null,
    ): array {
        $fields = FieldDefinition::query()
            ->where('workflow_version_id', $stage->workflow_version_id)
            ->get();
        $rules = $stage->stageFieldRules()->get()->keyBy('field_id');

        return $this->validateData($fields, $rules, $data, $previousData, $enforceRequired, $actor, $request);
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
        ?User $actor = null,
        ?EngineRequest $request = null,
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

            $previous = $previousData[$field->key] ?? null;

            if ($error = $this->checkConstraints($field, $value, $actor, $request, $previous)) {
                $errors[$field->key] = $error;

                continue;
            }

            $type = $field->type instanceof FieldType ? $field->type : null;

            if (
                $enforceRequired
                && $isRequired
                && $type === FieldType::FILE
                && $request !== null
                && ! $this->hasLinkedFileEvidence($field, $request)
            ) {
                $errors[$field->key] = 'An uploaded document is required for this field.';
            }
        }

        return $errors;
    }

    private function checkConstraints(
        FieldDefinition $field,
        mixed $value,
        ?User $actor = null,
        ?EngineRequest $request = null,
        mixed $previousValue = null,
    ): ?string {
        $type = $field->type instanceof FieldType ? $field->type : null;

        if (in_array($type, [FieldType::SELECT, FieldType::DYNAMIC_SELECT], true)) {
            if ($error = $this->validateSelectMembership($field, $value, $type, $actor, $request, $previousValue)) {
                return $error;
            }
        }

        if ($type !== null && ($error = TypedFieldValueValidator::validateRuntimeValue($type, $value))) {
            return $error;
        }

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

        if ($type === FieldType::FILE) {
            return $this->validateFileReferences($field, $value, $request);
        }

        return null;
    }

    /**
     * F-7: at stage exit, required FILE fields need server-side evidence — at least
     * one non-deleted document on the same request, linked via field_id, matching
     * mime/size constraints. Client metadata or unlinked uploads do not count.
     */
    private function hasLinkedFileEvidence(FieldDefinition $field, EngineRequest $request): bool
    {
        $documents = EngineRequestDocument::query()
            ->where('request_id', $request->id)
            ->where('field_id', $field->id)
            ->active()
            ->get();

        foreach ($documents as $document) {
            if (
                $this->mimeAllowedForField((string) $document->mime, $field)
                && $this->sizeAllowedForField((int) $document->size, $field)
            ) {
                return true;
            }
        }

        return false;
    }

    private function validateFileReferences(
        FieldDefinition $field,
        mixed $value,
        ?EngineRequest $request,
    ): ?string {
        $ids = $this->normalizeFileReferenceIds($value);
        if ($ids === false) {
            return 'File fields must reference uploaded documents.';
        }

        if ($request === null) {
            return 'File fields must reference uploaded documents.';
        }

        foreach ($ids as $id) {
            $document = EngineRequestDocument::query()
                ->where('id', $id)
                ->where('request_id', $request->id)
                ->first();

            if ($document === null) {
                return 'The referenced document was not found for this request.';
            }

            if (! $document->isActive()) {
                return 'The referenced document is no longer active.';
            }

            if ($document->field_id !== null && (int) $document->field_id !== (int) $field->id) {
                return 'The referenced document is not linked to this field.';
            }

            if (! $this->mimeAllowedForField((string) $document->mime, $field)) {
                $exts = implode(', ', $field->allowed_file_types ?? []);

                return "Only the following file types are allowed: {$exts}.";
            }

            if (! $this->sizeAllowedForField((int) $document->size, $field)) {
                return "The file must not exceed {$field->max_file_size} KB.";
            }
        }

        return null;
    }

    /**
     * @return list<int>|false
     */
    private function normalizeFileReferenceIds(mixed $value): array|false
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return [(int) $value];
        }

        if (! is_array($value)) {
            return false;
        }

        if (array_key_exists('mime', $value) || array_key_exists('size_kb', $value)) {
            return false;
        }

        $ids = [];
        foreach ($value as $item) {
            if (is_int($item) || (is_string($item) && ctype_digit((string) $item))) {
                $ids[] = (int) $item;

                continue;
            }

            return false;
        }

        return $ids;
    }

    private function mimeAllowedForField(string $mime, FieldDefinition $field): bool
    {
        if (empty($field->allowed_file_types)) {
            return true;
        }

        $allowedMimes = array_map(
            fn (string $ext) => $this->extensionToMime($ext),
            $field->allowed_file_types,
        );

        return in_array($mime, $allowedMimes, true);
    }

    private function sizeAllowedForField(int $sizeBytes, FieldDefinition $field): bool
    {
        if ($field->max_file_size === null) {
            return true;
        }

        $sizeKb = (int) ceil($sizeBytes / 1024);

        return $sizeKb <= $field->max_file_size;
    }

    private function extensionToMime(string $ext): string
    {
        return match ($ext) {
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => $ext,
        };
    }

    private function validateSelectMembership(
        FieldDefinition $field,
        mixed $value,
        FieldType $type,
        ?User $actor,
        ?EngineRequest $request,
        mixed $previousValue = null,
    ): ?string {
        if ($this->isUnchangedSelectValue($value, $previousValue)) {
            return null;
        }

        if ($field->multiple && is_array($value)) {
            foreach ($value as $item) {
                if ($error = $this->selectValueNotAllowed($field, $item, $type, $actor, $request)) {
                    return $error;
                }
            }

            return null;
        }

        return $this->selectValueNotAllowed($field, $value, $type, $actor, $request);
    }

    private function isUnchangedSelectValue(mixed $value, mixed $previousValue): bool
    {
        if (is_array($value) && is_array($previousValue)) {
            return $value === $previousValue;
        }

        if (is_array($value) || is_array($previousValue)) {
            return false;
        }

        return $this->valuesEqual($value, $previousValue);
    }

    private function selectValueNotAllowed(
        FieldDefinition $field,
        mixed $value,
        FieldType $type,
        ?User $actor,
        ?EngineRequest $request,
    ): ?string {
        $allowedValues = $this->allowedSelectValues($field, $type, $actor, $request);

        if ($allowedValues === []) {
            return 'The selected value is not a valid option.';
        }

        if (! $this->valueInOptionSet($value, $allowedValues)) {
            return 'The selected value is not a valid option.';
        }

        return null;
    }

    /**
     * @return list<int|string>
     */
    private function allowedSelectValues(
        FieldDefinition $field,
        FieldType $type,
        ?User $actor,
        ?EngineRequest $request,
    ): array {
        if ($type === FieldType::SELECT) {
            return collect($field->options ?? [])
                ->pluck('value')
                ->all();
        }

        if ($actor === null) {
            return [];
        }

        return array_column(
            $this->optionsResolver->resolve($field, $actor, $request),
            'value',
        );
    }

    /**
     * @param  list<int|string>  $allowedValues
     */
    private function valueInOptionSet(mixed $value, array $allowedValues): bool
    {
        foreach ($allowedValues as $allowed) {
            if ($this->valuesEqual($value, $allowed)) {
                return true;
            }
        }

        return false;
    }

    private function valuesEqual(mixed $left, mixed $right): bool
    {
        return $left === $right || (string) $left === (string) $right;
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

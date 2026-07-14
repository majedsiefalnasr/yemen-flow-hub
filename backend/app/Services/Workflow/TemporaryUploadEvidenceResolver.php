<?php

namespace App\Services\Workflow;

use App\Enums\DocumentScanStatus;
use App\Enums\FieldType;
use App\Exceptions\EngineException;
use App\Models\FieldDefinition;
use App\Models\TemporaryUpload;
use App\Models\User;
use App\Models\WorkflowVersion;
use App\Support\FileFieldConstraint;
use Illuminate\Support\Collection;

/**
 * Pre-creation Pass 1 evidence check for FILE-type fields, run against
 * TemporaryUpload rows before any EngineRequest exists. Does not replace
 * StageFieldRuleValidator — it validates the tokens themselves (ownership,
 * scope, scan status, mime/size), then the caller promotes the accepted
 * uploads to real EngineRequestDocument rows and StageFieldRuleValidator
 * runs its own, unmodified, strict pass against those real rows a moment
 * later, inside the transaction (Pass 2).
 */
class TemporaryUploadEvidenceResolver
{
    /**
     * @param  array<string, mixed>  $data  submitted field data (FILE fields hold token or token[] values)
     * @param  list<string>  $uploadTokens  every token declared in the request's upload_tokens list
     * @return array<string, TemporaryUpload[]> resolved uploads keyed by field key, only present when non-empty
     *
     * @throws EngineException on any bijection or per-token violation
     */
    public function resolve(
        WorkflowVersion $version,
        array $data,
        array $uploadTokens,
        User $actor,
    ): array {
        $fileFields = FieldDefinition::query()
            ->where('workflow_version_id', $version->id)
            ->where('type', FieldType::FILE->value)
            ->get()
            ->keyBy('key');

        $tokensByField = $this->extractTokensPerField($data, $fileFields);
        $this->assertBijection($tokensByField, $uploadTokens);

        $resolved = [];
        $errors = [];

        foreach ($tokensByField as $fieldKey => $tokens) {
            $field = $fileFields->get($fieldKey);
            $uploads = [];

            foreach ($tokens as $token) {
                $upload = TemporaryUpload::query()->where('token', $token)->first();

                if ($upload === null) {
                    throw EngineException::uploadTokenInvalid();
                }
                if ((int) $upload->user_id !== (int) $actor->id) {
                    throw EngineException::uploadTokenForbidden();
                }
                if ($upload->organization_id !== null && (int) $upload->organization_id !== (int) $actor->organization_id) {
                    throw EngineException::uploadTokenForbidden();
                }
                if ($upload->bank_id !== null && (int) $upload->bank_id !== (int) $actor->bank_id) {
                    throw EngineException::uploadTokenForbidden();
                }
                if ((int) $upload->workflow_version_id !== (int) $version->id) {
                    throw EngineException::uploadTokenWrongWorkflow();
                }
                if ((int) $upload->field_id !== (int) $field->id) {
                    throw EngineException::uploadTokenWrongField();
                }
                if ($upload->consumed_at !== null) {
                    throw EngineException::uploadTokenAlreadyConsumed();
                }
                if ($upload->expires_at->isPast()) {
                    throw EngineException::uploadTokenExpired();
                }
                if ($upload->scan_status === DocumentScanStatus::Pending) {
                    throw EngineException::scanInProgress();
                }
                if ($upload->scan_status !== DocumentScanStatus::Clean) {
                    throw EngineException::uploadNotSafe();
                }
                if (! FileFieldConstraint::mimeAllowed((string) $upload->mime, $field)) {
                    $exts = implode(', ', $field->allowed_file_types ?? []);
                    $errors[$fieldKey] = "Only the following file types are allowed: {$exts}.";

                    continue;
                }
                if (! FileFieldConstraint::sizeAllowed((int) $upload->size, $field)) {
                    $errors[$fieldKey] = "The file must not exceed {$field->max_file_size} KB.";

                    continue;
                }

                $uploads[] = $upload;
            }

            if ($uploads !== []) {
                $resolved[$fieldKey] = $uploads;
            }
        }

        if ($errors !== []) {
            throw EngineException::stageFieldsInvalid($errors);
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<string, FieldDefinition>  $fileFields
     * @return array<string, list<string>>
     */
    private function extractTokensPerField(array $data, Collection $fileFields): array
    {
        $tokensByField = [];

        foreach ($fileFields as $key => $field) {
            if (! array_key_exists($key, $data) || $data[$key] === null) {
                continue;
            }

            $value = $data[$key];
            $tokens = is_array($value) ? $value : [$value];
            $tokens = array_values(array_filter($tokens, fn ($t) => is_string($t) && $t !== ''));

            if ($tokens !== []) {
                $tokensByField[$key] = $tokens;
            }
        }

        return $tokensByField;
    }

    /**
     * Exact bijection: every token referenced in $data must appear exactly
     * once in $uploadTokens, and vice versa. Duplicates (within one field,
     * or across two fields) and stray unreferenced tokens are both errors.
     *
     * @param  array<string, list<string>>  $tokensByField
     * @param  list<string>  $uploadTokens
     */
    private function assertBijection(array $tokensByField, array $uploadTokens): void
    {
        $referenced = [];
        $duplicated = [];

        foreach ($tokensByField as $tokens) {
            foreach ($tokens as $token) {
                if (isset($referenced[$token])) {
                    $duplicated[] = $token;
                }
                $referenced[$token] = true;
            }
        }

        $declared = [];
        $declaredDuplicated = [];
        foreach ($uploadTokens as $token) {
            if (isset($declared[$token])) {
                $declaredDuplicated[] = $token;
            }
            $declared[$token] = true;
        }

        $missingFromDeclared = array_keys(array_diff_key($referenced, $declared));
        $extraInDeclared = array_keys(array_diff_key($declared, $referenced));

        if ($duplicated !== [] || $declaredDuplicated !== [] || $missingFromDeclared !== [] || $extraInDeclared !== []) {
            throw EngineException::uploadTokenMismatch([
                'duplicated_in_data' => array_values(array_unique($duplicated)),
                'duplicated_in_upload_tokens' => array_values(array_unique($declaredDuplicated)),
                'missing_from_upload_tokens' => $missingFromDeclared,
                'not_referenced_in_data' => $extraInDeclared,
            ]);
        }
    }
}

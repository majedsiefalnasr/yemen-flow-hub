<?php

namespace App\Services\Workflow;

use App\Enums\FieldSemanticTag;
use App\Enums\StageSemanticRole;
use App\Enums\WorkflowEffectCode;
use App\Models\EngineRequest;
use App\Models\FieldDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Illuminate\Support\Collection;

/**
 * Resolves semantic tags and stage roles for a workflow version or runtime request.
 */
class SemanticResolver
{
    public function __construct(
        private readonly SemanticRegistry $registry,
    ) {}

    public function fieldForTag(WorkflowVersion $version, FieldSemanticTag $tag): ?FieldDefinition
    {
        $explicit = FieldDefinition::query()
            ->where('workflow_version_id', $version->id)
            ->where('semantic_tag', $tag->value)
            ->first();

        if ($explicit !== null) {
            return $explicit;
        }

        foreach ($this->registry->fieldKeyAliases() as $key => $aliasTag) {
            if ($aliasTag !== $tag) {
                continue;
            }

            $field = FieldDefinition::query()
                ->where('workflow_version_id', $version->id)
                ->where('key', $key)
                ->first();

            if ($field !== null) {
                return $field;
            }
        }

        return null;
    }

    public function resolveFieldValue(EngineRequest $request, FieldSemanticTag $tag): mixed
    {
        $version = WorkflowVersion::query()->find($request->workflow_version_id);
        if ($version === null) {
            return null;
        }

        $field = $this->fieldForTag($version, $tag);
        if ($field !== null) {
            $data = $request->data ?? [];
            if (array_key_exists($field->key, $data)) {
                return $data[$field->key];
            }
        }

        return match ($tag) {
            FieldSemanticTag::INVOICE_NUMBER => $request->invoice_number,
            FieldSemanticTag::REQUESTED_PERCENTAGE => $request->request_percentage,
            FieldSemanticTag::AMOUNT => $request->amount,
            FieldSemanticTag::CURRENCY => $request->currency,
            FieldSemanticTag::MERCHANT_TAX_NUMBER => $request->merchant?->tax_number,
            default => null,
        };
    }

    public function stageForRole(WorkflowVersion $version, StageSemanticRole $role): ?WorkflowStage
    {
        $explicit = WorkflowStage::query()
            ->where('workflow_version_id', $version->id)
            ->where('semantic_role', $role->value)
            ->orderBy('id')
            ->first();

        if ($explicit !== null) {
            return $explicit;
        }

        foreach ($this->registry->stageCodeAliases() as $code => $aliasRole) {
            if ($aliasRole !== $role) {
                continue;
            }

            $stage = WorkflowStage::query()
                ->where('workflow_version_id', $version->id)
                ->where('code', $code)
                ->first();

            if ($stage !== null) {
                return $stage;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, FieldDefinition>  $fields
     * @return array<int, array{code: string, target: string, message: string}>
     */
    public function publishErrors(WorkflowVersion $version, Collection $stages, Collection $fields): array
    {
        $errors = [];

        $tagCounts = $fields
            ->pluck('semantic_tag')
            ->filter()
            ->map(static fn ($tag) => $tag instanceof FieldSemanticTag ? $tag->value : (string) $tag)
            ->countBy();
        foreach ($tagCounts as $tag => $count) {
            if ($count > 1) {
                $errors[] = [
                    'code' => 'SEMANTIC_MAPPING_AMBIGUOUS',
                    'target' => "semantic_tag:{$tag}",
                    'message' => "Multiple fields declare semantic tag {$tag}.",
                ];
            }
        }

        foreach ($stages as $stage) {
            $effects = $stage->attached_effects ?? [];
            foreach ($effects as $effectValue) {
                $effect = WorkflowEffectCode::tryFrom((string) $effectValue);
                if ($effect === null) {
                    continue;
                }

                foreach ($this->registry->requiredTagsForEffect($effect) as $tag) {
                    if ($this->fieldForTag($version, $tag) === null) {
                        $errors[] = [
                            'code' => 'SEMANTIC_MAPPING_MISSING',
                            'target' => "effect:{$effect->value}:{$tag->value}",
                            'message' => "Effect {$effect->value} requires semantic tag {$tag->value}.",
                        ];
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @param  Collection<int, WorkflowStage>  $stages
     * @return array<int, array{code: string, target: string, message: string}>
     */
    public function publishWarnings(WorkflowVersion $version, Collection $stages): array
    {
        $warnings = [];

        foreach ($this->registry->dashboardRoles() as $role) {
            if ($this->stageForRole($version, $role) === null) {
                $warnings[] = [
                    'code' => 'SEMANTIC_DASHBOARD_ROLE_GAP',
                    'target' => "semantic_role:{$role->value}",
                    'message' => "No stage declares dashboard semantic role {$role->value}.",
                ];
            }
        }

        return $warnings;
    }
}

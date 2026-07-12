<?php

namespace App\Services\Workflow;

use App\Enums\EffectEnforcementPolicy;
use App\Enums\FieldSemanticTag;
use App\Enums\StageSemanticRole;
use App\Enums\WorkflowEffectCode;

/**
 * Code-only registry of semantic tags, dashboard roles, and workflow effects.
 */
class SemanticRegistry
{
    /**
     * Legacy field-key aliases used when semantic_tag is unset (migration fallback).
     *
     * @return array<string, FieldSemanticTag>
     */
    public function fieldKeyAliases(): array
    {
        return [
            'invoice_number' => FieldSemanticTag::INVOICE_NUMBER,
            'request_percentage' => FieldSemanticTag::REQUESTED_PERCENTAGE,
            'taxNumber' => FieldSemanticTag::MERCHANT_TAX_NUMBER,
            'supplierName' => FieldSemanticTag::SUPPLIER_NAME,
            'importType' => FieldSemanticTag::GOODS_DESCRIPTION,
            'arrivalPort' => FieldSemanticTag::PORT_OF_ENTRY,
            'amount' => FieldSemanticTag::AMOUNT,
            'currency' => FieldSemanticTag::CURRENCY,
        ];
    }

    /**
     * @return array<string, StageSemanticRole>
     */
    public function stageCodeAliases(): array
    {
        return [
            'CREATE' => StageSemanticRole::INITIAL_ENTRY,
            'INTERNAL' => StageSemanticRole::BANK_REVIEW,
            'SUPPORT' => StageSemanticRole::SUPPORT_REVIEW,
            'EXEC' => StageSemanticRole::EXECUTIVE_REVIEW,
            'FX' => StageSemanticRole::SWIFT,
            'FX_CONFIRM' => StageSemanticRole::FX_CONFIRMATION,
            'FINAL' => StageSemanticRole::FINAL,
        ];
    }

    /**
     * @return list<FieldSemanticTag>
     */
    public function requiredTagsForEffect(WorkflowEffectCode $effect): array
    {
        return match ($effect) {
            WorkflowEffectCode::FINANCING_RESERVE => [
                FieldSemanticTag::MERCHANT_TAX_NUMBER,
                FieldSemanticTag::INVOICE_NUMBER,
                FieldSemanticTag::REQUESTED_PERCENTAGE,
            ],
            WorkflowEffectCode::FX_CONFIRMATION_PDF => [
                FieldSemanticTag::AMOUNT,
                FieldSemanticTag::CURRENCY,
                FieldSemanticTag::INVOICE_NUMBER,
                FieldSemanticTag::MERCHANT_TAX_NUMBER,
                FieldSemanticTag::SUPPLIER_NAME,
                FieldSemanticTag::GOODS_DESCRIPTION,
                FieldSemanticTag::PORT_OF_ENTRY,
            ],
        };
    }

    public function enforcementPolicy(WorkflowEffectCode $effect): EffectEnforcementPolicy
    {
        return match ($effect) {
            WorkflowEffectCode::FINANCING_RESERVE,
            WorkflowEffectCode::FX_CONFIRMATION_PDF => EffectEnforcementPolicy::FAIL,
        };
    }

    /**
     * Dashboard buckets should resolve at least one stage per role (warn-only).
     *
     * @return list<StageSemanticRole>
     */
    public function dashboardRoles(): array
    {
        return [
            StageSemanticRole::INITIAL_ENTRY,
            StageSemanticRole::BANK_REVIEW,
            StageSemanticRole::SUPPORT_REVIEW,
            StageSemanticRole::EXECUTIVE_REVIEW,
            StageSemanticRole::SWIFT,
            StageSemanticRole::FX_CONFIRMATION,
            StageSemanticRole::FINAL,
        ];
    }

    /**
     * @return list<string>
     */
    public function registeredEffectCodes(): array
    {
        return array_map(
            static fn (WorkflowEffectCode $code): string => $code->value,
            WorkflowEffectCode::cases(),
        );
    }
}

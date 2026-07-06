<?php

namespace App\Services\Workflow\Effects;

use App\Enums\FieldSemanticTag;
use App\Exceptions\SemanticMappingUnresolvedException;
use App\Models\EngineRequest;
use App\Models\User;
use App\Models\WorkflowTransition;
use App\Services\Workflow\Engine\EngineFinancingLedger;
use App\Services\Workflow\SemanticResolver;

class FinancingLedgerEffect
{
    public function __construct(
        private EngineFinancingLedger $ledger,
        private SemanticResolver $resolver,
    ) {}

    public function __invoke(EngineRequest $request, WorkflowTransition $transition, User $actor): void
    {
        $taxNumber = $this->resolver->resolveFieldValue($request, FieldSemanticTag::MERCHANT_TAX_NUMBER);
        $invoiceNumber = $this->resolver->resolveFieldValue($request, FieldSemanticTag::INVOICE_NUMBER);
        $percent = (float) ($this->resolver->resolveFieldValue($request, FieldSemanticTag::REQUESTED_PERCENTAGE) ?? 0);

        if ($taxNumber === null) {
            throw SemanticMappingUnresolvedException::forTag('FINANCING_MAPPING_UNRESOLVED', FieldSemanticTag::MERCHANT_TAX_NUMBER);
        }

        if ($invoiceNumber === null) {
            throw SemanticMappingUnresolvedException::forTag('FINANCING_MAPPING_UNRESOLVED', FieldSemanticTag::INVOICE_NUMBER);
        }

        if ($percent <= 0) {
            return;
        }

        $this->ledger->assertWithinLimit(
            (string) $taxNumber,
            (string) $invoiceNumber,
            $percent,
            excludeRequestId: $request->id,
        );
    }
}

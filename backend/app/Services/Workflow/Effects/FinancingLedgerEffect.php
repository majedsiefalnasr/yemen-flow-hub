<?php

namespace App\Services\Workflow\Effects;

use App\Models\EngineRequest;
use App\Models\User;
use App\Models\WorkflowTransition;
use App\Services\Workflow\Engine\EngineFinancingLedger;

/**
 * DI-4 stage-entry effect: enforces the global cross-bank financing cap when a request
 * enters the configured financing-reserve stage. Runs inside the transition transaction
 * (via StageHookRegistry), so a breach throws FinancingLimitExceededException and rolls
 * the whole transition back atomically (AC1/AC3).
 *
 * Reads the indexed Hybrid columns: request_percentage + invoice_number off the engine
 * row, trader tax via the merchant relation. Excludes the request itself from the sum.
 */
class FinancingLedgerEffect
{
    public function __construct(
        private EngineFinancingLedger $ledger,
    ) {}

    public function __invoke(EngineRequest $request, WorkflowTransition $transition, User $actor): void
    {
        $taxNumber = $request->merchant?->tax_number;
        $invoiceNumber = $request->invoice_number;
        $percent = (float) ($request->request_percentage ?? 0);

        // Nothing to enforce without an invoice key or a positive allocation.
        if ($taxNumber === null || $invoiceNumber === null || $percent <= 0) {
            return;
        }

        // assertWithinLimit re-acquires the named lock and row-locks the matching rows
        // inside its own transaction; nested within the outer transition transaction it
        // still serializes concurrent reservers on the same (tax, invoice) key and
        // throws on breach — rolling the transition back.
        $this->ledger->assertWithinLimit(
            $taxNumber,
            $invoiceNumber,
            $percent,
            excludeRequestId: $request->id,
        );
    }
}

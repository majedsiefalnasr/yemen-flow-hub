<?php

namespace Database\Seeders\Support;

use App\Models\EngineRequest;
use App\Support\EngineRequestStatus;
use App\Support\InvoiceKey;
use InvalidArgumentException;

/**
 * Validates direct-insert anchor requests against invariant rules.
 *
 * Direct-insert anchors bypass transition logic and are inserted with pre-computed state.
 * Every anchor must pass these invariant checks before being considered valid QA/test data.
 *
 * Spec: backend/docs/superpowers/specs/2026-07-07-engine-demo-seeder-redesign-design.md § Invariant validator
 */
final class EngineRequestAnchorInvariantValidator
{
    /**
     * Validate an EngineRequest anchor after insertion.
     *
     * @throws InvalidArgumentException on invariant violation
     */
    public function validate(EngineRequest $request): void
    {
        $request->loadMissing(['workflowVersion.definition', 'currentStage', 'history']);

        $this->validateWorkflowVersion($request);
        $this->validateStage($request);
        $this->validateStatusOutcomeMapping($request);
        $this->validateClaimState($request);
        $this->validateDataFields($request);
        $this->validateProjections($request);
        $this->validateHistory($request);
    }

    /**
     * Workflow version must exist and be IMPORT_FINANCING v1.
     */
    private function validateWorkflowVersion(EngineRequest $request): void
    {
        $version = $request->workflowVersion;

        if (! $version) {
            throw new InvalidArgumentException(
                sprintf('Anchor %s: workflow_version_id=%s not found', $request->reference, $request->workflow_version_id)
            );
        }

        $definition = $version->definition;

        if ($definition->code !== 'IMPORT_FINANCING') {
            throw new InvalidArgumentException(
                sprintf('Anchor %s: workflow definition must be IMPORT_FINANCING, got %s', $request->reference, $definition->code)
            );
        }

        if ($version->version_number !== 1) {
            throw new InvalidArgumentException(
                sprintf('Anchor %s: workflow version must be 1, got %d', $request->reference, $version->version_number)
            );
        }
    }

    /**
     * Current stage must belong to the request's workflow version.
     */
    private function validateStage(EngineRequest $request): void
    {
        $stage = $request->currentStage;

        if (! $stage) {
            throw new InvalidArgumentException(
                sprintf('Anchor %s: current_stage_id=%s not found', $request->reference, $request->current_stage_id)
            );
        }

        if ($stage->workflow_version_id !== $request->workflow_version_id) {
            throw new InvalidArgumentException(
                sprintf('Anchor %s: current stage belongs to different workflow version', $request->reference)
            );
        }
    }

    /**
     * Runtime status must be compatible with stage and final_outcome.
     *
     * Active requests: status = ACTIVE, final_outcome null
     * Terminal (is_final) requests: status matches final_outcome mapping
     * ABANDONED is the one status the engine can reach without a terminal
     * stage hop (EngineTransitionService::abandonDraft() only requires the
     * *current* stage to be is_initial) — so it is valid on a non-final stage.
     */
    private function validateStatusOutcomeMapping(EngineRequest $request): void
    {
        $stage = $request->currentStage;
        $isTerminal = $stage->is_final;

        if (! $isTerminal) {
            if (! in_array($request->status, [EngineRequestStatus::ACTIVE, EngineRequestStatus::ABANDONED], true)) {
                throw new InvalidArgumentException(
                    sprintf('Anchor %s: non-terminal stage must have status=ACTIVE or ABANDONED, got %s', $request->reference, $request->status)
                );
            }
        } else {
            // $stage->final_outcome is cast to FinalOutcome enum (or null).
            $expectedStatus = EngineRequestStatus::fromFinalOutcome($stage->final_outcome);

            if ($request->status !== $expectedStatus) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Anchor %s: terminal stage with outcome %s requires status=%s, got %s',
                        $request->reference,
                        $stage->final_outcome?->value,
                        $expectedStatus,
                        $request->status
                    )
                );
            }
        }
    }

    /**
     * Claim state validation (WP-5).
     *
     * Rules:
     * - If stage requires_claim and request is ACTIVE:
     *   - If claimed: claimed_by, claimed_at, claim_expires_at must be set; claim_stage_id must equal current_stage_id
     *   - If not claimed: claim columns must be null
     * - If stage doesn't require_claim: all claim columns must be null
     * - Terminal requests must have claim columns null
     */
    private function validateClaimState(EngineRequest $request): void
    {
        $stage = $request->currentStage;
        $isClaimed = $request->claimed_by !== null;
        $isTerminal = $stage->is_final;

        // Terminal requests must have no claim
        if ($isTerminal) {
            if ($isClaimed || $request->claimed_at !== null || $request->claim_expires_at !== null || $request->claim_stage_id !== null) {
                throw new InvalidArgumentException(
                    sprintf('Anchor %s: terminal request must have claim columns null', $request->reference)
                );
            }

            return;
        }

        // Non-terminal stage that doesn't require claim: claim columns must be null
        if (! $stage->requires_claim) {
            if ($this->hasAnyClaimColumn($request)) {
                throw new InvalidArgumentException(
                    sprintf('Anchor %s: stage %s does not require_claim, but claim columns are set', $request->reference, $stage->code)
                );
            }

            return;
        }

        // Stage requires claim
        if ($isClaimed) {
            if ($request->claimed_at === null || $request->claim_expires_at === null) {
                throw new InvalidArgumentException(
                    sprintf('Anchor %s: claimed request must have claimed_at and claim_expires_at', $request->reference)
                );
            }

            if ($request->claim_stage_id !== $request->current_stage_id) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Anchor %s: claim_stage_id must equal current_stage_id; got %s vs %s',
                        $request->reference,
                        $request->claim_stage_id,
                        $request->current_stage_id
                    )
                );
            }
        } else {
            if ($request->claimed_at !== null || $request->claim_expires_at !== null || $request->claim_stage_id !== null) {
                throw new InvalidArgumentException(
                    sprintf('Anchor %s: unclaimed request cannot have partial claim columns', $request->reference)
                );
            }
        }
    }

    /**
     * Lovable prototype keys for the three semantic-projection fields. Published
     * v1 field keys are otherwise legitimately camelCase (taxNumber,
     * importerName, docCommercialInvoice, ...) — only these three aliases are
     * banned, per the YFH field-key mapping in the design spec.
     */
    private const BANNED_PROTOTYPE_KEYS = ['financeAmount', 'invoiceNumber', 'requestPercentage'];

    /**
     * Data keys must be a subset of published field definitions, and must not
     * use the banned Lovable prototype aliases for the projection fields.
     */
    private function validateDataFields(EngineRequest $request): void
    {
        $data = $request->data ?? [];
        if (! is_array($data)) {
            throw new InvalidArgumentException(
                sprintf('Anchor %s: data must be an array', $request->reference)
            );
        }

        $version = $request->workflowVersion;
        $publishedFieldKeys = $version->fields()->pluck('key')->toArray();
        $dataKeys = array_keys($data);

        $bannedKeys = array_intersect($dataKeys, self::BANNED_PROTOTYPE_KEYS);
        if (! empty($bannedKeys)) {
            throw new InvalidArgumentException(
                sprintf('Anchor %s: data contains banned prototype keys: %s', $request->reference, implode(', ', $bannedKeys))
            );
        }

        $unauthorizedKeys = array_diff($dataKeys, $publishedFieldKeys);
        if (! empty($unauthorizedKeys)) {
            throw new InvalidArgumentException(
                sprintf('Anchor %s: data contains unpublished field keys: %s', $request->reference, implode(', ', $unauthorizedKeys))
            );
        }
    }

    /**
     * Semantic projections must be consistent.
     *
     * - amount must match data['amount']
     * - invoice_number must match data['invoice_number']
     * - request_percentage must match data['request_percentage']
     * - invoice_number_normalized must match InvoiceKey::normalize(invoice_number)
     */
    private function validateProjections(EngineRequest $request): void
    {
        $data = $request->data ?? [];

        if ($request->amount !== null && array_key_exists('amount', $data) && $data['amount'] !== null) {
            if ((float) $request->amount !== (float) $data['amount']) {
                throw new InvalidArgumentException(
                    sprintf('Anchor %s: amount mismatch; column=%s, data=%s', $request->reference, $request->amount, $data['amount'])
                );
            }
        }

        if ($request->invoice_number !== null && array_key_exists('invoice_number', $data) && $data['invoice_number'] !== null) {
            if ($request->invoice_number !== $data['invoice_number']) {
                throw new InvalidArgumentException(
                    sprintf('Anchor %s: invoice_number mismatch; column=%s, data=%s', $request->reference, $request->invoice_number, $data['invoice_number'])
                );
            }
        }

        if ($request->request_percentage !== null && array_key_exists('request_percentage', $data) && $data['request_percentage'] !== null) {
            if ((float) $request->request_percentage !== (float) $data['request_percentage']) {
                throw new InvalidArgumentException(
                    sprintf('Anchor %s: request_percentage mismatch; column=%s, data=%s', $request->reference, $request->request_percentage, $data['request_percentage'])
                );
            }
        }

        if ($request->invoice_number_normalized !== null && $request->invoice_number !== null) {
            $invoiceKey = app(InvoiceKey::class);
            $expectedNormalized = $invoiceKey->normalize($request->invoice_number);

            if ($request->invoice_number_normalized !== $expectedNormalized) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Anchor %s: invoice_number_normalized mismatch; column=%s, expected=%s',
                        $request->reference,
                        $request->invoice_number_normalized,
                        $expectedNormalized
                    )
                );
            }
        }
    }

    /**
     * History validation.
     *
     * - Transitions form a connected path (each to_stage matches next from_stage)
     * - All action codes exist on the published workflow
     * - Terminal path must end with a valid terminal transition
     */
    private function validateHistory(EngineRequest $request): void
    {
        $entries = $request->history()->orderBy('created_at')->get();

        if ($entries->isEmpty()) {
            throw new InvalidArgumentException(
                sprintf('Anchor %s: history must have at least one entry', $request->reference)
            );
        }

        $version = $request->workflowVersion;
        $transitionActionCodes = $version->transitions()
            ->with('action:id,code')
            ->get()
            ->pluck('action.code')
            ->filter()
            ->all();

        $first = $entries->first();
        if ($first->from_stage_id !== null) {
            if (! $version->stages()->where('is_initial', true)->where('id', $first->from_stage_id)->exists()) {
                throw new InvalidArgumentException(
                    sprintf('Anchor %s: first history entry must start from initial stage or null', $request->reference)
                );
            }
        }

        $previousToStageId = null;
        foreach ($entries as $index => $entry) {
            // ABANDON is a lifecycle exit written by EngineTransitionService::abandonDraft(),
            // not a WorkflowTransition — it has to_stage_id=null and no registered action
            // row, and current_stage_id intentionally stays on the pre-abandon stage.
            if ($entry->action_code === 'ABANDON') {
                continue;
            }

            if ($previousToStageId !== null && $entry->from_stage_id !== $previousToStageId) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Anchor %s: history path broken at entry %d',
                        $request->reference,
                        $index
                    )
                );
            }

            if ($entry->action_code !== null && ! in_array($entry->action_code, $transitionActionCodes, true)) {
                throw new InvalidArgumentException(
                    sprintf('Anchor %s: action code %s not found on workflow', $request->reference, $entry->action_code)
                );
            }

            $previousToStageId = $entry->to_stage_id;
        }

        $lastEntry = $entries->last();
        if ($lastEntry->action_code === 'ABANDON') {
            return;
        }

        if ((int) $lastEntry->to_stage_id !== (int) $request->current_stage_id) {
            throw new InvalidArgumentException(
                sprintf('Anchor %s: latest history to_stage must equal current_stage_id', $request->reference)
            );
        }
    }

    private function hasAnyClaimColumn(EngineRequest $request): bool
    {
        return $request->claimed_by !== null
            || $request->claimed_at !== null
            || $request->claim_expires_at !== null
            || $request->claim_stage_id !== null;
    }
}

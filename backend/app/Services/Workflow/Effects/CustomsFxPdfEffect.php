<?php

namespace App\Services\Workflow\Effects;

use App\Enums\AuditAction;
use App\Models\CustomsDeclaration;
use App\Models\EngineRequest;
use App\Models\User;
use App\Models\WorkflowTransition;
use App\Services\Audit\AuditService;
use App\Services\Customs\CustomsDeclarationGenerator;

/**
 * DI-4 stage-entry effect: generates the external FX-confirmation (customs) PDF when a
 * request enters the configured FX/FINAL stage, and persists a CustomsDeclaration linked
 * to the engine request. Runs inside the transition transaction; a generation failure
 * throws and rolls the transition back atomically (AC1/AC3). Reuses the shared,
 * model-agnostic CustomsDeclarationGenerator (same render/numbering/storage as legacy).
 */
class CustomsFxPdfEffect
{
    public function __construct(
        private CustomsDeclarationGenerator $generator,
        private AuditService $auditService,
    ) {}

    public function __invoke(EngineRequest $request, WorkflowTransition $transition, User $actor): void
    {
        // Idempotency: one declaration per engine request.
        if (CustomsDeclaration::query()->where('engine_request_id', $request->id)->exists()) {
            return;
        }

        $request->loadMissing('bank');
        $artifacts = $this->generator->generate($this->snapshot($request), $actor, $request->id);

        $declaration = CustomsDeclaration::create([
            'engine_request_id' => $request->id,
            'declaration_number' => $artifacts['declaration_number'],
            'issued_by' => $actor->id,
            'issued_at' => $artifacts['issued_at'],
            'pdf_path' => $artifacts['pdf_path'],
            'metadata' => $artifacts['snapshot'],
        ]);

        $this->auditService->log(
            AuditAction::FX_CONFIRMATION_ISSUED,
            $actor,
            $request,
            ['declaration_id' => $declaration->id, 'declaration_number' => $artifacts['declaration_number']],
        );
    }

    /**
     * Builds the generator snapshot (same keys as CustomsService::snapshot()) from the
     * engine row: typed columns for amount/currency, JSON data for the domain text fields.
     *
     * @return array<string, mixed>
     */
    private function snapshot(EngineRequest $request): array
    {
        $data = $request->data ?? [];

        return [
            'reference_number' => $request->reference,
            'bank' => [
                'id' => $request->bank?->id,
                'name' => $request->bank?->name,
                'code' => $request->bank?->code,
            ],
            'supplier_name' => $data['supplier_name'] ?? null,
            'amount' => (float) $request->amount,
            'currency' => $request->currency,
            'goods_description' => $data['goods_description'] ?? null,
            'port_of_entry' => $data['port_of_entry'] ?? null,
            'bank_approved_at' => null,
            'support_approved_at' => null,
            'executive_decided_at' => null,
        ];
    }
}

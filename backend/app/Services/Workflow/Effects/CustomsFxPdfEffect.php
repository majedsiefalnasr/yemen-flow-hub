<?php

namespace App\Services\Workflow\Effects;

use App\Enums\AuditAction;
use App\Enums\FieldSemanticTag;
use App\Enums\StageSemanticRole;
use App\Exceptions\SemanticMappingUnresolvedException;
use App\Models\CustomsDeclaration;
use App\Models\EngineRequest;
use App\Models\User;
use App\Models\WorkflowTransition;
use App\Services\Audit\AuditService;
use App\Services\Customs\CustomsDeclarationGenerator;
use App\Services\Workflow\SemanticResolver;

class CustomsFxPdfEffect
{
    public function __construct(
        private CustomsDeclarationGenerator $generator,
        private AuditService $auditService,
        private SemanticResolver $resolver,
    ) {}

    public function __invoke(EngineRequest $request, WorkflowTransition $transition, User $actor): void
    {
        if (CustomsDeclaration::query()->where('engine_request_id', $request->id)->exists()) {
            return;
        }

        $request->loadMissing('bank', 'workflowVersion');
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
     * @return array<string, mixed>
     */
    private function snapshot(EngineRequest $request): array
    {
        $amount = $this->resolver->resolveFieldValue($request, FieldSemanticTag::AMOUNT);
        $currency = $this->resolver->resolveFieldValue($request, FieldSemanticTag::CURRENCY);
        $supplierName = $this->resolver->resolveFieldValue($request, FieldSemanticTag::SUPPLIER_NAME);
        $goodsDescription = $this->resolver->resolveFieldValue($request, FieldSemanticTag::GOODS_DESCRIPTION);
        $portOfEntry = $this->resolver->resolveFieldValue($request, FieldSemanticTag::PORT_OF_ENTRY);

        if ($amount === null) {
            throw SemanticMappingUnresolvedException::forTag('FX_MAPPING_UNRESOLVED', FieldSemanticTag::AMOUNT);
        }

        if ($currency === null) {
            throw SemanticMappingUnresolvedException::forTag('FX_MAPPING_UNRESOLVED', FieldSemanticTag::CURRENCY);
        }

        return [
            'reference_number' => $request->reference,
            'bank' => [
                'id' => $request->bank?->id,
                'name' => $request->bank?->name,
                'code' => $request->bank?->code,
            ],
            'supplier_name' => $supplierName,
            'amount' => (float) $amount,
            'currency' => $currency,
            'goods_description' => $goodsDescription,
            'port_of_entry' => $portOfEntry,
            'bank_approved_at' => $this->firstEnteredAt($request, StageSemanticRole::BANK_REVIEW),
            'support_approved_at' => $this->firstEnteredAt($request, StageSemanticRole::SUPPORT_REVIEW),
            'executive_decided_at' => $this->firstEnteredAt($request, StageSemanticRole::EXECUTIVE_VOTE),
        ];
    }

    private function firstEnteredAt(EngineRequest $request, StageSemanticRole $role): ?string
    {
        $version = $request->workflowVersion;
        if ($version === null) {
            return null;
        }

        $stage = $this->resolver->stageForRole($version, $role);
        if ($stage === null) {
            return null;
        }

        $history = $request->history()
            ->where('to_stage_id', $stage->id)
            ->orderBy('created_at')
            ->first();

        return $history?->created_at?->toISOString();
    }
}

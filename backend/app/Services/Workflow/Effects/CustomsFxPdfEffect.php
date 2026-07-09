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
use App\Services\Customs\FxOfficialIssuerResolver;
use App\Services\Operations\OperationalAlertLogger;
use App\Services\Workflow\SemanticResolver;
use Illuminate\Support\Facades\DB;

/**
 * ARCH-007: the transition's row lock (EngineTransitionService::execute()'s
 * lockForUpdate()) must not span the PDF render — that's CPU/IO work that
 * has nothing to do with the state change being locked. snapshot() (cheap
 * field-mapping resolution that can validly abort the transition via
 * SemanticMappingUnresolvedException) runs inline, inside the lock, exactly
 * as before. The actual render + disk write + CustomsDeclaration creation
 * runs in a DB::afterCommit() callback, after the transition's transaction
 * (and its row lock) has already released. A render failure at that point
 * cannot roll back an already-committed transition -- it alerts instead
 * (OperationalAlertLogger), the same fail-visible pattern QUEUE-001 uses for
 * the document scan job. This preserves AGENTS.md's "a committed FX
 * confirmation always has its document" guarantee for the success path,
 * while making a post-commit render failure an operationally visible,
 * retryable gap instead of silently missing.
 */
class CustomsFxPdfEffect
{
    public function __construct(
        private CustomsDeclarationGenerator $generator,
        private AuditService $auditService,
        private SemanticResolver $resolver,
        private FxOfficialIssuerResolver $officialIssuerResolver,
    ) {}

    public function __invoke(EngineRequest $request, WorkflowTransition $transition, User $actor): void
    {
        if (CustomsDeclaration::query()->where('engine_request_id', $request->id)->exists()) {
            return;
        }

        $request->loadMissing('bank', 'workflowVersion');
        $snapshot = $this->snapshot($request);

        DB::afterCommit(function () use ($request, $snapshot, $actor): void {
            try {
                $this->render($request, $snapshot, $actor);
            } catch (\Throwable $e) {
                OperationalAlertLogger::failure('fx_confirmation_pdf_render', $e, [
                    'engine_request_id' => $request->id,
                ]);
            }
        });
    }

    private function render(EngineRequest $request, array $snapshot, User $actor): void
    {
        $artifacts = $this->generator->generate($snapshot, $actor, $request->id);

        $officialIssuer = $this->officialIssuerResolver->resolve();

        $declaration = CustomsDeclaration::create([
            'engine_request_id' => $request->id,
            'declaration_number' => $artifacts['declaration_number'],
            'generated_by' => $actor->id,
            'issued_by' => $officialIssuer?->id,
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

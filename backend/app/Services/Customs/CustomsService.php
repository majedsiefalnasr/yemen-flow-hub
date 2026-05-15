<?php

namespace App\Services\Customs;

use App\Enums\AuditAction;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Exceptions\CustomsException;
use App\Models\CustomsDeclaration;
use App\Models\ImportRequest;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Workflow\WorkflowService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomsService
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly AuditService $auditService
    ) {
    }

    public function generate(ImportRequest $request, User $issuer): CustomsDeclaration
    {
        if ($request->status !== RequestStatus::EXECUTIVE_APPROVED) {
            throw new CustomsException('Customs declaration can only be generated for EXECUTIVE_APPROVED requests.');
        }

        if (!$issuer->hasRole(UserRole::COMMITTEE_DIRECTOR)) {
            throw new CustomsException('Only committee director can generate customs declarations.');
        }

        if ($request->customsDeclaration()->exists()) {
            throw new CustomsException('Customs declaration already exists for this request.');
        }

        $declarationNumber = $this->nextDeclarationNumber();
        $snapshot = $this->snapshot($request->fresh(['bank']));

        $pdf = Pdf::loadView('pdf.customs-declaration', [
            'requestModel' => $request->fresh(['bank']),
            'declarationNumber' => $declarationNumber,
            'issuedAt' => now(),
            'snapshot' => $snapshot,
        ]);

        $relativePath = "customs/{$request->id}/{$declarationNumber}.pdf";
        Storage::disk('local')->put('private/'.$relativePath, $pdf->output());

        $declaration = CustomsDeclaration::query()->create([
            'request_id' => $request->id,
            'declaration_number' => $declarationNumber,
            'issued_by' => $issuer->id,
            'issued_at' => now(),
            'pdf_path' => $relativePath,
            'metadata' => $snapshot,
        ]);

        $this->workflowService->transition($request->fresh(), 'issue_customs', $issuer);
        $this->workflowService->transition($request->fresh(), 'complete', $issuer);

        $this->auditService->log(
            AuditAction::CUSTOMS_ISSUED,
            $issuer,
            $declaration,
            ['request_id' => $request->id, 'declaration_number' => $declarationNumber]
        );

        return $declaration->refresh();
    }

    public function getPdfStream(CustomsDeclaration $declaration, User $user): StreamedResponse
    {
        Gate::forUser($user)->authorize('view', $declaration->request);

        $fullPath = 'private/'.$declaration->pdf_path;
        if (!Storage::disk('local')->exists($fullPath)) {
            throw new CustomsException('Customs PDF file not found.');
        }

        $stream = Storage::disk('local')->readStream($fullPath);
        if ($stream === false) {
            throw new CustomsException('Unable to stream customs PDF.');
        }

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, $declaration->declaration_number.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    private function nextDeclarationNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "CD-{$year}-";
        $latest = CustomsDeclaration::query()
            ->where('declaration_number', 'like', $prefix.'%')
            ->latest('id')
            ->value('declaration_number');

        $next = 1;
        if ($latest) {
            $parts = explode('-', $latest);
            $next = ((int) ($parts[2] ?? 0)) + 1;
        }

        return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function snapshot(ImportRequest $request): array
    {
        return [
            'reference_number' => $request->reference_number,
            'bank' => [
                'id' => $request->bank?->id,
                'name' => $request->bank?->name,
                'code' => $request->bank?->code,
            ],
            'supplier_name' => $request->supplier_name,
            'amount' => (float) $request->amount,
            'currency' => $request->currency,
            'goods_description' => $request->goods_description,
            'port_of_entry' => $request->port_of_entry,
            'bank_approved_at' => $request->bank_approved_at?->toISOString(),
            'support_approved_at' => $request->support_approved_at?->toISOString(),
            'executive_decided_at' => $request->executive_decided_at?->toISOString(),
        ];
    }
}

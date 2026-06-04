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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
        if (!$issuer->hasRole(UserRole::COMMITTEE_DIRECTOR)) {
            throw new CustomsException('Only committee director can generate customs declarations.');
        }

        $storedPath = null;

        try {
            return DB::transaction(function () use ($request, $issuer, &$storedPath): CustomsDeclaration {
                $lockedRequest = ImportRequest::query()
                    ->with('bank')
                    ->whereKey($request->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Declaration lifecycle has two phases, distinguished by pdf_path:
                //   1. uploadSignedFxDoc() creates a *placeholder* row (pdf_path === '',
                //      signed_fx_doc_path set) and moves the request to FX_CONFIRMATION_PENDING.
                //   2. generate() (here) fills that same placeholder with the real PDF and
                //      issues it. customs_declaration_id on the request is only set at the end
                //      of this method, so an *issued* declaration always has both pdf_path and
                //      the FK populated.
                // The three guards below therefore cover three distinct states, not one:
                if (!in_array($lockedRequest->status, [RequestStatus::EXECUTIVE_APPROVED, RequestStatus::FX_CONFIRMATION_PENDING], true)) {
                    throw new CustomsException('Customs declaration can only be generated for EXECUTIVE_APPROVED or FX_CONFIRMATION_PENDING requests.');
                }

                // Already fully issued (FK set in a prior, committed generate()).
                if ($lockedRequest->customs_declaration_id !== null) {
                    throw new CustomsException('Customs declaration already exists for this request.');
                }

                // No placeholder yet, or placeholder without the signed FX doc → upload must come first.
                $declaration = $lockedRequest->customsDeclaration()->first();
                if ($declaration === null || $declaration->signed_fx_doc_path === null) {
                    throw new CustomsException('Signed FX confirmation document must be uploaded before issuing.');
                }

                // Placeholder already carries a generated PDF → issued (defends against a re-issue
                // race where the FK write above hadn't landed yet).
                if ($declaration->pdf_path !== '') {
                    throw new CustomsException('Customs declaration already exists for this request.');
                }

                $issuedAt = now();
                $declarationNumber = $this->nextDeclarationNumber();
                $snapshot = $this->snapshot($lockedRequest);

                $pdf = Pdf::loadView('pdf.customs-declaration', [
                    'requestModel' => $lockedRequest,
                    'declarationNumber' => $declarationNumber,
                    'issuedAt' => $issuedAt,
                    'snapshot' => $snapshot,
                    'issuer' => $issuer,
                ])->setPaper('a4');

                $relativePath = "customs/{$lockedRequest->id}/{$declarationNumber}.pdf";
                $storedPath = 'private/'.$relativePath;
                Storage::disk('local')->put($storedPath, $pdf->output());

                $declaration->update([
                    'declaration_number' => $declarationNumber,
                    'issued_by' => $issuer->id,
                    'issued_at' => $issuedAt,
                    'pdf_path' => $relativePath,
                    'metadata' => $snapshot,
                ]);

                $lockedRequest->forceFill(['customs_declaration_id' => $declaration->id])->saveQuietly();

                $afterIssue = $this->workflowService->transition($lockedRequest->fresh(), 'issue_customs', $issuer);
                $this->workflowService->transition($afterIssue->fresh(), 'complete', $issuer);

                $this->auditService->log(
                    AuditAction::FX_CONFIRMATION_ISSUED,
                    $issuer,
                    $lockedRequest,
                    ['declaration_id' => $declaration->id, 'declaration_number' => $declarationNumber]
                );

                return $declaration->load(['issuer', 'request.bank'])->refresh();
            });
        } catch (\Throwable $exception) {
            if ($storedPath !== null) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }
    }

    public function uploadSignedFxDoc(ImportRequest $request, User $uploader, UploadedFile $file): CustomsDeclaration
    {
        if (!$uploader->hasRole(UserRole::COMMITTEE_DIRECTOR)) {
            throw new CustomsException('Only committee director can upload signed FX confirmation documents.');
        }

        if ($request->status !== RequestStatus::EXECUTIVE_APPROVED) {
            throw new CustomsException('Signed FX confirmation can only be uploaded for EXECUTIVE_APPROVED requests.');
        }

        $relativePath = null;

        try {
            return DB::transaction(function () use ($request, $uploader, $file, &$relativePath): CustomsDeclaration {
                $lockedRequest = ImportRequest::query()
                    ->whereKey($request->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedRequest->status !== RequestStatus::EXECUTIVE_APPROVED) {
                    throw new CustomsException('Signed FX confirmation can only be uploaded for EXECUTIVE_APPROVED requests.');
                }

                $declaration = CustomsDeclaration::query()->firstOrCreate(
                    ['request_id' => $lockedRequest->id],
                    [
                        'declaration_number' => "PENDING-FX-{$lockedRequest->id}",
                        'issued_by' => $uploader->id,
                        'issued_at' => now(),
                        'pdf_path' => '',
                        'metadata' => [],
                    ]
                );

                if ($declaration->pdf_path !== '') {
                    throw new CustomsException('Issued FX confirmation documents are immutable.');
                }

                if ($declaration->signed_fx_doc_path !== null) {
                    Storage::disk('local')->delete('private/'.$declaration->signed_fx_doc_path);
                }

                $extension = $file->getClientOriginalExtension() ?: 'pdf';
                $relativePath = "fx-confirmation/{$lockedRequest->id}/".uniqid('signed_', true).".{$extension}";
                Storage::disk('local')->put('private/'.$relativePath, file_get_contents($file->getRealPath()));

                $declaration->update([
                    'signed_fx_doc_path' => $relativePath,
                    'signed_fx_doc_uploaded_at' => now(),
                    'signed_fx_doc_uploaded_by' => $uploader->id,
                ]);

                $this->workflowService->transition($lockedRequest->fresh(), 'upload_fx_confirmation', $uploader);

                $this->auditService->log(
                    AuditAction::FX_CONFIRMATION_UPLOADED,
                    $uploader,
                    $lockedRequest,
                    ['declaration_id' => $declaration->id]
                );

                return $declaration->refresh();
            });
        } catch (\Throwable $exception) {
            if ($relativePath !== null) {
                Storage::disk('local')->delete('private/'.$relativePath);
            }

            throw $exception;
        }
    }

    public function getPdfStream(CustomsDeclaration $declaration, User $user): StreamedResponse
    {
        $declaration->loadMissing('request');
        Gate::forUser($user)->authorize('download', $declaration);

        $fullPath = 'private/'.$declaration->pdf_path;
        if (!Storage::disk('local')->exists($fullPath)) {
            throw new CustomsException('Customs PDF file not found.');
        }

        $stream = Storage::disk('local')->readStream($fullPath);
        if ($stream === false) {
            throw new CustomsException('Unable to stream customs PDF.');
        }

        $this->auditService->log(AuditAction::DOCUMENT_DOWNLOADED, $user, $declaration, [
            'document_id' => $declaration->id,
            'document_type' => 'CUSTOMS',
            'request_id' => $declaration->request_id,
        ]);

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
        $isMySQL = DB::connection()->getDriverName() === 'mysql';

        if ($isMySQL) {
            DB::statement("SELECT GET_LOCK('customs_declaration_number', 10)");
        }

        try {
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
        } finally {
            if ($isMySQL) {
                DB::statement("SELECT RELEASE_LOCK('customs_declaration_number')");
            }
        }
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

<?php

namespace App\Services\Customs;

use App\Enums\AuditAction;
use App\Models\CustomsDeclaration;
use App\Models\EngineRequest;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EngineCustomsService
{
    public function __construct(private readonly AuditService $auditService) {}

    /**
     * Attach the bank-countersigned FX confirmation PDF to an already-issued
     * engine CustomsDeclaration. Post-issuance operation — no workflow transition.
     *
     * On first upload emits FX_CONFIRMATION_UPLOADED.
     * On replacement emits FX_SIGNED_DOC_REPLACED with prior-path + reason,
     * and appends to metadata.replacement_history[].
     */
    public function uploadSignedFxDoc(
        EngineRequest $request,
        User $uploader,
        UploadedFile $file,
        ?string $reason = null,
    ): CustomsDeclaration {
        $relativePath = null;

        try {
            return DB::transaction(function () use ($request, $uploader, $file, $reason, &$relativePath): CustomsDeclaration {
                $declaration = CustomsDeclaration::query()
                    ->where('engine_request_id', $request->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $isReplacement = $declaration->signed_fx_doc_path !== null;
                $priorPath = $declaration->signed_fx_doc_path;
                $priorUploadedAt = $declaration->signed_fx_doc_uploaded_at?->toISOString();
                $priorUploadedBy = $declaration->signed_fx_doc_uploaded_by;

                // Remove any previous signed doc from storage.
                if ($priorPath !== null) {
                    Storage::disk('local')->delete('private/'.$priorPath);
                }

                $extension = $file->getClientOriginalExtension() ?: 'pdf';
                $relativePath = "fx-confirmation/engine/{$request->id}/".uniqid('signed_', true).".{$extension}";
                Storage::disk('local')->put('private/'.$relativePath, file_get_contents($file->getRealPath()));

                $metadata = $declaration->metadata ?? [];

                // Append prior upload to replacement history when replacing.
                if ($isReplacement) {
                    $metadata['replacement_history'][] = array_filter([
                        'at' => $priorUploadedAt,
                        'by' => $priorUploadedBy,
                        'prior_path' => $priorPath,
                        'reason' => $reason,
                    ], fn ($v) => $v !== null);
                }

                // Use model update — the whitelist guard in CustomsDeclaration::booted()
                // allows signed-doc columns and metadata only.
                $declaration->update([
                    'signed_fx_doc_path' => $relativePath,
                    'signed_fx_doc_uploaded_at' => now(),
                    'signed_fx_doc_uploaded_by' => $uploader->id,
                    'signed_uploaded_by' => $uploader->id,
                    'metadata' => $metadata,
                ]);

                if ($isReplacement) {
                    $this->auditService->log(
                        AuditAction::FX_SIGNED_DOC_REPLACED,
                        $uploader,
                        $request,
                        [
                            'declaration_id' => $declaration->id,
                            'prior_path' => $priorPath,
                            'new_path' => $relativePath,
                            'reason' => $reason,
                        ],
                    );
                } else {
                    $this->auditService->log(
                        AuditAction::FX_CONFIRMATION_UPLOADED,
                        $uploader,
                        $request,
                        ['declaration_id' => $declaration->id],
                    );
                }

                return $declaration->fresh();
            });
        } catch (\Throwable $e) {
            if ($relativePath !== null) {
                Storage::disk('local')->delete('private/'.$relativePath);
            }
            throw $e;
        }
    }
}

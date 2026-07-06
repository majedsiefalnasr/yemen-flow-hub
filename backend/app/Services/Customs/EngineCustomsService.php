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
     */
    public function uploadSignedFxDoc(EngineRequest $request, User $uploader, UploadedFile $file): CustomsDeclaration
    {
        $relativePath = null;

        try {
            return DB::transaction(function () use ($request, $uploader, $file, &$relativePath): CustomsDeclaration {
                $declaration = CustomsDeclaration::query()
                    ->where('engine_request_id', $request->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Replace any previous signed doc.
                if ($declaration->signed_fx_doc_path !== null) {
                    Storage::disk('local')->delete('private/'.$declaration->signed_fx_doc_path);
                }

                $extension = $file->getClientOriginalExtension() ?: 'pdf';
                $relativePath = "fx-confirmation/engine/{$request->id}/".uniqid('signed_', true).".{$extension}";
                Storage::disk('local')->put('private/'.$relativePath, file_get_contents($file->getRealPath()));

                // Use DB::table to bypass the Eloquent booted() immutability guard
                // (which protects declaration_number/pdf_path but incorrectly fires on
                // all updates including signed_fx_doc_* columns).
                DB::table('customs_declarations')->where('id', $declaration->id)->update([
                    'signed_fx_doc_path' => $relativePath,
                    'signed_fx_doc_uploaded_at' => now(),
                    'signed_fx_doc_uploaded_by' => $uploader->id,
                    'updated_at' => now(),
                ]);

                $this->auditService->log(
                    AuditAction::FX_CONFIRMATION_UPLOADED,
                    $uploader,
                    $request,
                    ['declaration_id' => $declaration->id]
                );

                return $declaration->refresh();
            });
        } catch (\Throwable $e) {
            if ($relativePath !== null) {
                Storage::disk('local')->delete('private/'.$relativePath);
            }
            throw $e;
        }
    }
}

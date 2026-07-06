<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\FxConfirmationUploadRequest;
use App\Http\Resources\CustomsDeclarationResource;
use App\Models\CustomsDeclaration;
use App\Models\EngineRequest;
use App\Services\Customs\EngineCustomsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EngineFxConfirmationController extends Controller
{
    public function uploadSignedFx(FxConfirmationUploadRequest $request, EngineRequest $engineRequest): JsonResponse
    {
        $this->authorize('uploadSignedFx', $engineRequest);

        $declaration = app(EngineCustomsService::class)->uploadSignedFxDoc(
            $engineRequest,
            $request->user(),
            $request->file('signed_document'),
            $request->input('reason'),
        );

        return ApiResponse::success(
            new CustomsDeclarationResource($declaration->load(['issuer', 'generatedBy', 'signedUploadedBy', 'engineRequest.bank'])),
            'تم رفع وثيقة المصارفة الموقعة بنجاح.'
        );
    }

    public function downloadCustomsDeclaration(Request $request, EngineRequest $engineRequest): StreamedResponse
    {
        $declaration = CustomsDeclaration::query()
            ->where('engine_request_id', $engineRequest->id)
            ->firstOrFail();

        $this->authorize('download', $declaration);

        abort_unless(
            $declaration->pdf_path !== null && Storage::disk('local')->exists('private/'.$declaration->pdf_path),
            404,
            'Customs declaration PDF not found.'
        );

        $filename = 'customs-declaration-'.$engineRequest->reference_number.'.pdf';

        return Storage::disk('local')->download('private/'.$declaration->pdf_path, $filename);
    }

    public function downloadSignedFxDoc(Request $request, EngineRequest $engineRequest): StreamedResponse
    {
        $declaration = CustomsDeclaration::query()
            ->where('engine_request_id', $engineRequest->id)
            ->firstOrFail();

        $this->authorize('downloadSignedFx', $declaration);

        abort_unless(
            $declaration->signed_fx_doc_path !== null && Storage::disk('local')->exists('private/'.$declaration->signed_fx_doc_path),
            404,
            'Signed FX confirmation document not found.'
        );

        $filename = 'signed-fx-confirmation-'.$engineRequest->reference_number.'.pdf';

        return Storage::disk('local')->download('private/'.$declaration->signed_fx_doc_path, $filename);
    }
}

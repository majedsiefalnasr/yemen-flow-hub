<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentType;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\ImportRequest;
use App\Services\Documents\PdfGeneratorService;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentTemplateController extends Controller
{
    public function __construct(private readonly PdfGeneratorService $pdf) {}

    /** Shared data builder for Document 1 (confirmation-request) — used by both download and preview. */
    private function buildConfirmationRequestData(ImportRequest $importRequest): array
    {
        $importRequest->load(['merchant', 'bank', 'documents']);

        $requestDocs = $importRequest->documents->where('type', DocumentType::REQUEST_DOC->value);
        $hasAnyRequestDoc = $requestDocs->isNotEmpty();

        // The attachment checklist mirrors the wizard's document slots. Each flag prefers the
        // query param (set from the wizard's local selection before files are uploaded) and
        // falls back to whether any request document exists once they are persisted.
        $flag = static function (string $param) use ($hasAnyRequestDoc): bool {
            return request()->has($param)
                ? request()->boolean($param)
                : $hasAnyRequestDoc;
        };

        // Each entry: label + whether it is attached. Built dynamically so the PDF only
        // shows documents that are actually part of this request's wizard flow.
        $attachedDocs = [
            ['label' => 'الفاتورة الأولية (Proforma Invoice)', 'attached' => $flag('has_proforma_invoice')],
            ['label' => 'السجل التجاري', 'attached' => $flag('has_commercial_register')],
            ['label' => 'البطاقة الضريبية', 'attached' => $flag('has_tax_card')],
            ['label' => 'مستندات إضافية', 'attached' => $flag('has_extra_docs')],
        ];

        $invoiceDate = $importRequest->invoice_date
            ? Carbon::parse($importRequest->invoice_date)->format('d/m/Y')
            : '—';

        $dueDate = $importRequest->due_date
            ? Carbon::parse($importRequest->due_date)->format('d/m/Y')
            : '—';

        return [
            'date' => now()->format('d/m/Y'),
            'documentNumber' => $importRequest->reference_number ?? '—',
            'bankName' => $importRequest->bank?->name ?? '—',
            'merchantName' => $importRequest->merchant?->name ?? '—',
            'commercialRegNo' => $importRequest->merchant?->commercial_register ?? '—',
            'committeeApprovalNo' => null,
            'supplierName' => $importRequest->supplier_name ?? '—',
            'originCountry' => $importRequest->origin_country ?? '—',
            'invoiceNumber' => $importRequest->invoice_number ?? '—',
            'invoiceDate' => $invoiceDate,
            'amount' => $importRequest->amount ?? 0,
            'currency' => $importRequest->currency?->value ?? $importRequest->currency ?? 'USD',
            'goodsType' => $importRequest->goods_type ?? '—',
            'paymentTerms' => $importRequest->payment_terms ?? '—',
            'dueDate' => $dueDate,
            'goodsDescription' => $importRequest->goods_description ?? '—',
            'arrivalPort' => $importRequest->arrival_port ?? '—',
            'shippingPort' => $importRequest->shipping_port ?? '—',
            'customsOffice' => $importRequest->customs_office ?? '—',
            'blNumber' => $importRequest->bl_number ?? '—',
            'attachedDocs' => $attachedDocs,
        ];
    }

    /**
     * GET /api/requests/{importRequest}/confirmation-request-template
     *
     * Pre-filled Document 1 PDF for DATA_ENTRY/BANK_ADMIN to download, stamp, and re-upload.
     */
    public function confirmationRequest(ImportRequest $importRequest): StreamedResponse
    {
        $user = request()->user();

        $allowed = match ($user->role) {
            UserRole::DATA_ENTRY,
            UserRole::BANK_ADMIN => $user->bank_id !== null && $user->bank_id === $importRequest->bank_id,
            default => false,
        };

        if (! $allowed) {
            throw new AuthorizationException;
        }

        if (! $importRequest->status?->isEditable()) {
            abort(response()->json(ApiResponse::error(
                'Confirmation request template is only available while the request is editable.',
                [],
                422,
                'TEMPLATE_INVALID_STATUS'
            ), 422));
        }

        $data = $this->buildConfirmationRequestData($importRequest);
        $filename = 'confirmation-request-'.($importRequest->reference_number ?? $importRequest->id).'.pdf';

        return $this->pdf->download($filename, 'pdf.confirmation-request', $data);
    }

    /**
     * GET /api/requests/{importRequest}/confirmation-request-preview
     *
     * Watermarked display-only PDF for BANK_REVIEWER, COMMITTEE_DIRECTOR, CBY_ADMIN.
     * Streams inline so the browser renders it in the PDF viewer.
     */
    public function confirmationRequestPreview(ImportRequest $importRequest): StreamedResponse
    {
        $user = request()->user();

        $allowed = match ($user->role) {
            UserRole::BANK_REVIEWER => $user->bank_id !== null && $user->bank_id === $importRequest->bank_id,
            UserRole::COMMITTEE_DIRECTOR,
            UserRole::CBY_ADMIN => true,
            default => false,
        };

        if (! $allowed) {
            throw new AuthorizationException;
        }

        $data = $this->buildConfirmationRequestData($importRequest);
        $filename = 'confirmation-request-preview-'.($importRequest->reference_number ?? $importRequest->id).'.pdf';
        $watermark = "نسخة إلكترونية للعرض\nFOR DISPLAY ONLY\nYEMEN FLOW HUB";

        return $this->pdf->inline($filename, 'pdf.confirmation-request-preview', $data, $watermark);
    }

    /**
     * GET /api/requests/{importRequest}/fx-confirmation-template
     *
     * Pre-filled Document 2 PDF for COMMITTEE_DIRECTOR to download, stamp, sign, and re-upload.
     */
    public function fxConfirmation(ImportRequest $importRequest): StreamedResponse
    {
        $user = request()->user();

        if ($user->role !== UserRole::COMMITTEE_DIRECTOR) {
            throw new AuthorizationException;
        }

        if ($importRequest->status !== RequestStatus::EXECUTIVE_APPROVED) {
            abort(response()->json(ApiResponse::error(
                'FX confirmation template is only available for EXECUTIVE_APPROVED requests.',
                [],
                422,
                'TEMPLATE_INVALID_STATUS'
            ), 422));
        }

        $importRequest->load(['merchant', 'bank']);

        $data = [
            'date' => now()->format('d/m/Y'),
            'documentNumber' => '',
            'merchantName' => $importRequest->merchant?->name ?? '—',
            'taxNumber' => $importRequest->merchant?->tax_number ?? null,
            'bankName' => $importRequest->bank?->name ?? '—',
            'referenceNumber' => $importRequest->reference_number ?? '—',
            'goodsType' => $importRequest->goods_type ?? '—',
            'currency' => $importRequest->currency?->value ?? $importRequest->currency ?? 'USD',
            'amount' => $importRequest->amount ?? 0,
            'yerEquivalent' => $importRequest->yer_equivalent,
            'arrivalPort' => $importRequest->arrival_port ?? '—',
            'quantity' => $importRequest->quantity,
        ];

        $filename = 'fx-confirmation-template-'.($importRequest->reference_number ?? $importRequest->id).'.pdf';

        return $this->pdf->download($filename, 'pdf.fx-confirmation', $data);
    }
}

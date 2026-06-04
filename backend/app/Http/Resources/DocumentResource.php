<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    /** Human-readable Arabic titles per wizard sub-type / document type. */
    private const SUB_TYPE_TITLES = [
        'proforma_invoice' => 'الفاتورة الأولية (Proforma Invoice)',
        'commercial_register' => 'السجل التجاري',
        'tax_card' => 'البطاقة الضريبية',
        'confirmation_request' => 'طلب وثيقة التأكيد',
        'extra_docs' => 'مستندات إضافية',
    ];

    private const TYPE_TITLES = [
        'SWIFT' => 'مستند SWIFT',
        'FX_REQUEST' => 'مستند طلب المصارفة الخارجية',
        'CONFIRMATION_REQUEST' => 'طلب وثيقة التأكيد',
        'CUSTOMS' => 'بيان جمركي',
        'REQUEST_DOC' => 'مستند الطلب',
    ];

    private function documentTitle(): string
    {
        if ($this->document_sub_type && isset(self::SUB_TYPE_TITLES[$this->document_sub_type])) {
            return self::SUB_TYPE_TITLES[$this->document_sub_type];
        }

        return self::TYPE_TITLES[$this->type] ?? 'مستند الطلب';
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'document_sub_type' => $this->document_sub_type,
            'title' => $this->documentTitle(),
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'checksum' => $this->checksum,
            'uploaded_by' => $this->uploaded_by,
            'uploaded_by_name' => $this->whenLoaded('uploader', fn () => $this->uploader?->name),
            'uploaded_at' => $this->created_at?->toISOString(),
            'download_url' => url("/api/documents/{$this->id}/download"),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomsDeclarationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_id' => $this->request_id,
            'declaration_number' => $this->declaration_number,
            'issued_by' => $this->issued_by,
            'issued_at' => $this->issued_at?->toISOString(),
            'pdf_path' => $this->pdf_path,
            'metadata' => $this->metadata,
            'download_url' => url("/api/customs/{$this->id}/download"),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

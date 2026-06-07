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
            'issuer' => $this->issuer ? [
                'id' => $this->issuer->id,
                'name' => $this->issuer->name,
                'email' => $this->issuer->email,
                'role' => $this->issuer->role?->value ?? $this->issuer->role,
            ] : null,
            'issued_at' => $this->issued_at?->toISOString(),
            'signed_fx_doc_path' => $this->signed_fx_doc_path,
            'signed_fx_doc_uploaded_at' => $this->signed_fx_doc_uploaded_at?->toISOString(),
            'signed_fx_doc_uploaded_by' => $this->signed_fx_doc_uploaded_by,
            'has_signed_fx_doc' => $this->signed_fx_doc_path !== null,
            'request' => $this->request ? [
                'id' => $this->request->id,
                'reference_number' => $this->request->reference_number,
                'bank_name' => $this->request->bank?->name,
            ] : null,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

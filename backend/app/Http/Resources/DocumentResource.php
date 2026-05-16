<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'type'              => $this->type,
            'original_filename' => $this->original_filename,
            'mime_type'         => $this->mime_type,
            'size_bytes'        => $this->size_bytes,
            'checksum'          => $this->checksum,
            'uploaded_by'       => $this->uploaded_by,
            'uploaded_by_name'  => $this->whenLoaded('uploader', fn () => $this->uploader?->name),
            'uploaded_at'       => $this->created_at?->toISOString(),
            'download_url'      => url("/api/documents/{$this->id}/download"),
        ];
    }
}

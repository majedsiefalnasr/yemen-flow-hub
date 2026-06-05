<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_ar' => $this->name,
            'name_en' => $this->name,
            'code' => $this->code,
            'is_active' => (bool) $this->is_active,
            'admin' => $this->whenLoaded(
                'bankAdmin',
                fn () => $this->bankAdmin ? new UserResource($this->bankAdmin) : null,
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

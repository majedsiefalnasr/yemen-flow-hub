<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_id' => $this->request_id,
            'user_id' => $this->user_id,
            'vote' => $this->vote?->value,
            'justification' => $this->justification,
            'is_director_override' => (bool) $this->is_director_override,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

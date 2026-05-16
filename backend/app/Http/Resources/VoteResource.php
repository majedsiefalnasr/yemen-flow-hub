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
            'user_name' => $this->whenLoaded('user', fn () => $this->user->name),
            'vote' => $this->vote?->value,
            'justification' => $this->justification,
            'is_director_override' => (bool) $this->is_director_override,
            'voted_at' => $this->voted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

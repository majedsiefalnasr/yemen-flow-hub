<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role?->value,
            'role_label' => $this->role?->label(),
            'bank_id' => $this->bank_id,
            'bank_name' => $this->bank?->name,
            'bank_name_ar' => $this->bank?->name,
            'bank_name_en' => $this->bank?->name,
            'last_login_at' => $this->last_login_at?->toISOString(),
            'last_seen_at' => $this->last_login_at?->toISOString(),
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

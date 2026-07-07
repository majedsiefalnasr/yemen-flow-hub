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
            'phone' => $this->phone,
            'role' => $this->asUserRole()?->value,
            'role_label' => $this->asUserRole()?->label(),
            'bank_id' => $this->bank_id,
            'bank_name' => $this->bank?->name,
            'bank_name_ar' => $this->bank?->name,
            'bank_name_en' => $this->bank?->name,
            'last_login_at' => $this->last_login_at?->toISOString(),
            'last_seen_at' => $this->last_login_at?->toISOString(),
            'is_active' => (bool) $this->is_active,
            'must_change_password' => (bool) $this->must_change_password,
            'mfa_enabled' => (bool) $this->mfa_enabled,
            'totp_enabled' => (bool) $this->totp_enabled,
            'pin_enabled' => (bool) $this->pin_enabled,
            'avatar_variant' => $this->avatar_variant ?? 'beam',
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DemoUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $team = $this->resource->team();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->asUserRole()?->value,
            'role_label' => $this->asUserRole()?->label(),
            'organization' => $this->organization ? [
                'id' => $this->organization->id,
                'code' => $this->organization->code,
                'name' => $this->organization->name,
            ] : null,
            'team' => $team ? [
                'id' => $team->id,
                'organization_id' => $team->organization_id,
                'code' => $team->code,
                'name' => $team->name,
            ] : null,
            'bank' => $this->bank ? [
                'id' => $this->bank->id,
                'code' => $this->bank->code,
                'name' => $this->bank->name,
            ] : null,
        ];
    }
}

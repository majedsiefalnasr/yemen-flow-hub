<?php

namespace App\Http\Resources;

use App\Services\Authorization\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthMeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $permissions = app(PermissionService::class);
        $team = $this->resource->team();
        $role = $this->resource->role();
        $user = (new UserResource($this->resource))->resolve($request);

        // Nested governance identity (`organization`/`team`/`role`/`bank`) is the
        // authoritative shape and deliberately overrides the scalar keys of
        // the same name spread from UserResource — both the frontend identity
        // hydration and AuthIdentityTest read `data.role.code`, `data.bank`, etc.
        // Compatibility scalars (`role_label`, `bank_id`, `bank_name`) remain spread
        // for older consumers.
        return array_merge($user, [
            'user' => $user,
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
            'role' => $role ? [
                'id' => $role->id,
                'organization_id' => $role->organization_id,
                'code' => $role->code,
                'name' => $role->name,
            ] : null,
            'bank' => $this->bank ? [
                'id' => $this->bank->id,
                'code' => $this->bank->code,
                'name' => $this->bank->name,
            ] : null,
            'screen_permissions' => $permissions->screenPermissionsForUser($this->resource),
            'capabilities' => $permissions->capabilitiesForUser($this->resource),
        ]);
    }
}

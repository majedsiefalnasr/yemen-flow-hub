<?php

namespace App\Http\Requests;

use App\Enums\StageAccessLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateStagePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // The match fields (org/team/role/user) are not required by callers that only
        // change display_label/access_level. Organization is required when submitted;
        // team/role remain optional scoping refinements (see StagePermissionConsistency).
        return [
            'organization_id' => ['sometimes', 'required', 'integer', 'exists:organizations,id'],
            'team_id' => ['sometimes', 'nullable', 'integer', 'exists:teams,id'],
            'role_id' => ['sometimes', 'nullable', 'integer', 'exists:roles,id'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'access_level' => ['sometimes', Rule::enum(StageAccessLevel::class)],
            'display_label' => ['sometimes', 'string', 'max:255'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $permission = $this->route('stagePermission');

                StagePermissionConsistency::check($validator, [
                    'organization_id' => $this->has('organization_id') ? $this->input('organization_id') : $permission?->organization_id,
                    'team_id' => $this->has('team_id') ? $this->input('team_id') : $permission?->team_id,
                    'role_id' => $this->has('role_id') ? $this->input('role_id') : $permission?->role_id,
                    'user_id' => $this->has('user_id') ? $this->input('user_id') : $permission?->user_id,
                ]);
            },
        ];
    }
}

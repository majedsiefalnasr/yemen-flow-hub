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
        // change display_label/access_level, but if any of organization/team/role is
        // submitted, all three must be submitted together (see StagePermissionConsistency).
        return [
            'organization_id' => ['sometimes', 'required', 'integer', 'exists:organizations,id'],
            'team_id' => ['sometimes', 'required', 'integer', 'exists:teams,id'],
            'role_id' => ['sometimes', 'required', 'integer', 'exists:roles,id'],
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
                StagePermissionConsistency::check($validator, $this->all());
            },
        ];
    }
}

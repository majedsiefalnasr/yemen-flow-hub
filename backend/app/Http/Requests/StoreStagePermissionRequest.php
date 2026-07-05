<?php

namespace App\Http\Requests;

use App\Enums\StageAccessLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStagePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'access_level' => ['required', Rule::enum(StageAccessLevel::class)],
            'display_label' => ['required', 'string', 'max:255'],
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

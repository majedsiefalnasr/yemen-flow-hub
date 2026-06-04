<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasRole(UserRole::CBY_ADMIN);
    }

    public function rules(): array
    {
        return [
            'value' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'value.required' => 'The value field is required.',
        ];
    }
}

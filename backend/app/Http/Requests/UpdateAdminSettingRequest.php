<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasRoleCode('system_admin');
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

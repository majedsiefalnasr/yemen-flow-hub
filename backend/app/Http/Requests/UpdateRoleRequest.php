<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'organization_id' => ['sometimes', Rule::prohibitedIf(fn () => $this->integer('organization_id') !== $role->organization_id)],
            'code' => ['sometimes', Rule::prohibitedIf(fn () => $this->input('code') !== $role->code)],
            'name' => ['required', 'string', 'max:255'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}

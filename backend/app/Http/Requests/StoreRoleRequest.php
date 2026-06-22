<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'code' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('roles')->where('organization_id', $this->integer('organization_id'))],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}

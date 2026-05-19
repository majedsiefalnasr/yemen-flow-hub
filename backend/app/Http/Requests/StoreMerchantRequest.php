<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreMerchantRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'name' => ['required', 'string', 'max:255'],
            'commercial_register' => ['nullable', 'string', 'max:255', Rule::unique('merchants', 'commercial_register')],
            'tax_number' => ['nullable', 'string', 'max:255', Rule::unique('merchants', 'tax_number')],
            'national_id' => ['nullable', 'string', 'max:255'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

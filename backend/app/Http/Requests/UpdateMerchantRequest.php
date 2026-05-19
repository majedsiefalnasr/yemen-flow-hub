<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateMerchantRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $merchantId = $this->route('merchant')?->id;

        return [
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'commercial_register' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('merchants', 'commercial_register')->ignore($merchantId)],
            'tax_number' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('merchants', 'tax_number')->ignore($merchantId)],
            'national_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'owner_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string'],
            'business_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

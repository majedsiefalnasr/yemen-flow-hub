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
        return [
            'version' => ['required', 'integer'],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'tax_number' => ['sometimes', 'required', 'string', 'max:255'],
            'tax_card_expiry' => ['sometimes', 'nullable', 'date'],
            'address' => ['sometimes', 'nullable', 'string'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['ACTIVE', 'SUSPENDED'])],
            'owners' => ['sometimes', 'array'],
            'owners.*.name' => ['required', 'string', 'max:255'],
            'owners.*.ownership_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'companies' => ['sometimes', 'array'],
            'companies.*.name' => ['required', 'string', 'max:255'],
            'companies.*.commercial_registration_number' => ['required', 'string', 'max:255'],
            'companies.*.commercial_registration_expiry' => ['nullable', 'date'],
            'companies.*.sector_reference_value_id' => ['nullable', 'integer'],
            'companies.*.is_active' => ['sometimes', 'boolean'],
        ];
    }
}

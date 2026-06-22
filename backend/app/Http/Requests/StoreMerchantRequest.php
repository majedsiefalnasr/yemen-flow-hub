<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMerchantRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $owners = $this->input('owners', []);
            if (! is_array($owners) || $owners === []) {
                return;
            }

            $total = array_sum(array_map(
                static fn ($owner) => (float) ($owner['ownership_percentage'] ?? 0),
                $owners
            ));

            if ($total > 100) {
                $validator->errors()->add('owners', 'Total ownership percentage cannot exceed 100.');
            }
        });
    }

    public function rules(): array
    {
        return [
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'name' => ['required', 'string', 'max:255'],
            'tax_number' => ['required', 'string', 'max:255'],
            'tax_card_expiry' => ['nullable', 'date'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:255'],
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

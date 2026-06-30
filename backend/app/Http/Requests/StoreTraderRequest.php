<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTraderRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tax_number' => ['required', 'string', 'max:255', Rule::unique('traders', 'tax_number')],
            'trader_name' => ['required', 'string', 'max:255'],
            'tax_card_expiry' => ['required', 'date'],
            'commercial_registration_number' => ['required', 'string', 'max:255'],
            'commercial_registration_expiry' => ['required', 'date'],
            'companies' => ['sometimes', 'array', 'max:50'],
            'companies.*.id' => ['prohibited'],
            'companies.*.company_name' => ['required', 'string', 'max:255'],
            'owners' => ['sometimes', 'array', 'max:50'],
            'owners.*.id' => ['prohibited'],
            'owners.*.full_name' => ['required', 'string', 'max:255'],
            'owners.*.ownership_percentage' => ['required', 'numeric', 'gt:0', 'max:100'],
            'owners.*.nationality' => ['nullable', 'string', 'max:255'],
            'owners.*.identification_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after($this->validateMajorOwners(...));
        $validator->after($this->validateOwnershipTotal(...));
    }

    protected function validateMajorOwners(Validator $validator): void
    {
        foreach ($this->input('owners', []) as $index => $owner) {
            if ((float) ($owner['ownership_percentage'] ?? 0) < 25) {
                continue;
            }

            if (blank($owner['nationality'] ?? null)) {
                $validator->errors()->add("owners.{$index}.nationality", 'The nationality field is required for owners with 25% or more ownership.');
            }

            if (blank($owner['identification_number'] ?? null)) {
                $validator->errors()->add("owners.{$index}.identification_number", 'The identification number field is required for owners with 25% or more ownership.');
            }
        }
    }

    /**
     * Authoritative backend enforcement: combined ownership across all owners
     * of a single trader must not exceed 100% (code-review 17-B decision #8).
     */
    protected function validateOwnershipTotal(Validator $validator): void
    {
        $owners = $this->input('owners', []);

        if ($owners === []) {
            return;
        }

        $total = array_sum(array_map(
            static fn ($owner): float => (float) ($owner['ownership_percentage'] ?? 0),
            $owners
        ));

        if (round($total, 2) > 100) {
            $validator->errors()->add('owners', 'Total ownership percentage across all owners must not exceed 100%.');
        }
    }
}

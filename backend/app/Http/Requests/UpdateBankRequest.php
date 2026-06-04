<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Validation\Rule;

class UpdateBankRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('name') && $this->filled('name_ar')) {
            $this->merge(['name' => $this->input('name_ar')]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bankId = $this->route('bank')?->id;

        if ($this->user()?->hasRole(UserRole::BANK_ADMIN)) {
            return [
                'name' => ['required', 'string', 'max:255', Rule::unique('banks', 'name')->ignore($bankId)],
                'code' => ['prohibited'],
                'is_active' => ['prohibited'],
            ];
        }

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('banks', 'name')->ignore($bankId)],
            'code' => ['required', 'string', 'max:20', Rule::unique('banks', 'code')->ignore($bankId)],
            'is_active' => ['required', 'boolean'],
        ];
    }
}

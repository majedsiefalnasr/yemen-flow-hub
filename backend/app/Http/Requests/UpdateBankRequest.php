<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateBankRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('name') && $this->filled('name_ar')) {
            $this->merge(['name' => $this->input('name_ar')]);
        }

        if ($this->has('admin_name') || $this->has('adminName')) {
            $this->merge(['admin_name' => $this->input('admin_name', $this->input('adminName'))]);
        }

        if ($this->has('admin_email') || $this->has('adminEmail')) {
            $this->merge(['admin_email' => $this->input('admin_email', $this->input('adminEmail'))]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bankId = $this->route('bank')?->id;

        if ($this->user()?->hasRoleCode('bank_admin')) {
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
            'admin_name' => ['sometimes', 'required', 'string', 'max:255'],
            'admin_email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('bank')?->bankAdmin?->id),
            ],
        ];
    }
}

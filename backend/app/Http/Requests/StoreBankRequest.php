<?php

namespace App\Http\Requests;

use App\Models\Bank;
use App\Support\PasswordPolicy;
use Illuminate\Validation\Rule;

class StoreBankRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('name') && $this->filled('name_ar')) {
            $this->merge(['name' => $this->input('name_ar')]);
        }

        $this->merge([
            'admin_name' => $this->input('admin_name', $this->input('adminName')),
            'admin_email' => $this->input('admin_email', $this->input('adminEmail')),
            'admin_password' => $this->input('admin_password', $this->input('adminPassword')),
        ]);
    }

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Bank::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:banks,name'],
            'code' => ['required', 'string', 'max:20', 'unique:banks,code'],
            'is_active' => ['sometimes', 'boolean'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'admin_password' => ['required', ...PasswordPolicy::rules()],
        ];
    }

    public function messages(): array
    {
        return [
            ...PasswordPolicy::messages('admin_password'),
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Validation\Rules\Enum;

class StoreUserRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', new Enum(UserRole::class)],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $roleValue = $this->input('role');
            $bankId = $this->input('bank_id');

            if (!$roleValue) {
                return;
            }

            $role = UserRole::from($roleValue);

            if ($role->isBankRole() && empty($bankId)) {
                $validator->errors()->add('bank_id', 'bank_id is required for bank roles.');
            }

            if ($role->isCbyRole() && !is_null($bankId)) {
                $validator->errors()->add('bank_id', 'bank_id must be null for CBY roles.');
            }
        });
    }
}

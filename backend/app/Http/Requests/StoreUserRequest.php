<?php

namespace App\Http\Requests;

use App\Enums\AvatarVariant;
use App\Enums\UserRole;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreUserRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        if (! $actor?->hasRoleCode('bank_admin')) {
            return true;
        }

        $roleValue = $this->input('role');
        if (! $roleValue || ! UserRole::tryFrom($roleValue)) {
            return true;
        }

        return $actor->bank_id !== null
            && UserRole::from($roleValue)->isBankAdminManageable()
            && (int) $this->input('bank_id') === (int) $actor->bank_id;
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
            'avatar_variant' => ['nullable', Rule::in(AvatarVariant::values())],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $roleValue = $this->input('role');
            $bankId = $this->input('bank_id');

            if (! $roleValue) {
                return;
            }

            $role = UserRole::tryFrom($roleValue);
            if (! $role) {
                return;
            }

            if ($role->isBankRole() && empty($bankId)) {
                $validator->errors()->add('bank_id', 'bank_id is required for bank roles.');
            }

            if ($role->isCbyRole() && ! is_null($bankId)) {
                $validator->errors()->add('bank_id', 'bank_id must be null for CBY roles.');
            }

            if ($this->user()?->hasRoleCode('bank_admin')) {
                if (! $role->isBankAdminManageable()) {
                    $validator->errors()->add('role', 'BANK_ADMIN can only manage DATA_ENTRY and BANK_REVIEWER users.');
                }

                if ((int) $bankId !== (int) $this->user()->bank_id) {
                    $validator->errors()->add('bank_id', 'BANK_ADMIN can only manage users in their own bank.');
                }
            }
        });
    }
}

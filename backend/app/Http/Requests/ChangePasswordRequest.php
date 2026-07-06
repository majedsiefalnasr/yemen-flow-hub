<?php

namespace App\Http\Requests;

use App\Enums\AuditAction;
use App\Services\Audit\AuditService;
use App\Support\PasswordPolicy;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->password !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (! Hash::check($value, $this->user()->password)) {
                        $fail('The current password is incorrect.');
                    }
                },
            ],
            'password' => [
                'required',
                ...PasswordPolicy::rules(),
                'confirmed',
                function ($attribute, $value, $fail) {
                    $errors = PasswordPolicy::validate($this->user(), $value, 'password');
                    if ($errors !== []) {
                        $fail($errors['password'] ?? 'The password does not meet policy requirements.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            ...PasswordPolicy::messages(),
            'password.confirmed' => 'The password confirmation does not match.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $user = $this->user();
        if ($user !== null) {
            app(AuditService::class)->log(
                AuditAction::PASSWORD_CHANGE_FAILED,
                $user,
                $user,
                ['reason' => 'validation_failed', 'fields' => array_keys($validator->errors()->toArray())]
            );
        }

        throw new ValidationException($validator);
    }
}

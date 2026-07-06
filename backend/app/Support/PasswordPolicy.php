<?php

namespace App\Support;

class PasswordPolicy
{
    public static function rules(): array
    {
        return ['string', 'min:8', 'regex:/[A-Z]/', 'regex:/[a-z]/', 'regex:/[0-9]/'];
    }

    public static function messages(string $field = 'password'): array
    {
        return [
            "{$field}.min" => 'Password must be at least 8 characters long.',
            "{$field}.regex" => 'Password must contain uppercase letters, lowercase letters, and numbers.',
        ];
    }
}

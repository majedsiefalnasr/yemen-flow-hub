<?php

namespace App\Support;

use App\Models\PasswordHistory;
use App\Models\User;
use App\Services\Auth\AuthSecuritySettings;
use Illuminate\Support\Facades\Hash;

class PasswordPolicy
{
    public static function rules(): array
    {
        $min = app(AuthSecuritySettings::class)->passwordMinLength();

        return [
            'string',
            "min:{$min}",
            'regex:/[A-Z]/',
            'regex:/[a-z]/',
            'regex:/[0-9]/',
        ];
    }

    public static function messages(string $field = 'password'): array
    {
        $min = app(AuthSecuritySettings::class)->passwordMinLength();

        return [
            "{$field}.min" => "Password must be at least {$min} characters long.",
            "{$field}.regex" => 'Password must contain uppercase letters, lowercase letters, and numbers.',
        ];
    }

    /**
     * Validate a password against centralized policy (history, blacklist, length).
     * Stable public API for admin reset and all password set paths.
     *
     * @return array<string, string> field => message
     */
    public static function validate(?User $user, string $password, string $field = 'password'): array
    {
        $settings = app(AuthSecuritySettings::class);
        $errors = [];
        $min = $settings->passwordMinLength();

        if (strlen($password) < $min) {
            $errors[$field] = "Password must be at least {$min} characters long.";
        } elseif (! preg_match('/[A-Z]/', $password)
            || ! preg_match('/[a-z]/', $password)
            || ! preg_match('/[0-9]/', $password)) {
            $errors[$field] = 'Password must contain uppercase letters, lowercase letters, and numbers.';
        }

        if (self::isBlacklisted($password)) {
            $errors[$field] = 'This password is too common and cannot be used.';
        }

        if ($user !== null && self::isReused($user, $password)) {
            $errors[$field] = 'You cannot reuse a recent password.';
        }

        return $errors;
    }

    public static function isBlacklisted(string $password): bool
    {
        $normalized = strtolower(trim($password));
        $list = config('auth_security.blacklisted_passwords', []);

        return in_array($normalized, array_map('strtolower', $list), true);
    }

    public static function isReused(User $user, string $password): bool
    {
        if ($user->password !== null && Hash::check($password, $user->password)) {
            return true;
        }

        $limit = app(AuthSecuritySettings::class)->passwordHistoryCount();
        if ($limit <= 0) {
            return false;
        }

        $histories = PasswordHistory::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        foreach ($histories as $history) {
            if (Hash::check($password, $history->password_hash)) {
                return true;
            }
        }

        return false;
    }

    public static function recordHistory(User $user): void
    {
        $limit = app(AuthSecuritySettings::class)->passwordHistoryCount();
        if ($limit <= 0 || $user->password === null) {
            return;
        }

        PasswordHistory::query()->create([
            'user_id' => $user->id,
            'password_hash' => $user->password,
            'created_at' => now(),
        ]);

        $keepIds = PasswordHistory::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->pluck('id');

        PasswordHistory::query()
            ->where('user_id', $user->id)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }
}

<?php

namespace App\Support;

use App\Enums\FieldType;

/**
 * Shared typed-value checks for runtime field data and authoring-time defaults.
 */
final class TypedFieldValueValidator
{
    public static function validateRuntimeValue(FieldType $type, mixed $value): ?string
    {
        return match ($type) {
            FieldType::DATE => self::validateDateValue($value, 'The date must be a valid ISO-8601 date (YYYY-MM-DD).'),
            FieldType::CHECKBOX => self::validateCheckboxValue($value),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function validateDefaultValue(FieldType $type, mixed $defaultValue, array $attributes = []): ?string
    {
        if ($defaultValue === null || trim((string) $defaultValue) === '') {
            return null;
        }

        $value = (string) $defaultValue;

        return match ($type) {
            FieldType::DATE => self::validateDateString($value, 'default_value must be a valid ISO-8601 date (YYYY-MM-DD).'),
            FieldType::CHECKBOX => self::validateCheckboxDefaultString($value),
            FieldType::NUMBER, FieldType::CURRENCY => is_numeric($value)
                ? null
                : 'default_value must be a valid number.',
            FieldType::SELECT => self::validateSelectDefault($value, $attributes['options'] ?? null),
            FieldType::TEXT, FieldType::TEXTAREA => self::validateTextDefault($value, $attributes),
            FieldType::FILE => 'FILE fields cannot have a default_value.',
            FieldType::DYNAMIC_SELECT => null,
        };
    }

    private static function validateDateValue(mixed $value, string $message): ?string
    {
        if (! is_string($value)) {
            return $message;
        }

        return self::validateDateString($value, $message);
    }

    private static function validateDateString(string $value, string $message): ?string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $message;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));
        if (! checkdate($month, $day, $year)) {
            return $message;
        }

        return null;
    }

    private static function validateCheckboxValue(mixed $value): ?string
    {
        if (! is_bool($value)) {
            return 'The checkbox value must be true or false.';
        }

        return null;
    }

    private static function validateCheckboxDefaultString(string $value): ?string
    {
        if (! in_array(strtolower($value), ['true', 'false'], true)) {
            return 'default_value must be true or false.';
        }

        return null;
    }

    private static function validateSelectDefault(string $value, mixed $options): ?string
    {
        if (! is_array($options)) {
            return 'default_value cannot be set without configured options.';
        }

        $allowed = collect($options)->pluck('value')->all();
        foreach ($allowed as $allowedValue) {
            if ($value === (string) $allowedValue) {
                return null;
            }
        }

        return 'default_value must be one of the configured options.';
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function validateTextDefault(string $value, array $attributes): ?string
    {
        $minLength = $attributes['min_length'] ?? null;
        $maxLength = $attributes['max_length'] ?? null;
        $regex = $attributes['regex_pattern'] ?? null;

        $len = mb_strlen($value);
        if ($minLength !== null && $len < (int) $minLength) {
            return "default_value must be at least {$minLength} characters.";
        }
        if ($maxLength !== null && $len > (int) $maxLength) {
            return "default_value must not exceed {$maxLength} characters.";
        }
        if ($regex !== null && $regex !== '' && preg_match((string) $regex, $value) !== 1) {
            return 'default_value does not match the required format.';
        }

        return null;
    }
}

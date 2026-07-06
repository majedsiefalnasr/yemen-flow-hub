<?php

namespace App\Support;

use App\Enums\FieldType;

/**
 * V-9 field constraint checks for authoring and publish-time validation.
 */
final class FieldDefinitionConstraintValidator
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string>
     */
    public static function validate(array $attributes): array
    {
        $errors = [];

        $minValue = $attributes['min_value'] ?? null;
        $maxValue = $attributes['max_value'] ?? null;
        if ($minValue !== null && $maxValue !== null && (float) $minValue > (float) $maxValue) {
            $errors['min_value'] = 'min_value must be less than or equal to max_value.';
        }

        $minLength = $attributes['min_length'] ?? null;
        $maxLength = $attributes['max_length'] ?? null;
        if ($minLength !== null && $maxLength !== null && (int) $minLength > (int) $maxLength) {
            $errors['min_length'] = 'min_length must be less than or equal to max_length.';
        }

        $maxFileSize = $attributes['max_file_size'] ?? null;
        if ($maxFileSize !== null && (int) $maxFileSize <= 0) {
            $errors['max_file_size'] = 'max_file_size must be a positive integer.';
        }

        $regex = $attributes['regex_pattern'] ?? null;
        if ($regex !== null && $regex !== '') {
            if (strlen((string) $regex) > 255) {
                $errors['regex_pattern'] = 'regex_pattern must be at most 255 characters.';
            } elseif (@preg_match((string) $regex, '') === false) {
                $errors['regex_pattern'] = 'regex_pattern is not a valid PCRE expression.';
            }
        }

        $type = $attributes['type'] ?? null;
        if ($type === FieldType::SELECT->value || $type === FieldType::SELECT) {
            $optionErrors = self::validateSelectOptions($attributes['options'] ?? null);
            if ($optionErrors !== null) {
                $errors['options'] = $optionErrors;
            }
        }

        return $errors;
    }

    private static function validateSelectOptions(mixed $options): ?string
    {
        if (! is_array($options)) {
            return 'SELECT options must be an array of {value, label} objects.';
        }

        $values = [];
        foreach ($options as $index => $option) {
            if (! is_array($option)) {
                return "Option at index {$index} must be an object with value and label.";
            }
            $value = $option['value'] ?? null;
            $label = $option['label'] ?? null;
            if (! is_string($value) || trim($value) === '') {
                return "Option at index {$index} requires a non-empty value.";
            }
            if (! is_string($label) || trim($label) === '') {
                return "Option at index {$index} requires a non-empty label.";
            }
            if (in_array($value, $values, true)) {
                return "Duplicate option value '{$value}'.";
            }
            $values[] = $value;
        }

        return null;
    }
}

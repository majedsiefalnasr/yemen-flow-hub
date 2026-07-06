<?php

namespace Tests\Unit\Support;

use App\Enums\FieldType;
use App\Support\FieldDefinitionConstraintValidator;
use App\Support\TypedFieldValueValidator;
use PHPUnit\Framework\TestCase;

class TypedFieldValueValidatorTest extends TestCase
{
    public function test_default_value_validates_date_format(): void
    {
        $error = TypedFieldValueValidator::validateDefaultValue(FieldType::DATE, 'not-a-date');
        $this->assertSame('default_value must be a valid ISO-8601 date (YYYY-MM-DD).', $error);

        $this->assertNull(TypedFieldValueValidator::validateDefaultValue(FieldType::DATE, '2026-06-25'));
    }

    public function test_default_value_validates_checkbox_boolean_strings(): void
    {
        $error = TypedFieldValueValidator::validateDefaultValue(FieldType::CHECKBOX, 'yes');
        $this->assertSame('default_value must be true or false.', $error);

        $this->assertNull(TypedFieldValueValidator::validateDefaultValue(FieldType::CHECKBOX, 'true'));
        $this->assertNull(TypedFieldValueValidator::validateDefaultValue(FieldType::CHECKBOX, 'false'));
    }

    public function test_field_definition_constraint_validator_rejects_invalid_default_value(): void
    {
        $errors = FieldDefinitionConstraintValidator::validate([
            'type' => FieldType::DATE->value,
            'default_value' => '06/25/2026',
        ]);

        $this->assertArrayHasKey('default_value', $errors);
    }
}

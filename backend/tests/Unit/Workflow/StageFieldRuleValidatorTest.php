<?php

namespace Tests\Unit\Workflow;

use App\Models\EngineRequest;
use App\Models\FieldDefinition;
use App\Models\StageFieldRule;
use App\Models\User;
use App\Services\Workflow\DynamicFieldOptionsResolver;
use App\Services\Workflow\StageFieldRuleValidator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class StageFieldRuleValidatorTest extends TestCase
{
    private StageFieldRuleValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new StageFieldRuleValidator(new DynamicFieldOptionsResolver);
    }

    private function field(int $id, string $key, bool $baseRequired = false): FieldDefinition
    {
        $field = new FieldDefinition;
        $field->forceFill(['id' => $id, 'key' => $key, 'is_required' => $baseRequired]);

        return $field;
    }

    private function rule(int $fieldId, bool $visible, bool $editable, bool $required): StageFieldRule
    {
        $rule = new StageFieldRule;
        $rule->forceFill([
            'field_id' => $fieldId,
            'is_visible' => $visible,
            'is_editable' => $editable,
            'is_required' => $required,
        ]);

        return $rule;
    }

    public function test_no_errors_when_all_rules_satisfied(): void
    {
        $fields = new Collection([$this->field(1, 'amount')]);
        $rules = new Collection;

        $errors = $this->validator->validateData($fields, $rules, ['amount' => 100]);

        $this->assertSame([], $errors);
    }

    public function test_required_enforced_only_on_leaving_action(): void
    {
        $fields = new Collection([$this->field(1, 'amount', baseRequired: true)]);
        $rules = new Collection;

        // Draft (lenient): empty required field is OK.
        $this->assertSame([], $this->validator->validateData($fields, $rules, [], [], false));

        // Leaving action: required field must be present.
        $errors = $this->validator->validateData($fields, $rules, [], [], true);
        $this->assertArrayHasKey('amount', $errors);
    }

    public function test_stage_rule_required_overrides_base(): void
    {
        $fields = new Collection([$this->field(1, 'note', baseRequired: false)]);
        $rules = new Collection([1 => $this->rule(1, visible: true, editable: true, required: true)]);

        $errors = $this->validator->validateData($fields, $rules, ['note' => ''], [], true);
        $this->assertArrayHasKey('note', $errors);
    }

    public function test_hidden_field_must_not_be_submitted(): void
    {
        $fields = new Collection([$this->field(1, 'secret')]);
        $rules = new Collection([1 => $this->rule(1, visible: false, editable: true, required: false)]);

        $errors = $this->validator->validateData($fields, $rules, ['secret' => 'x']);
        $this->assertArrayHasKey('secret', $errors);

        // Not submitting it is fine.
        $this->assertSame([], $this->validator->validateData($fields, $rules, []));
    }

    public function test_read_only_field_value_cannot_change(): void
    {
        $fields = new Collection([$this->field(1, 'amount')]);
        $rules = new Collection([1 => $this->rule(1, visible: true, editable: false, required: false)]);

        // Same value as previous → OK.
        $this->assertSame(
            [],
            $this->validator->validateData($fields, $rules, ['amount' => 100], ['amount' => 100]),
        );

        // Changed value → error.
        $errors = $this->validator->validateData($fields, $rules, ['amount' => 200], ['amount' => 100]);
        $this->assertArrayHasKey('amount', $errors);
    }

    public function test_required_array_field_is_empty_when_empty_array(): void
    {
        $fields = new Collection([$this->field(1, 'docs', baseRequired: true)]);
        $rules = new Collection;

        $errors = $this->validator->validateData($fields, $rules, ['docs' => []], [], true);
        $this->assertArrayHasKey('docs', $errors);

        $this->assertSame(
            [],
            $this->validator->validateData($fields, $rules, ['docs' => [1]], [], true),
        );
    }

    private function fieldWith(int $id, string $key, array $attrs): FieldDefinition
    {
        $field = new FieldDefinition;
        $field->forceFill(array_merge(['id' => $id, 'key' => $key, 'is_required' => false], $attrs));

        return $field;
    }

    public function test_regex_pattern_enforced_when_value_set(): void
    {
        $fields = collect([
            $this->fieldWith(1, 'ref', ['type' => 'TEXT', 'regex_pattern' => '/^\d{4}$/']),
        ]);
        $rules = collect([]);

        $errors = $this->validator->validateData($fields, $rules, ['ref' => 'abc'], []);
        $this->assertArrayHasKey('ref', $errors);

        $ok = $this->validator->validateData($fields, $rules, ['ref' => '1234'], []);
        $this->assertArrayNotHasKey('ref', $ok);
    }

    public function test_regex_not_enforced_when_value_empty(): void
    {
        $fields = collect([
            $this->fieldWith(1, 'ref', ['type' => 'TEXT', 'regex_pattern' => '/^\d{4}$/']),
        ]);
        $errors = $this->validator->validateData(collect([]), collect([]), [], []);
        $this->assertEmpty($errors);

        // empty string with regex — no error (required check is separate)
        $ok = $this->validator->validateData($fields, collect([]), ['ref' => ''], []);
        $this->assertArrayNotHasKey('ref', $ok);
    }

    public function test_min_max_value_enforced_for_number_field(): void
    {
        $fields = collect([
            $this->fieldWith(1, 'amount', ['type' => 'NUMBER', 'min_value' => '100', 'max_value' => '1000']),
        ]);
        $rules = collect([]);

        $errLow = $this->validator->validateData($fields, $rules, ['amount' => 50], []);
        $this->assertArrayHasKey('amount', $errLow);

        $errHigh = $this->validator->validateData($fields, $rules, ['amount' => 2000], []);
        $this->assertArrayHasKey('amount', $errHigh);

        $ok = $this->validator->validateData($fields, $rules, ['amount' => 500], []);
        $this->assertArrayNotHasKey('amount', $ok);
    }

    public function test_min_max_length_enforced_for_text_field(): void
    {
        $fields = collect([
            $this->fieldWith(1, 'note', ['type' => 'TEXT', 'min_length' => 3, 'max_length' => 10]),
        ]);
        $rules = collect([]);

        $errShort = $this->validator->validateData($fields, $rules, ['note' => 'ab'], []);
        $this->assertArrayHasKey('note', $errShort);

        $errLong = $this->validator->validateData($fields, $rules, ['note' => 'abcdefghijk'], []);
        $this->assertArrayHasKey('note', $errLong);

        $ok = $this->validator->validateData($fields, $rules, ['note' => 'hello'], []);
        $this->assertArrayNotHasKey('note', $ok);
    }

    public function test_rejects_client_metadata_for_file_field(): void
    {
        $fields = collect([
            $this->fieldWith(1, 'doc', ['type' => 'FILE', 'allowed_file_types' => ['pdf']]),
        ]);
        $rules = collect([]);
        $request = new EngineRequest;
        $request->forceFill(['id' => 1]);

        $errors = $this->validator->validateData(
            $fields,
            $rules,
            ['doc' => ['mime' => 'application/pdf', 'size_kb' => 50]],
            [],
            false,
            null,
            $request,
        );
        $this->assertArrayHasKey('doc', $errors);
        $this->assertSame('File fields must reference uploaded documents.', $errors['doc']);
    }

    public function test_rejects_file_field_without_request_context(): void
    {
        $fields = collect([
            $this->fieldWith(1, 'doc', ['type' => 'FILE']),
        ]);

        $errors = $this->validator->validateData($fields, collect([]), ['doc' => [1]], []);
        $this->assertArrayHasKey('doc', $errors);
        $this->assertSame('File fields must reference uploaded documents.', $errors['doc']);
    }

    public function test_constraints_skipped_when_no_value(): void
    {
        $fields = collect([
            $this->fieldWith(1, 'amount', ['type' => 'NUMBER', 'min_value' => '100', 'max_value' => '1000']),
        ]);
        // field not present in data at all — no constraint error
        $ok = $this->validator->validateData($fields, collect([]), [], []);
        $this->assertEmpty($ok);
    }

    public function test_static_select_rejects_unknown_value(): void
    {
        $fields = collect([
            $this->fieldWith(1, 'coverage', [
                'type' => 'SELECT',
                'options' => [
                    ['value' => 'full', 'label' => 'Full'],
                    ['value' => 'partial', 'label' => 'Partial'],
                ],
            ]),
        ]);
        $rules = collect([]);

        $errors = $this->validator->validateData($fields, $rules, ['coverage' => 'invalid'], []);
        $this->assertArrayHasKey('coverage', $errors);

        $ok = $this->validator->validateData($fields, $rules, ['coverage' => 'full'], []);
        $this->assertArrayNotHasKey('coverage', $ok);
    }

    public function test_static_select_accepts_valid_value(): void
    {
        $fields = collect([
            $this->fieldWith(1, 'coverage', [
                'type' => 'SELECT',
                'options' => [
                    ['value' => 'full', 'label' => 'Full'],
                ],
            ]),
        ]);

        $ok = $this->validator->validateData($fields, collect([]), ['coverage' => 'full'], []);
        $this->assertSame([], $ok);
    }

    public function test_unchanged_deactivated_dynamic_select_value_is_grandfathered(): void
    {
        $fields = collect([
            $this->fieldWith(1, 'merchant_pick', [
                'type' => 'DYNAMIC_SELECT',
                'dynamic_source' => 'MERCHANTS',
            ]),
        ]);
        $rules = collect([]);
        $actor = new User;
        $actor->forceFill(['id' => 1, 'bank_id' => 1]);

        $previous = ['merchant_pick' => 99];
        $ok = $this->validator->validateData(
            $fields,
            $rules,
            ['merchant_pick' => 99],
            $previous,
            false,
            $actor,
        );
        $this->assertSame([], $ok);
    }

    public function test_unchanged_static_select_value_is_grandfathered_when_option_removed(): void
    {
        $fields = collect([
            $this->fieldWith(1, 'coverage', [
                'type' => 'SELECT',
                'options' => [
                    ['value' => 'full', 'label' => 'Full'],
                ],
            ]),
        ]);

        $ok = $this->validator->validateData(
            $fields,
            collect([]),
            ['coverage' => 'legacy'],
            ['coverage' => 'legacy'],
        );
        $this->assertSame([], $ok);
    }

    public function test_date_field_rejects_invalid_format(): void
    {
        $fields = collect([
            $this->fieldWith(1, 'invoice_date', ['type' => 'DATE']),
        ]);

        $errors = $this->validator->validateData($fields, collect([]), ['invoice_date' => '06/25/2026'], []);
        $this->assertArrayHasKey('invoice_date', $errors);

        $ok = $this->validator->validateData($fields, collect([]), ['invoice_date' => '2026-06-25'], []);
        $this->assertArrayNotHasKey('invoice_date', $ok);
    }

    public function test_date_field_rejects_invalid_calendar_date(): void
    {
        $fields = collect([
            $this->fieldWith(1, 'invoice_date', ['type' => 'DATE']),
        ]);

        $errors = $this->validator->validateData($fields, collect([]), ['invoice_date' => '2026-02-30'], []);
        $this->assertArrayHasKey('invoice_date', $errors);
    }

    public function test_checkbox_field_rejects_non_boolean_value(): void
    {
        $fields = collect([
            $this->fieldWith(1, 'agree', ['type' => 'CHECKBOX']),
        ]);

        $errors = $this->validator->validateData($fields, collect([]), ['agree' => 'yes'], []);
        $this->assertArrayHasKey('agree', $errors);

        $ok = $this->validator->validateData($fields, collect([]), ['agree' => false], []);
        $this->assertArrayNotHasKey('agree', $ok);
    }
}

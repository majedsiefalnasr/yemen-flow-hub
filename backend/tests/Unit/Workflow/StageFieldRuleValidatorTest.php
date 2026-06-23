<?php

namespace Tests\Unit\Workflow;

use App\Models\FieldDefinition;
use App\Models\StageFieldRule;
use App\Services\Workflow\StageFieldRuleValidator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class StageFieldRuleValidatorTest extends TestCase
{
    private StageFieldRuleValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new StageFieldRuleValidator;
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
}

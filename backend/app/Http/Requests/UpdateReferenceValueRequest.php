<?php

namespace App\Http\Requests;

use App\Enums\AuditAction;
use App\Services\Audit\AuditService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateReferenceValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $referenceValue = $this->route('reference_value');

        return [
            'reference_table_id' => ['sometimes', Rule::prohibitedIf(fn () => $this->integer('reference_table_id') !== $referenceValue->reference_table_id)],
            'key' => ['sometimes', 'string'],
            'label' => ['required', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $referenceValue = $this->route('reference_value');
                if (! $this->has('key') || $this->input('key') === $referenceValue->key) {
                    return;
                }

                app(AuditService::class)->log(
                    AuditAction::AUTHORIZATION_FAILURE,
                    $this->user(),
                    $referenceValue,
                    [
                        'reason' => 'reference_value_key_change_attempt',
                        'attempted_key' => $this->input('key'),
                    ],
                );
                $validator->errors()->add('key', 'The reference value key is immutable.');
            },
        ];
    }
}

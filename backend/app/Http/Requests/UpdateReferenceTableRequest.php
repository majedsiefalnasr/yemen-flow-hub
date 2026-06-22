<?php

namespace App\Http\Requests;

use App\Enums\AuditAction;
use App\Services\Audit\AuditService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateReferenceTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $referenceTable = $this->route('reference_table');

        return [
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
                $referenceTable = $this->route('reference_table');
                if (! $this->has('key') || $this->input('key') === $referenceTable->key) {
                    return;
                }

                app(AuditService::class)->log(
                    AuditAction::AUTHORIZATION_FAILURE,
                    $this->user(),
                    $referenceTable,
                    [
                        'reason' => 'reference_table_key_change_attempt',
                        'attempted_key' => $this->input('key'),
                    ],
                );
                $validator->errors()->add('key', 'The reference table key is immutable.');
            },
        ];
    }
}

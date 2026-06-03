<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateImportRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bankId = $this->user()?->bank_id;
        $existingDueDateValue = $this->route('importRequest')?->due_date;
        $existingDueDate = $existingDueDateValue ? substr((string) $existingDueDateValue, 0, 10) : null;
        $incomingDueDate = $this->input('due_date');

        return [
            'merchant_id' => ['required', 'integer', Rule::exists('merchants', 'id')->where(fn ($q) => $q->where('bank_id', $bankId))],
            'currency' => ['required', 'string', 'size:3', Rule::in(['USD', 'EUR', 'SAR', 'AED', 'CNY'])],
            'amount' => ['required', 'numeric', 'min:1'],
            'supplier_name' => ['required', 'string', 'max:255'],
            'goods_description' => ['required', 'string', 'max:1000'],
            'port_of_entry' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
            'goods_type' => ['nullable', 'string', 'max:100'],
            'payment_terms' => ['nullable', 'string', Rule::in(['LC', 'TT', 'CAD'])],
            'due_date' => [
                'nullable',
                'date',
                Rule::when(
                    $incomingDueDate !== null && (string) $incomingDueDate !== (string) $existingDueDate,
                    ['after:today']
                ),
            ],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'invoice_date' => ['nullable', 'date'],
            'origin_country' => ['nullable', 'string', 'max:100'],
            'arrival_port' => ['nullable', 'string', 'max:100'],
            'shipping_port' => ['nullable', 'string', 'max:255'],
            'customs_office' => ['nullable', 'string', 'max:100'],
            'bl_number' => ['nullable', 'string', 'max:100'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreImportRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bankId = $this->user()?->bank_id;

        return [
            'merchant_id' => ['required', 'integer', Rule::exists('merchants', 'id')->where(fn ($q) => $q->where('bank_id', $bankId))],
            'currency' => ['required', 'string', 'size:3', Rule::in(['USD', 'EUR', 'SAR', 'AED', 'CNY'])],
            'amount' => ['required', 'numeric', 'min:1'],
            'supplier_name' => ['required', 'string', 'max:255'],
            'goods_description' => ['required', 'string'],
            'port_of_entry' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}

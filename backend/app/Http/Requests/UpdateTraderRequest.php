<?php

namespace App\Http\Requests;

use App\Models\Trader;
use Illuminate\Validation\Rule;

class UpdateTraderRequest extends StoreTraderRequest
{
    public function rules(): array
    {
        $traderId = $this->traderId();
        $presence = $this->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'tax_number' => [$presence, 'string', 'max:255', Rule::unique('traders', 'tax_number')->ignore($traderId)],
            'trader_name' => [$presence, 'string', 'max:255'],
            'tax_card_expiry' => [$presence, 'date'],
            'commercial_registration_number' => [$presence, 'string', 'max:255'],
            'commercial_registration_expiry' => [$presence, 'date'],
            'companies' => ['sometimes', 'array', 'max:50'],
            'companies.*.id' => ['sometimes', 'integer', Rule::exists('trader_companies', 'id')->where('trader_id', $traderId)],
            'companies.*.company_name' => ['required', 'string', 'max:255'],
            'owners' => ['sometimes', 'array', 'max:50'],
            'owners.*.id' => ['sometimes', 'integer', Rule::exists('trader_owners', 'id')->where('trader_id', $traderId)],
            'owners.*.full_name' => ['required', 'string', 'max:255'],
            'owners.*.ownership_percentage' => ['required', 'numeric', 'gt:0', 'max:100'],
            'owners.*.nationality' => ['nullable', 'string', 'max:255'],
            'owners.*.identification_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function traderId(): int
    {
        $trader = $this->route('trader');

        return $trader instanceof Trader ? $trader->id : (int) $trader;
    }
}

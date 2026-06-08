<?php

namespace App\Http\Requests;

use App\Enums\CoverageType;
use App\Enums\CurrencySource;
use App\Enums\Incoterm;
use App\Enums\InvoiceType;
use App\Enums\PaymentTermsMode;
use App\Enums\PortOfArrival;
use App\Enums\RequestType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'merchant_id' => ['nullable', 'integer', Rule::exists('merchants', 'id')->where(fn ($q) => $q->where('bank_id', $bankId))],
            'trader_id' => ['nullable', 'integer', Rule::exists('traders', 'id')],
            'currency' => ['required', 'string', 'size:3', Rule::in(['USD', 'EUR', 'SAR', 'AED', 'CNY'])],
            'amount' => ['required', 'numeric', 'min:1'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'goods_description' => ['nullable', 'string', 'max:1000'],
            'port_of_entry' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
            'goods_type' => ['nullable', 'string', 'max:100'],
            'payment_terms' => ['nullable', 'string', Rule::in(['LC', 'TT', 'CAD'])],
            'due_date' => ['nullable', 'date', 'after:today'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'invoice_date' => ['nullable', 'date'],
            'origin_country' => ['nullable', 'string', 'max:100'],
            'arrival_port' => ['nullable', 'string', 'max:100'],
            'shipping_port' => ['nullable', 'string', 'max:255'],
            'customs_office' => ['nullable', 'string', 'max:100'],
            'bl_number' => ['nullable', 'string', 'max:100'],
            'request_type' => ['nullable', Rule::enum(RequestType::class)],
            'coverage_type' => ['nullable', Rule::enum(CoverageType::class)],
            'currency_source' => ['nullable', Rule::enum(CurrencySource::class)],
            'payment_terms_mode' => ['nullable', Rule::enum(PaymentTermsMode::class)],
            'request_percentage' => ['nullable', 'numeric'],
            'request_currency' => ['nullable', 'string', 'max:10'],
            'requested_amount' => ['nullable', 'numeric', 'min:0'],
            'invoice_type' => ['nullable', Rule::enum(InvoiceType::class)],
            'invoice_currency' => ['nullable', 'string', 'max:10'],
            'unit_of_measure' => ['nullable', 'string', 'max:100'],
            'total_invoice_amount' => ['nullable', 'numeric', 'min:0'],
            'commodity' => ['nullable', 'string', 'max:1000'],
            'exporting_company_name' => ['nullable', 'string', 'max:255'],
            'exporting_company_location' => ['nullable', 'string', 'max:255'],
            'country_of_origin' => ['nullable', 'string', 'max:100'],
            'port_of_loading' => ['nullable', 'string', 'max:255'],
            'port_of_arrival' => ['nullable', Rule::enum(PortOfArrival::class)],
            'incoterm' => ['nullable', Rule::enum(Incoterm::class)],
            'final_destination' => ['nullable', 'string', 'max:255'],
            'shipping_date' => ['nullable', 'date'],
            'arrival_date' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $coverageType = $this->input('coverage_type');
            $percentage = $this->input('request_percentage');

            if ($coverageType === null || $percentage === null || ! is_numeric($percentage)) {
                return;
            }

            $percentage = (float) $percentage;

            if ($coverageType === CoverageType::FULL->value && $percentage !== 100.0) {
                $validator->errors()->add('request_percentage', 'التغطية الكاملة تستلزم نسبة 100% / Full coverage requires 100%');
            }

            if ($coverageType === CoverageType::PARTIAL->value && ($percentage < 5.0 || $percentage >= 100.0)) {
                $validator->errors()->add('request_percentage', 'التغطية الجزئية يجب أن تكون بين 5% و 100% (غير شاملة) / Partial coverage must be between 5% and 100% (exclusive)');
            }
        });
    }
}

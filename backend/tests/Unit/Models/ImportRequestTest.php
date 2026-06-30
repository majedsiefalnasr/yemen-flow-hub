<?php

namespace Tests\Unit\Models;

use App\Enums\CoverageType;
use App\Enums\CurrencySource;
use App\Enums\Incoterm;
use App\Enums\InvoiceType;
use App\Enums\PaymentTermsMode;
use App\Enums\PortOfArrival;
use App\Enums\RequestType;
use App\Models\ImportRequest;
use App\Models\Merchant;
use App\Models\Trader;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

class ImportRequestTest extends TestCase
{
    public function test_invoice_shipping_fields_are_fillable(): void
    {
        $fillable = (new ImportRequest)->getFillable();

        $expected = [
            'trader_id',
            'request_type',
            'coverage_type',
            'currency_source',
            'payment_terms_mode',
            'request_percentage',
            'request_currency',
            'requested_amount',
            'invoice_type',
            'invoice_currency',
            'unit_of_measure',
            'total_invoice_amount',
            'commodity',
            'exporting_company_name',
            'exporting_company_location',
            'country_of_origin',
            'port_of_loading',
            'port_of_arrival',
            'incoterm',
            'final_destination',
            'shipping_date',
            'arrival_date',
            'trader_snapshot_name',
            'trader_snapshot_tax_number',
            'trader_snapshot_tax_card_expiry',
            'trader_snapshot_commercial_registration_number',
            'trader_snapshot_commercial_registration_expiry',
            'voting_rule_version',
        ];

        foreach ($expected as $field) {
            $this->assertContains($field, $fillable, "{$field} must be mass assignable");
        }
    }

    public function test_invoice_shipping_casts_are_registered(): void
    {
        $casts = (new ImportRequest)->getCasts();

        $this->assertSame(RequestType::class, $casts['request_type']);
        $this->assertSame(CoverageType::class, $casts['coverage_type']);
        $this->assertSame(CurrencySource::class, $casts['currency_source']);
        $this->assertSame(PaymentTermsMode::class, $casts['payment_terms_mode']);
        $this->assertSame(InvoiceType::class, $casts['invoice_type']);
        $this->assertSame(PortOfArrival::class, $casts['port_of_arrival']);
        $this->assertSame(Incoterm::class, $casts['incoterm']);
        $this->assertSame('decimal:2', $casts['request_percentage']);
        $this->assertSame('date', $casts['shipping_date']);
        $this->assertSame('date', $casts['arrival_date']);
        $this->assertSame('date', $casts['trader_snapshot_tax_card_expiry']);
        $this->assertSame('date', $casts['trader_snapshot_commercial_registration_expiry']);
    }

    public function test_trader_and_merchant_relationships_are_independent_belongs_to_relations(): void
    {
        $model = new ImportRequest;

        $traderRelation = $model->trader();
        $merchantRelation = $model->merchant();

        $this->assertInstanceOf(BelongsTo::class, $traderRelation);
        $this->assertSame(Trader::class, $traderRelation->getRelated()::class);
        $this->assertSame('trader_id', $traderRelation->getForeignKeyName());

        $this->assertInstanceOf(BelongsTo::class, $merchantRelation);
        $this->assertSame(Merchant::class, $merchantRelation->getRelated()::class);
        $this->assertSame('merchant_id', $merchantRelation->getForeignKeyName());
    }
}

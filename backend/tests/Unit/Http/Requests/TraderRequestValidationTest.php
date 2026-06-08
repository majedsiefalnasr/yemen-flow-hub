<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StoreTraderRequest;
use App\Http\Requests\UpdateTraderRequest;
use App\Models\Trader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TraderRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_requires_core_trader_fields_and_valid_nested_payloads(): void
    {
        $validator = Validator::make([
            'companies' => [['company_name' => '']],
            'owners' => [['full_name' => '', 'ownership_percentage' => 120]],
        ], (new StoreTraderRequest)->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('tax_number', $validator->errors()->toArray());
        $this->assertArrayHasKey('trader_name', $validator->errors()->toArray());
        $this->assertArrayHasKey('companies.0.company_name', $validator->errors()->toArray());
        $this->assertArrayHasKey('owners.0.full_name', $validator->errors()->toArray());
        $this->assertArrayHasKey('owners.0.ownership_percentage', $validator->errors()->toArray());
    }

    public function test_required_owner_set_enforces_identification_fields_for_major_owners(): void
    {
        $payload = [
            ...$this->payload(),
            'owners' => [
                ['full_name' => 'Major Owner', 'ownership_percentage' => 25],
            ],
        ];
        $request = StoreTraderRequest::create('/api/traders', 'POST', $payload);
        $validator = Validator::make($payload, $request->rules());
        $request->withValidator($validator);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('owners.0.nationality', $validator->errors()->toArray());
        $this->assertArrayHasKey('owners.0.identification_number', $validator->errors()->toArray());
    }

    public function test_duplicate_tax_number_fails_on_store(): void
    {
        Trader::factory()->create(['tax_number' => 'YE-TAX-DUP']);

        $validator = Validator::make($this->payload('YE-TAX-DUP'), (new StoreTraderRequest)->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('tax_number', $validator->errors()->toArray());
    }

    public function test_update_tax_number_unique_rule_ignores_current_trader(): void
    {
        $trader = Trader::factory()->create(['tax_number' => 'YE-TAX-SELF']);
        Trader::factory()->create(['tax_number' => 'YE-TAX-OTHER']);

        $request = UpdateTraderRequest::create("/api/traders/{$trader->id}", 'PUT');
        $request->setRouteResolver(fn () => new class($trader)
        {
            public function __construct(private readonly Trader $trader) {}

            public function parameter(string $key): ?Trader
            {
                return $key === 'trader' ? $this->trader : null;
            }
        });

        $selfValidator = Validator::make($this->payload('YE-TAX-SELF'), $request->rules());
        $otherValidator = Validator::make($this->payload('YE-TAX-OTHER'), $request->rules());

        $this->assertFalse($selfValidator->fails());
        $this->assertTrue($otherValidator->fails());
        $this->assertArrayHasKey('tax_number', $otherValidator->errors()->toArray());
    }

    private function payload(string $taxNumber = 'YE-TAX-VALID'): array
    {
        return [
            'tax_number' => $taxNumber,
            'trader_name' => 'Valid Trader',
            'tax_card_expiry' => '2028-06-30',
            'commercial_registration_number' => 'CR-VALID',
            'commercial_registration_expiry' => '2028-12-31',
            'companies' => [],
            'owners' => [],
        ];
    }
}

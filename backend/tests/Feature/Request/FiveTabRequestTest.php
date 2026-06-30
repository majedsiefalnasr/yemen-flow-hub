<?php

namespace Tests\Feature\Request;

use App\Enums\CoverageType;
use App\Enums\CurrencySource;
use App\Enums\Incoterm;
use App\Enums\InvoiceType;
use App\Enums\PaymentTermsMode;
use App\Enums\PortOfArrival;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\Merchant;
use App\Models\Trader;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FiveTabRequestTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;

    private User $dataEntry;

    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCreatePermission();

        $this->bank = Bank::query()->create(['name' => 'Bank', 'code' => 'BNK', 'is_active' => true]);
        $this->dataEntry = User::query()->create([
            'name' => 'Data Entry',
            'email' => 'data-entry-five-tab@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY->value,
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);
        $this->merchant = Merchant::query()->create([
            'name' => 'Merchant',
            'tax_number' => 'M-TAX',
            'commercial_register' => 'M-CR',
            'address' => 'Sanaa',
            'contact' => '+967',
            'category' => 'general',
            'status' => 'active',
            'bank_id' => $this->bank->id,
        ]);
    }

    public function test_full_coverage_requires_100_percent(): void
    {
        $this->postPayload(['coverage_type' => CoverageType::FULL->value, 'request_percentage' => 100])
            ->assertCreated();

        $this->postPayload(['coverage_type' => CoverageType::FULL->value, 'request_percentage' => 75])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('request_percentage');
    }

    public function test_partial_coverage_must_be_between_5_and_less_than_100(): void
    {
        $this->postPayload(['coverage_type' => CoverageType::PARTIAL->value, 'request_percentage' => 5])
            ->assertCreated();

        $this->postPayload(['coverage_type' => CoverageType::PARTIAL->value, 'request_percentage' => 4.99])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('request_percentage');

        $this->postPayload(['coverage_type' => CoverageType::PARTIAL->value, 'request_percentage' => 100])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('request_percentage');
    }

    public function test_valid_trader_id_snapshots_trader_columns(): void
    {
        $trader = Trader::factory()->create([
            'trader_name' => 'Snapshot Trader',
            'tax_number' => 'SNAP-TAX',
            'tax_card_expiry' => '2027-03-01',
            'commercial_registration_number' => 'SNAP-CR',
            'commercial_registration_expiry' => '2028-04-01',
        ]);

        $response = $this->postPayload(['trader_id' => $trader->id]);

        $response->assertCreated()
            ->assertJsonPath('data.trader_id', $trader->id)
            ->assertJsonPath('data.trader_snapshot_name', 'Snapshot Trader')
            ->assertJsonPath('data.trader_snapshot_tax_number', 'SNAP-TAX');

        $this->assertDatabaseHas('import_requests', [
            'id' => $response->json('data.id'),
            'trader_snapshot_name' => 'Snapshot Trader',
            'trader_snapshot_tax_number' => 'SNAP-TAX',
            'trader_snapshot_commercial_registration_number' => 'SNAP-CR',
        ]);
    }

    public function test_without_trader_id_snapshot_columns_are_null(): void
    {
        $response = $this->postPayload();

        $response->assertCreated()
            ->assertJsonPath('data.trader_id', null)
            ->assertJsonPath('data.trader_snapshot_name', null);
    }

    public function test_trader_edits_do_not_mutate_request_snapshot(): void
    {
        $trader = Trader::factory()->create(['trader_name' => 'Original Trader']);
        $response = $this->postPayload(['trader_id' => $trader->id]);
        $request = ImportRequest::query()->findOrFail($response->json('data.id'));

        $trader->update(['trader_name' => 'Edited Trader']);

        $this->assertSame('Original Trader', $request->refresh()->trader_snapshot_name);
    }

    public function test_new_request_forces_voting_rule_version_two_and_resource_includes_new_fields(): void
    {
        $response = $this->postPayload([
            'voting_rule_version' => 99,
            'request_type' => RequestType::GOODS_IMPORT->value,
            'currency_source' => CurrencySource::OWN_FUNDS->value,
            'payment_terms_mode' => PaymentTermsMode::LETTER_OF_CREDIT->value,
            'invoice_type' => InvoiceType::PROFORMA->value,
            'port_of_arrival' => PortOfArrival::ADEN->value,
            'incoterm' => Incoterm::CIF->value,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.voting_rule_version', 2)
            ->assertJsonPath('data.request_type', RequestType::GOODS_IMPORT->value)
            ->assertJsonPath('data.currency_source', CurrencySource::OWN_FUNDS->value)
            ->assertJsonPath('data.port_of_arrival', PortOfArrival::ADEN->value);
    }

    public function test_historical_request_resource_emits_null_new_fields_and_legacy_voting_version(): void
    {
        app()->instance('workflow.transition.active', true);
        try {
            $request = ImportRequest::query()->create([
                'bank_id' => $this->bank->id,
                'merchant_id' => $this->merchant->id,
                'created_by' => $this->dataEntry->id,
                'currency' => 'USD',
                'amount' => 100,
                'supplier_name' => 'Historical Supplier',
                'goods_description' => 'Goods',
                'port_of_entry' => 'Aden',
                'status' => RequestStatus::DRAFT,
                'current_owner_role' => UserRole::DATA_ENTRY,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }

        $this->actingAs($this->dataEntry)
            ->getJson("/api/requests/{$request->id}")
            ->assertOk()
            ->assertJsonPath('data.request_type', null)
            ->assertJsonPath('data.trader_snapshot_name', null)
            ->assertJsonPath('data.voting_rule_version', 1);
    }

    private function postPayload(array $overrides = [])
    {
        return $this->actingAs($this->dataEntry)->postJson('/api/requests', [
            ...$this->validPayload(),
            ...$overrides,
        ]);
    }

    private function validPayload(): array
    {
        return [
            'merchant_id' => $this->merchant->id,
            'currency' => 'USD',
            'amount' => 5000,
            'supplier_name' => 'Supplier',
            'goods_description' => 'Goods',
            'port_of_entry' => 'Aden',
            'request_currency' => 'USD',
            'requested_amount' => 2500,
            'invoice_currency' => 'USD',
            'unit_of_measure' => 'طن',
            'total_invoice_amount' => 5000,
            'commodity' => 'قمح',
            'exporting_company_name' => 'Exporter',
            'exporting_company_location' => 'Dubai',
            'country_of_origin' => 'India',
            'port_of_loading' => 'Mumbai',
            'final_destination' => 'Sanaa',
            'shipping_date' => '2026-07-01',
            'arrival_date' => '2026-07-10',
        ];
    }

    private function seedCreatePermission(): void
    {
        $permissionId = DB::table('permissions')->insertGetId([
            'slug' => 'request.create',
            'name_ar' => 'إنشاء طلب',
            'name_en' => 'Create request',
            'group' => 'requests',
        ]);

        DB::table('role_permissions')->insert([
            ['permission_id' => $permissionId, 'role' => UserRole::DATA_ENTRY->value],
            ['permission_id' => $permissionId, 'role' => UserRole::BANK_ADMIN->value],
        ]);
    }
}

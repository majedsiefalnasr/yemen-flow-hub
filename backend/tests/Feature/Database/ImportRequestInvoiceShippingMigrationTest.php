<?php

namespace Tests\Feature\Database;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportRequestInvoiceShippingMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_shipping_and_snapshot_columns_exist(): void
    {
        $expectedColumns = [
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

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('import_requests', $column),
                "Missing import_requests.{$column}"
            );
        }
    }

    public function test_request_percentage_round_trips_as_decimal_scale_two(): void
    {
        $bank = Bank::query()->create(['name' => 'Test Bank', 'code' => 'TB', 'is_active' => true]);
        $creator = User::query()->create([
            'name' => 'Data Entry',
            'email' => 'data-entry@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY->value,
            'bank_id' => $bank->id,
            'is_active' => true,
        ]);

        app()->instance('workflow.transition.active', true);
        try {
            $request = ImportRequest::query()->create([
                'bank_id' => $bank->id,
                'created_by' => $creator->id,
                'currency' => 'USD',
                'amount' => 1000,
                'supplier_name' => 'Supplier',
                'goods_description' => 'Goods',
                'port_of_entry' => 'Aden',
                'status' => RequestStatus::DRAFT,
                'current_owner_role' => UserRole::DATA_ENTRY,
                'request_percentage' => 25.50,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }

        $this->assertSame('25.50', $request->refresh()->request_percentage);
    }

    public function test_voting_rule_version_defaults_to_legacy_for_historical_rows(): void
    {
        $bankId = DB::table('banks')->insertGetId([
            'name' => 'Legacy Bank',
            'code' => 'LB',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $userId = DB::table('users')->insertGetId([
            'name' => 'Legacy User',
            'email' => 'legacy@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY->value,
            'bank_id' => $bankId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $requestId = DB::table('import_requests')->insertGetId([
            'reference_number' => 'YFH-2026-000001',
            'bank_id' => $bankId,
            'created_by' => $userId,
            'currency' => 'USD',
            'amount' => 1000,
            'supplier_name' => 'Legacy Supplier',
            'goods_description' => 'Legacy Goods',
            'port_of_entry' => 'Aden',
            'status' => RequestStatus::DRAFT->value,
            'current_owner_role' => UserRole::DATA_ENTRY->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(1, DB::table('import_requests')->where('id', $requestId)->value('voting_rule_version'));
    }
}

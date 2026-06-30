<?php

namespace Tests\Unit\Services;

use App\Models\Trader;
use App\Models\TraderCompany;
use App\Models\TraderOwner;
use App\Services\TraderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class TraderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_persists_trader_with_nested_companies_and_owners(): void
    {
        $trader = app(TraderService::class)->create($this->payload());

        $this->assertDatabaseHas('traders', [
            'id' => $trader->id,
            'tax_number' => 'YE-TAX-171002',
            'trader_name' => 'Al Noor Trading',
        ]);
        $this->assertDatabaseHas('trader_companies', [
            'trader_id' => $trader->id,
            'company_name' => 'Al Noor Foods',
        ]);
        $this->assertDatabaseHas('trader_owners', [
            'trader_id' => $trader->id,
            'full_name' => 'Ahmed Owner',
            'identification_number' => 'ID-100',
        ]);
    }

    public function test_update_syncs_nested_records_by_id_and_removes_missing_rows(): void
    {
        $trader = Trader::factory()
            ->has(TraderCompany::factory()->state(['company_name' => 'Old Company']), 'companies')
            ->has(TraderOwner::factory()->state(['full_name' => 'Old Owner', 'ownership_percentage' => 20]), 'owners')
            ->create(['tax_number' => 'YE-TAX-OLD']);
        $company = $trader->companies()->first();
        $owner = $trader->owners()->first();

        $updated = app(TraderService::class)->update($trader, [
            ...$this->payload('YE-TAX-UPDATED'),
            'companies' => [
                ['id' => $company->id, 'company_name' => 'Updated Company'],
                ['company_name' => 'New Company'],
            ],
            'owners' => [
                ['id' => $owner->id, 'full_name' => 'Updated Owner', 'ownership_percentage' => 51],
            ],
        ]);

        $this->assertSame('YE-TAX-UPDATED', $updated->tax_number);
        $this->assertDatabaseHas('trader_companies', ['id' => $company->id, 'company_name' => 'Updated Company']);
        $this->assertDatabaseHas('trader_companies', ['trader_id' => $trader->id, 'company_name' => 'New Company']);
        $this->assertDatabaseHas('trader_owners', ['id' => $owner->id, 'full_name' => 'Updated Owner']);
        $this->assertSame(2, $trader->companies()->count());
        $this->assertSame(1, $trader->owners()->count());
    }

    public function test_update_rollback_leaves_existing_relations_unchanged(): void
    {
        $trader = Trader::factory()
            ->has(TraderCompany::factory()->state(['company_name' => 'Stable Company']), 'companies')
            ->create();

        try {
            DB::transaction(function () use ($trader): void {
                app(TraderService::class)->update($trader, [
                    ...$this->payload('YE-TAX-ROLLBACK'),
                    'companies' => [['company_name' => 'Transient Company']],
                ]);

                throw new RuntimeException('force rollback');
            });
        } catch (RuntimeException) {
            // Expected: outer transaction rolls back the nested service transaction work.
        }

        $this->assertDatabaseHas('trader_companies', [
            'trader_id' => $trader->id,
            'company_name' => 'Stable Company',
        ]);
        $this->assertDatabaseMissing('trader_companies', [
            'trader_id' => $trader->id,
            'company_name' => 'Transient Company',
        ]);
    }

    public function test_find_by_tax_number_and_snapshot_builder(): void
    {
        $trader = app(TraderService::class)->create($this->payload());

        $found = app(TraderService::class)->findByTaxNumber('YE-TAX-171002');
        $snapshot = app(TraderService::class)->buildSnapshot($trader);

        $this->assertTrue($found?->is($trader));
        $this->assertSame([
            'trader_snapshot_name' => 'Al Noor Trading',
            'trader_snapshot_tax_number' => 'YE-TAX-171002',
            'trader_snapshot_tax_card_expiry' => '2028-06-30',
            'trader_snapshot_commercial_registration_number' => 'CR-171002',
            'trader_snapshot_commercial_registration_expiry' => '2028-12-31',
        ], $snapshot);
    }

    private function payload(string $taxNumber = 'YE-TAX-171002'): array
    {
        return [
            'tax_number' => $taxNumber,
            'trader_name' => 'Al Noor Trading',
            'tax_card_expiry' => '2028-06-30',
            'commercial_registration_number' => 'CR-171002',
            'commercial_registration_expiry' => '2028-12-31',
            'companies' => [
                ['company_name' => 'Al Noor Foods'],
            ],
            'owners' => [
                [
                    'full_name' => 'Ahmed Owner',
                    'ownership_percentage' => 60,
                    'nationality' => 'Yemeni',
                    'identification_number' => 'ID-100',
                ],
            ],
        ];
    }
}

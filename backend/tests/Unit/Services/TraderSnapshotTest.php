<?php

namespace Tests\Unit\Services;

use App\Models\Trader;
use App\Services\TraderService;
use Tests\TestCase;

class TraderSnapshotTest extends TestCase
{
    public function test_build_snapshot_maps_trader_to_request_snapshot_columns(): void
    {
        $trader = new Trader([
            'trader_name' => 'شركة اختبار',
            'tax_number' => 'TAX-123',
            'tax_card_expiry' => '2027-01-15',
            'commercial_registration_number' => 'CR-123',
            'commercial_registration_expiry' => '2028-02-20',
        ]);

        $snapshot = (new TraderService)->buildSnapshot($trader);

        $this->assertSame([
            'trader_snapshot_name' => 'شركة اختبار',
            'trader_snapshot_tax_number' => 'TAX-123',
            'trader_snapshot_tax_card_expiry' => '2027-01-15',
            'trader_snapshot_commercial_registration_number' => 'CR-123',
            'trader_snapshot_commercial_registration_expiry' => '2028-02-20',
        ], $snapshot);
    }
}

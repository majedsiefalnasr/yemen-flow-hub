<?php

namespace Tests\Unit\Models;

use App\Models\Trader;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TraderSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_trader_tables_have_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('traders'));
        $this->assertTrue(Schema::hasTable('trader_companies'));
        $this->assertTrue(Schema::hasTable('trader_owners'));

        foreach (['tax_number', 'trader_name', 'tax_card_expiry', 'commercial_registration_number', 'commercial_registration_expiry'] as $column) {
            $this->assertTrue(Schema::hasColumn('traders', $column), "Missing traders.{$column}");
        }

        foreach (['trader_id', 'company_name'] as $column) {
            $this->assertTrue(Schema::hasColumn('trader_companies', $column), "Missing trader_companies.{$column}");
        }

        foreach (['trader_id', 'full_name', 'ownership_percentage', 'nationality', 'identification_number'] as $column) {
            $this->assertTrue(Schema::hasColumn('trader_owners', $column), "Missing trader_owners.{$column}");
        }
    }

    public function test_tax_number_is_unique(): void
    {
        Trader::factory()->create(['tax_number' => 'YE-TAX-UNIQUE']);

        $this->expectException(QueryException::class);

        Trader::factory()->create(['tax_number' => 'YE-TAX-UNIQUE']);
    }

    public function test_tax_number_has_unique_index(): void
    {
        $indexes = collect(DB::select("PRAGMA index_list('traders')"));
        $uniqueIndexes = $indexes->filter(fn (object $index): bool => (bool) $index->unique);

        $hasTaxNumberUniqueIndex = $uniqueIndexes->contains(function (object $index): bool {
            $columns = collect(DB::select("PRAGMA index_info('{$index->name}')"))
                ->pluck('name')
                ->all();

            return $columns === ['tax_number'];
        });

        $this->assertTrue($hasTaxNumberUniqueIndex);
    }
}

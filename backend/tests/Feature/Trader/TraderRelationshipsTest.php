<?php

namespace Tests\Feature\Trader;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\Merchant;
use App\Models\Trader;
use App\Models\TraderCompany;
use App\Models\TraderOwner;
use App\Models\User;
use Database\Seeders\TraderSeeder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TraderRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_relationship_methods_resolve_expected_relation_types(): void
    {
        $this->assertInstanceOf(HasMany::class, (new Trader)->companies());
        $this->assertInstanceOf(HasMany::class, (new Trader)->owners());
        $this->assertInstanceOf(BelongsTo::class, (new TraderCompany)->trader());
        $this->assertInstanceOf(BelongsTo::class, (new TraderOwner)->trader());
    }

    public function test_trader_resolves_companies_and_owners_in_both_directions(): void
    {
        $trader = Trader::factory()
            ->has(TraderCompany::factory()->count(2), 'companies')
            ->has(TraderOwner::factory()->count(3), 'owners')
            ->create();

        $this->assertCount(2, $trader->companies);
        $this->assertCount(3, $trader->owners);
        $this->assertTrue($trader->companies->first()->trader->is($trader));
        $this->assertTrue($trader->owners->first()->trader->is($trader));
    }

    public function test_deleting_trader_cascades_to_companies_and_owners(): void
    {
        $trader = Trader::factory()
            ->has(TraderCompany::factory()->count(2), 'companies')
            ->has(TraderOwner::factory()->count(2), 'owners')
            ->create();

        $trader->delete();

        $this->assertDatabaseMissing('traders', ['id' => $trader->id]);
        $this->assertDatabaseCount('trader_companies', 0);
        $this->assertDatabaseCount('trader_owners', 0);
    }

    public function test_factory_required_owner_state_produces_required_set_owner(): void
    {
        $owner = TraderOwner::factory()->requiredOwner()->create();

        $this->assertGreaterThanOrEqual(25, (float) $owner->ownership_percentage);
    }

    public function test_trader_seeder_is_idempotent_and_does_not_touch_historical_tables(): void
    {
        $bank = Bank::query()->create([
            'name' => 'Bank TST',
            'code' => 'TST',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'name' => 'Seeder Guard User',
            'email' => 'seeder-guard@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::DATA_ENTRY->value,
            'bank_id' => $bank->id,
            'is_active' => true,
        ]);

        $merchant = Merchant::query()->create([
            'bank_id' => $bank->id,
            'name' => 'Historical Merchant',
            'commercial_register' => 'CR-HIST-001',
            'tax_number' => 'TX-HIST-001',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        DB::table('import_requests')->insert([
            'reference_number' => 'YFH-HIST-001',
            'bank_id' => $bank->id,
            'merchant_id' => $merchant->id,
            'created_by' => $user->id,
            'currency' => 'USD',
            'amount' => 1000,
            'supplier_name' => 'Historical Supplier',
            'goods_description' => 'Historical Goods',
            'port_of_entry' => 'Aden',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $merchantSnapshot = DB::table('merchants')->where('id', $merchant->id)->first();
        $requestSnapshot = DB::table('import_requests')->where('reference_number', 'YFH-HIST-001')->first();

        $this->seed(TraderSeeder::class);
        $this->seed(TraderSeeder::class);

        $this->assertSame(8, Trader::query()->count());
        $this->assertGreaterThanOrEqual(8, TraderCompany::query()->count());
        $this->assertGreaterThanOrEqual(8, TraderOwner::query()->where('ownership_percentage', '>=', 25)->count());
        $this->assertEquals($merchantSnapshot, DB::table('merchants')->where('id', $merchant->id)->first());
        $this->assertEquals($requestSnapshot, DB::table('import_requests')->where('reference_number', 'YFH-HIST-001')->first());
    }
}

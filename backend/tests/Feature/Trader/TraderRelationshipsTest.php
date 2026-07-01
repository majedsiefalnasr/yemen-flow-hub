<?php

namespace Tests\Feature\Trader;

use App\Models\Trader;
use App\Models\TraderCompany;
use App\Models\TraderOwner;
use Database\Seeders\TraderSeeder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

}

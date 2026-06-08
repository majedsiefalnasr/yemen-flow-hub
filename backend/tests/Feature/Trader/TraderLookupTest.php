<?php

namespace Tests\Feature\Trader;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\Trader;
use App\Models\TraderCompany;
use App\Models\TraderOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TraderLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_by_tax_number_returns_trader_payload_for_any_authenticated_role(): void
    {
        $bankA = Bank::query()->create(['name' => 'Lookup Bank A', 'code' => 'LBA', 'is_active' => true]);
        $bankB = Bank::query()->create(['name' => 'Lookup Bank B', 'code' => 'LBB', 'is_active' => true]);
        $trader = Trader::factory()
            ->has(TraderCompany::factory()->state(['company_name' => 'Lookup Company']), 'companies')
            ->has(TraderOwner::factory()->state(['full_name' => 'Lookup Owner', 'ownership_percentage' => 80]), 'owners')
            ->create(['tax_number' => 'YE-TAX-LOOKUP']);

        $this->makeUser(UserRole::DATA_ENTRY, $bankA);
        $crossBankUser = $this->makeUser(UserRole::DATA_ENTRY, $bankB);

        $response = $this->actingAs($crossBankUser)->getJson('/api/traders/lookup?tax_number=YE-TAX-LOOKUP');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $trader->id)
            ->assertJsonPath('data.tax_number', 'YE-TAX-LOOKUP')
            ->assertJsonPath('data.companies.0.company_name', 'Lookup Company')
            ->assertJsonPath('data.owners.0.full_name', 'Lookup Owner');
    }

    public function test_lookup_missing_tax_number_returns_404_without_partial_data(): void
    {
        // Trader access is restricted to the bank-side trader roles (Epic 17-B
        // decision #9): Data Entry, Internal Reviewer, Bank Manager.
        $bank = Bank::query()->create(['name' => 'Lookup Bank C', 'code' => 'LBC', 'is_active' => true]);
        $user = $this->makeUser(UserRole::DATA_ENTRY, $bank);
        Trader::factory()->create(['tax_number' => 'YE-TAX-EXISTS']);

        $response = $this->actingAs($user)->getJson('/api/traders/lookup?tax_number=YE-TAX-MISSING');

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonMissing(['tax_number' => 'YE-TAX-EXISTS']);
    }

    public function test_lookup_requires_tax_number_query_parameter(): void
    {
        $bank = Bank::query()->create(['name' => 'Lookup Bank D', 'code' => 'LBD', 'is_active' => true]);
        $user = $this->makeUser(UserRole::BANK_REVIEWER, $bank);

        $this->actingAs($user)
            ->getJson('/api/traders/lookup')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tax_number']);
    }

    private function makeUser(UserRole $role, ?Bank $bank = null): User
    {
        static $counter = 0;
        $counter++;

        return User::query()->create([
            'name' => "Trader Lookup User {$counter}",
            'email' => "trader-lookup-{$counter}@example.com",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }
}

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

class TraderCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_is_global_and_filterable_by_tax_number_and_name(): void
    {
        $bankA = Bank::query()->create(['name' => 'Bank A', 'code' => 'BA', 'is_active' => true]);
        $bankB = Bank::query()->create(['name' => 'Bank B', 'code' => 'BB', 'is_active' => true]);
        $user = $this->makeUser(UserRole::DATA_ENTRY, $bankA);
        Trader::factory()
            ->has(TraderCompany::factory()->state(['company_name' => 'Global Company']), 'companies')
            ->has(TraderOwner::factory()->state(['full_name' => 'Global Owner']), 'owners')
            ->create(['tax_number' => 'GLOBAL-1', 'trader_name' => 'Global Trader']);
        Trader::factory()->create(['tax_number' => 'OTHER-1', 'trader_name' => 'Other Trader']);
        $this->makeUser(UserRole::DATA_ENTRY, $bankB);

        $response = $this->actingAs($user)->getJson('/api/traders?tax_number=GLOBAL&trader_name=Global');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.tax_number', 'GLOBAL-1')
            ->assertJsonPath('data.data.0.companies_count', 1)
            ->assertJsonPath('data.data.0.owners_count', 1);
    }

    public function test_show_returns_nested_relations_and_missing_id_returns_404(): void
    {
        $user = $this->makeUser(UserRole::DATA_ENTRY, Bank::query()->create([
            'name' => 'Show Bank', 'code' => 'SHB', 'is_active' => true,
        ]));
        $trader = Trader::factory()
            ->has(TraderCompany::factory()->state(['company_name' => 'Nested Company']), 'companies')
            ->has(TraderOwner::factory()->state(['full_name' => 'Nested Owner', 'ownership_percentage' => 75]), 'owners')
            ->create();

        $this->actingAs($user)
            ->getJson("/api/traders/{$trader->id}")
            ->assertOk()
            ->assertJsonPath('data.companies.0.company_name', 'Nested Company')
            ->assertJsonPath('data.owners.0.full_name', 'Nested Owner');

        $this->actingAs($user)
            ->getJson('/api/traders/999999')
            ->assertNotFound();
    }

    public function test_permitted_role_can_create_trader_with_nested_relations(): void
    {
        $user = $this->makeUser(UserRole::BANK_ADMIN, Bank::query()->create([
            'name' => 'Bank Admin Bank',
            'code' => 'BAB',
            'is_active' => true,
        ]));

        $response = $this->actingAs($user)->postJson('/api/traders', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tax_number', 'YE-TAX-CRUD')
            ->assertJsonPath('data.companies.0.company_name', 'Crud Company')
            ->assertJsonPath('data.owners.0.full_name', 'Crud Owner');
        $this->assertDatabaseHas('traders', ['tax_number' => 'YE-TAX-CRUD']);
        $this->assertDatabaseHas('trader_companies', ['company_name' => 'Crud Company']);
        $this->assertDatabaseHas('trader_owners', ['full_name' => 'Crud Owner']);
    }

    public function test_create_validates_duplicate_tax_number_and_required_owner_set(): void
    {
        $user = $this->makeUser(UserRole::DATA_ENTRY);
        Trader::factory()->create(['tax_number' => 'YE-TAX-CRUD']);

        $this->actingAs($user)
            ->postJson('/api/traders', $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['tax_number']);

        $this->actingAs($user)
            ->postJson('/api/traders', [
                ...$this->payload('YE-TAX-OWNER'),
                'owners' => [['full_name' => 'Major Owner', 'ownership_percentage' => 25]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['owners.0.nationality', 'owners.0.identification_number']);

        $this->actingAs($user)
            ->postJson('/api/traders', [
                ...$this->payload('YE-TAX-NESTED-ID'),
                'companies' => [['id' => 999, 'company_name' => 'Invalid ID Company']],
                'owners' => [[
                    'id' => 999,
                    'full_name' => 'Invalid ID Owner',
                    'ownership_percentage' => 24,
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['companies.0.id', 'owners.0.id']);
    }

    public function test_update_syncs_nested_relations_and_ignores_own_tax_number_for_uniqueness(): void
    {
        $user = $this->makeUser(UserRole::BANK_REVIEWER);
        $trader = Trader::factory()
            ->has(TraderCompany::factory()->state(['company_name' => 'Old Company']), 'companies')
            ->has(TraderOwner::factory()->state(['full_name' => 'Old Owner']), 'owners')
            ->create(['tax_number' => 'YE-TAX-SELF']);
        $company = $trader->companies()->first();
        $owner = $trader->owners()->first();

        $response = $this->actingAs($user)->putJson("/api/traders/{$trader->id}", [
            ...$this->payload('YE-TAX-SELF'),
            'trader_name' => 'Updated Trader',
            'companies' => [
                ['id' => $company->id, 'company_name' => 'Updated Company'],
            ],
            'owners' => [
                ['id' => $owner->id, 'full_name' => 'Updated Owner', 'ownership_percentage' => 24],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.trader_name', 'Updated Trader')
            ->assertJsonPath('data.companies.0.company_name', 'Updated Company')
            ->assertJsonPath('data.owners.0.full_name', 'Updated Owner');
        $this->assertSame(1, $trader->companies()->count());
        $this->assertSame(1, $trader->owners()->count());
    }

    public function test_update_rejects_foreign_nested_ids_and_patch_allows_partial_scalar_update(): void
    {
        $user = $this->makeUser(UserRole::BANK_REVIEWER);
        $trader = Trader::factory()->create(['trader_name' => 'Before Patch']);
        $foreignTrader = Trader::factory()
            ->has(TraderCompany::factory()->state(['company_name' => 'Foreign Company']), 'companies')
            ->has(TraderOwner::factory()->state(['full_name' => 'Foreign Owner']), 'owners')
            ->create();

        $this->actingAs($user)
            ->putJson("/api/traders/{$trader->id}", [
                ...$this->payload('YE-TAX-FOREIGN-ID'),
                'companies' => [['id' => $foreignTrader->companies()->first()->id, 'company_name' => 'Hijack Company']],
                'owners' => [[
                    'id' => $foreignTrader->owners()->first()->id,
                    'full_name' => 'Hijack Owner',
                    'ownership_percentage' => 24,
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['companies.0.id', 'owners.0.id']);

        $this->actingAs($user)
            ->patchJson("/api/traders/{$trader->id}", ['trader_name' => 'After Patch'])
            ->assertOk()
            ->assertJsonPath('data.trader_name', 'After Patch');
    }

    public function test_non_permitted_roles_cannot_read_or_write(): void
    {
        // Epic 17-B decision #9: trader data (incl. owner identification PII) is
        // restricted to the bank-side trader roles. All other roles are denied
        // read and write alike — least privilege on owner identification data.
        $trader = Trader::factory()->create();
        $blockedRoles = [
            UserRole::SWIFT_OFFICER,
            UserRole::SUPPORT_COMMITTEE,
            UserRole::EXECUTIVE_MEMBER,
            UserRole::COMMITTEE_DIRECTOR,
            UserRole::CBY_ADMIN,
        ];

        foreach ($blockedRoles as $role) {
            $user = $this->makeUser($role);

            $this->actingAs($user)->getJson('/api/traders')->assertForbidden();
            $this->actingAs($user)->getJson("/api/traders/{$trader->id}")->assertForbidden();
            $this->actingAs($user)
                ->postJson('/api/traders', $this->payload("YE-TAX-{$role->value}"))
                ->assertForbidden()
                ->assertJsonPath('success', false)
                ->assertJsonPath('error_code', 'WORKFLOW_FORBIDDEN');
            $this->actingAs($user)
                ->putJson("/api/traders/{$trader->id}", $this->payload("YE-TAX-UP-{$role->value}"))
                ->assertForbidden()
                ->assertJsonPath('error_code', 'WORKFLOW_FORBIDDEN');
        }
    }

    private function makeUser(UserRole $role, ?Bank $bank = null): User
    {
        static $counter = 0;
        $counter++;

        return User::query()->create([
            'name' => "Trader CRUD User {$counter}",
            'email' => "trader-crud-{$counter}@example.com",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }

    private function payload(string $taxNumber = 'YE-TAX-CRUD'): array
    {
        return [
            'tax_number' => $taxNumber,
            'trader_name' => 'Crud Trader',
            'tax_card_expiry' => '2028-06-30',
            'commercial_registration_number' => "CR-{$taxNumber}",
            'commercial_registration_expiry' => '2028-12-31',
            'companies' => [
                ['company_name' => 'Crud Company'],
            ],
            'owners' => [
                [
                    'full_name' => 'Crud Owner',
                    'ownership_percentage' => 60,
                    'nationality' => 'Yemeni',
                    'identification_number' => 'ID-CRUD',
                ],
            ],
        ];
    }
}

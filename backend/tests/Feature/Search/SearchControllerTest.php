<?php

namespace Tests\Feature\Search;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\CustomsDeclaration;
use App\Models\ImportRequest;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bankA;

    private Bank $bankB;

    private User $dataEntryA;

    private User $bankReviewerA;

    private User $bankAdminA;

    private User $swiftOfficerA;

    private User $supportUser;

    private User $execMember;

    private User $director;

    private User $cbyadmin;

    private ImportRequest $requestA;

    private ImportRequest $requestB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bankA = $this->makeBank('YCBA', 'Bank Alpha');
        $this->bankB = $this->makeBank('YCBB', 'Bank Beta');

        $this->dataEntryA = $this->makeUser(UserRole::DATA_ENTRY, $this->bankA);
        $this->bankReviewerA = $this->makeUser(UserRole::BANK_REVIEWER, $this->bankA);
        $this->bankAdminA = $this->makeUser(UserRole::BANK_ADMIN, $this->bankA);
        $this->swiftOfficerA = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bankA);
        $this->supportUser = $this->makeUser(UserRole::SUPPORT_COMMITTEE);
        $this->execMember = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $this->director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $this->cbyadmin = $this->makeUser(UserRole::CBY_ADMIN);

        $merchantA = $this->makeMerchant($this->bankA);
        $merchantB = $this->makeMerchant($this->bankB);
        $dataEntryB = $this->makeUser(UserRole::DATA_ENTRY, $this->bankB);

        $this->requestA = $this->makeRequest($this->bankA, $merchantA, $this->dataEntryA, 'REF-ALPHA-001', 'Alpha Supplier');
        $this->requestB = $this->makeRequest($this->bankB, $merchantB, $dataEntryB, 'REF-BETA-001', 'Beta Supplier');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeBank(string $code, string $name = 'Test Bank'): Bank
    {
        return Bank::query()->create([
            'name' => $name,
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private static int $userCounter = 0;

    private function makeUser(UserRole $role, ?Bank $bank = null): User
    {
        self::$userCounter++;

        return User::query()->create([
            'name' => 'User '.self::$userCounter,
            'email' => 'user'.self::$userCounter.'@example.com',
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }

    private function makeMerchant(Bank $bank): Merchant
    {
        static $mc = 0;
        $mc++;

        return Merchant::query()->create([
            'name' => "Merchant {$mc}",
            'tax_number' => "TX-{$mc}",
            'commercial_register' => "CR-{$mc}",
            'address' => 'Sanaa, Yemen',
            'contact' => '+9671234567',
            'category' => 'general',
            'status' => 'active',
            'bank_id' => $bank->id,
        ]);
    }

    private function makeRequest(Bank $bank, Merchant $merchant, User $creator, string $refNumber, string $supplierName): ImportRequest
    {
        app()->instance('workflow.transition.active', true);
        try {
            return ImportRequest::query()->create([
                'reference_number' => $refNumber,
                'bank_id' => $bank->id,
                'merchant_id' => $merchant->id,
                'created_by' => $creator->id,
                'currency' => 'USD',
                'amount' => 50000.00,
                'supplier_name' => $supplierName,
                'goods_description' => 'Industrial equipment',
                'port_of_entry' => 'Aden Port',
                'notes' => null,
                'status' => RequestStatus::SUBMITTED,
                'current_owner_role' => UserRole::BANK_REVIEWER,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    private function makeCustomsDeclaration(ImportRequest $request, string $declarationNumber): CustomsDeclaration
    {
        return CustomsDeclaration::query()->create([
            'request_id' => $request->id,
            'declaration_number' => $declarationNumber,
            'issued_by' => $this->cbyadmin->id,
            'issued_at' => now(),
            'pdf_path' => 'customs/test/'.$declarationNumber.'.pdf',
            'metadata' => null,
        ]);
    }

    // ─── AC1: Authentication guard ────────────────────────────────────────────

    public function test_unauthenticated_search_returns_401(): void
    {
        $this->getJson('/api/search?q=alpha')->assertStatus(401);
    }

    public function test_unauthenticated_recent_returns_401(): void
    {
        $this->getJson('/api/search/recent')->assertStatus(401);
    }

    // ─── AC1: Empty query returns empty groups ────────────────────────────────

    public function test_empty_query_returns_empty_groups(): void
    {
        $response = $this->actingAs($this->dataEntryA)
            ->getJson('/api/search?q=');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.requests', [])
            ->assertJsonPath('data.users', [])
            ->assertJsonPath('data.banks', [])
            ->assertJsonPath('data.customs', []);
    }

    public function test_single_char_query_returns_empty_groups_without_db_hit(): void
    {
        $response = $this->actingAs($this->dataEntryA)
            ->getJson('/api/search?q=a');

        $response->assertStatus(200)
            ->assertJsonPath('data.requests', [])
            ->assertJsonPath('data.users', [])
            ->assertJsonPath('data.banks', [])
            ->assertJsonPath('data.customs', []);
    }

    // ─── AC2: Bank-scoped request results ────────────────────────────────────

    public function test_bank_user_only_sees_own_bank_requests(): void
    {
        $response = $this->actingAs($this->dataEntryA)
            ->getJson('/api/search?q=REF');

        $response->assertStatus(200);

        $requestIds = collect($response->json('data.requests'))->pluck('id')->all();
        $this->assertContains($this->requestA->id, $requestIds);
        $this->assertNotContains($this->requestB->id, $requestIds);
    }

    public function test_cby_user_sees_all_bank_requests(): void
    {
        $response = $this->actingAs($this->cbyadmin)
            ->getJson('/api/search?q=REF');

        $response->assertStatus(200);

        $requestIds = collect($response->json('data.requests'))->pluck('id')->all();
        $this->assertContains($this->requestA->id, $requestIds);
        $this->assertContains($this->requestB->id, $requestIds);
    }

    public function test_request_search_matches_reference_number(): void
    {
        $response = $this->actingAs($this->dataEntryA)
            ->getJson('/api/search?q=ALPHA-001');

        $requestIds = collect($response->json('data.requests'))->pluck('id')->all();
        $this->assertContains($this->requestA->id, $requestIds);
    }

    public function test_request_search_matches_supplier_name(): void
    {
        $response = $this->actingAs($this->dataEntryA)
            ->getJson('/api/search?q=Alpha Supplier');

        $requestIds = collect($response->json('data.requests'))->pluck('id')->all();
        $this->assertContains($this->requestA->id, $requestIds);
    }

    // ─── AC3: Admin-only user search ─────────────────────────────────────────

    public function test_data_entry_gets_empty_users_array(): void
    {
        $response = $this->actingAs($this->dataEntryA)
            ->getJson('/api/search?q=User');

        $response->assertStatus(200)
            ->assertJsonPath('data.users', []);
    }

    public function test_bank_admin_sees_only_own_bank_manageable_users(): void
    {
        // dataEntryA and bankReviewerA belong to bankA — should appear
        // cbyadmin and supportUser have no bank or different bank — should NOT appear
        $response = $this->actingAs($this->bankAdminA)
            ->getJson('/api/search?q=User');

        $response->assertStatus(200);

        $userIds = collect($response->json('data.users'))->pluck('id')->all();
        $this->assertContains($this->dataEntryA->id, $userIds);
        $this->assertContains($this->bankReviewerA->id, $userIds);
        $this->assertNotContains($this->cbyadmin->id, $userIds);
        $this->assertNotContains($this->supportUser->id, $userIds);
    }

    public function test_bank_admin_does_not_see_swift_officer_in_user_results(): void
    {
        $response = $this->actingAs($this->bankAdminA)
            ->getJson('/api/search?q=User');

        $userIds = collect($response->json('data.users'))->pluck('id')->all();
        $this->assertNotContains($this->swiftOfficerA->id, $userIds);
    }

    public function test_bank_admin_with_null_bank_id_gets_empty_users_array(): void
    {
        $banklessAdmin = $this->makeUser(UserRole::BANK_ADMIN);

        $response = $this->actingAs($banklessAdmin)
            ->getJson('/api/search?q=User');

        $response->assertStatus(200)
            ->assertJsonPath('data.users', []);
    }

    public function test_cby_admin_sees_all_users(): void
    {
        $response = $this->actingAs($this->cbyadmin)
            ->getJson('/api/search?q=User');

        $response->assertStatus(200);

        $userIds = collect($response->json('data.users'))->pluck('id')->all();
        $this->assertContains($this->dataEntryA->id, $userIds);
        $this->assertContains($this->supportUser->id, $userIds);
    }

    // ─── AC4: Bank results for CBY admin only ────────────────────────────────

    public function test_cby_admin_sees_bank_results(): void
    {
        $response = $this->actingAs($this->cbyadmin)
            ->getJson('/api/search?q=Alpha');

        $response->assertStatus(200);

        $bankIds = collect($response->json('data.banks'))->pluck('id')->all();
        $this->assertContains($this->bankA->id, $bankIds);
    }

    public function test_cby_admin_search_excludes_inactive_banks(): void
    {
        $inactiveBank = $this->makeBank('YCBX', 'Bank Gamma');
        $inactiveBank->update(['is_active' => false]);

        $response = $this->actingAs($this->cbyadmin)
            ->getJson('/api/search?q=Bank');

        $bankIds = collect($response->json('data.banks'))->pluck('id')->all();
        $this->assertContains($this->bankA->id, $bankIds);
        $this->assertContains($this->bankB->id, $bankIds);
        $this->assertNotContains($inactiveBank->id, $bankIds);
    }

    public function test_bank_scoped_user_gets_empty_banks_array(): void
    {
        $response = $this->actingAs($this->dataEntryA)
            ->getJson('/api/search?q=Alpha');

        $response->assertStatus(200)
            ->assertJsonPath('data.banks', []);
    }

    public function test_support_user_gets_empty_banks_array(): void
    {
        $response = $this->actingAs($this->supportUser)
            ->getJson('/api/search?q=Alpha');

        $response->assertStatus(200)
            ->assertJsonPath('data.banks', []);
    }

    // ─── AC5: Customs declaration results ────────────────────────────────────

    public function test_bank_user_only_sees_own_bank_customs(): void
    {
        $customsA = $this->makeCustomsDeclaration($this->requestA, 'DECL-ALPHA-2026');
        $customsB = $this->makeCustomsDeclaration($this->requestB, 'DECL-BETA-2026');

        $response = $this->actingAs($this->dataEntryA)
            ->getJson('/api/search?q=DECL');

        $response->assertStatus(200);

        $customsIds = collect($response->json('data.customs'))->pluck('id')->all();
        $this->assertContains($customsA->id, $customsIds);
        $this->assertNotContains($customsB->id, $customsIds);
    }

    public function test_cby_user_sees_all_customs_declarations(): void
    {
        $customsA = $this->makeCustomsDeclaration($this->requestA, 'DECL-ALPHA-2026');
        $customsB = $this->makeCustomsDeclaration($this->requestB, 'DECL-BETA-2026');

        $response = $this->actingAs($this->cbyadmin)
            ->getJson('/api/search?q=DECL');

        $response->assertStatus(200);

        $customsIds = collect($response->json('data.customs'))->pluck('id')->all();
        $this->assertContains($customsA->id, $customsIds);
        $this->assertContains($customsB->id, $customsIds);
    }

    // ─── AC6: Recent searches ────────────────────────────────────────────────

    public function test_recent_searches_returns_empty_initially(): void
    {
        $response = $this->actingAs($this->dataEntryA)
            ->getJson('/api/search/recent');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.recent_searches', []);
    }

    public function test_search_persists_query_to_recent_searches(): void
    {
        $this->actingAs($this->dataEntryA)->getJson('/api/search?q=ALPHA-001');
        $this->actingAs($this->dataEntryA)->getJson('/api/search?q=Beta Sup');
        $this->actingAs($this->dataEntryA)->getJson('/api/search?q=Aden');

        $response = $this->actingAs($this->dataEntryA)
            ->getJson('/api/search/recent');

        $response->assertStatus(200);

        $recent = $response->json('data.recent_searches');
        $this->assertCount(3, $recent);
        $this->assertEquals('Aden', $recent[0]);
        $this->assertEquals('ALPHA-001', $recent[2]);
    }

    public function test_duplicate_query_is_deduped_and_moved_to_front(): void
    {
        $this->actingAs($this->dataEntryA)->getJson('/api/search?q=ALPHA-001');
        $this->actingAs($this->dataEntryA)->getJson('/api/search?q=Supplier');
        $this->actingAs($this->dataEntryA)->getJson('/api/search?q=ALPHA-001');

        $response = $this->actingAs($this->dataEntryA)
            ->getJson('/api/search/recent');

        $recent = $response->json('data.recent_searches');
        $this->assertCount(2, $recent);
        $this->assertEquals('ALPHA-001', $recent[0]);
    }

    public function test_recent_searches_trimmed_to_10(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->actingAs($this->dataEntryA)->getJson('/api/search?q=query'.$i);
        }

        $response = $this->actingAs($this->dataEntryA)
            ->getJson('/api/search/recent');

        $recent = $response->json('data.recent_searches');
        $this->assertCount(10, $recent);
        $this->assertEquals('query12', $recent[0]);
    }

    public function test_short_query_is_not_persisted_to_recent(): void
    {
        $this->actingAs($this->dataEntryA)->getJson('/api/search?q=a');

        $response = $this->actingAs($this->dataEntryA)
            ->getJson('/api/search/recent');

        $this->assertEmpty($response->json('data.recent_searches'));
    }
}

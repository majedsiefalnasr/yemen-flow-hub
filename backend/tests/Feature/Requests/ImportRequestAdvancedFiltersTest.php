<?php

namespace Tests\Feature\Requests;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ImportRequestAdvancedFiltersTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;
    private Bank $otherBank;
    private User $bankReviewer;
    private User $otherBankReviewer;
    private User $cbyadmin;
    private User $dataEntry;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->bank           = $this->makeBank('YCB');
        $this->otherBank      = $this->makeBank('OTH');
        $this->bankReviewer   = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $this->otherBankReviewer = $this->makeUser(UserRole::BANK_REVIEWER, $this->otherBank);
        $this->cbyadmin       = $this->makeUser(UserRole::CBY_ADMIN);
        $this->dataEntry      = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeBank(string $code): Bank
    {
        return Bank::query()->create([
            'name'      => "بنك {$code}",
            'code'      => $code,
            'is_active' => true,
        ]);
    }

    private function makeUser(UserRole $role, ?Bank $bank = null): User
    {
        static $counter = 0;
        $counter++;

        return User::query()->create([
            'name'      => "User {$counter}",
            'email'     => "adv_filter_user{$counter}@example.com",
            'password'  => Hash::make('password'),
            'role'      => $role->value,
            'bank_id'   => $bank?->id,
            'is_active' => true,
        ]);
    }

    private function makeRequest(
        Bank $bank,
        User $creator,
        array $overrides = [],
    ): ImportRequest {
        $merchant = Merchant::query()->create([
            'name'                => 'تاجر اختبار',
            'tax_number'          => 'TX-AF-' . uniqid(),
            'commercial_register' => 'CR-AF-' . uniqid(),
            'address'             => 'صنعاء',
            'contact'             => '+9670000000',
            'category'            => 'general',
            'status'              => 'active',
            'bank_id'             => $bank->id,
        ]);

        app()->instance('workflow.transition.active', true);

        try {
            return ImportRequest::query()->create(array_merge([
                'bank_id'          => $bank->id,
                'merchant_id'      => $merchant->id,
                'created_by'       => $creator->id,
                'currency'         => 'USD',
                'amount'           => 10000.00,
                'supplier_name'    => 'Supplier Co.',
                'goods_description' => 'Industrial equipment',
                'port_of_entry'    => 'Aden Port',
                'status'           => RequestStatus::DRAFT,
                'current_owner_role' => UserRole::DATA_ENTRY,
            ], $overrides));
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    // ── AC-2: Validation — 422 on bad input ───────────────────────────────────

    public function test_invalid_created_from_returns_422(): void
    {
        $this->actingAs($this->cbyadmin)
            ->getJson('/api/requests?created_from=not-a-date')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['created_from']);
    }

    public function test_invalid_created_to_returns_422(): void
    {
        $this->actingAs($this->cbyadmin)
            ->getJson('/api/requests?created_to=not-a-date')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['created_to']);
    }

    public function test_non_numeric_amount_min_returns_422(): void
    {
        $this->actingAs($this->cbyadmin)
            ->getJson('/api/requests?amount_min=abc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount_min']);
    }

    public function test_non_numeric_amount_max_returns_422(): void
    {
        $this->actingAs($this->cbyadmin)
            ->getJson('/api/requests?amount_max=abc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount_max']);
    }

    public function test_negative_amount_min_returns_422(): void
    {
        $this->actingAs($this->cbyadmin)
            ->getJson('/api/requests?amount_min=-1')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount_min']);
    }

    public function test_amount_max_less_than_amount_min_returns_422(): void
    {
        $this->actingAs($this->cbyadmin)
            ->getJson('/api/requests?amount_min=5000&amount_max=1000')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount_max']);
    }

    public function test_valid_params_do_not_return_422(): void
    {
        $this->actingAs($this->cbyadmin)
            ->getJson('/api/requests?created_from=2026-01-01&created_to=2026-12-31&amount_min=1000&amount_max=5000&assigned_reviewer_id=1')
            ->assertOk();
    }

    // ── AC-1 / AC-3: created_from / created_to filter ────────────────────────

    public function test_created_from_filters_out_older_requests(): void
    {
        $old    = $this->makeRequest($this->bank, $this->dataEntry);
        $recent = $this->makeRequest($this->bank, $this->dataEntry);

        ImportRequest::where('id', $old->id)->update(['created_at' => '2024-01-01 00:00:00']);
        ImportRequest::where('id', $recent->id)->update(['created_at' => '2026-05-01 00:00:00']);

        $response = $this->actingAs($this->cbyadmin)
            ->getJson('/api/requests?created_from=2026-01-01')
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertContains($recent->id, $ids->all());
        $this->assertNotContains($old->id, $ids->all());
    }

    public function test_created_to_filters_out_newer_requests(): void
    {
        $old    = $this->makeRequest($this->bank, $this->dataEntry);
        $recent = $this->makeRequest($this->bank, $this->dataEntry);

        ImportRequest::where('id', $old->id)->update(['created_at' => '2024-01-01 00:00:00']);
        ImportRequest::where('id', $recent->id)->update(['created_at' => '2026-05-01 00:00:00']);

        $response = $this->actingAs($this->cbyadmin)
            ->getJson('/api/requests?created_to=2025-12-31')
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertContains($old->id, $ids->all());
        $this->assertNotContains($recent->id, $ids->all());
    }

    public function test_legacy_from_date_to_date_still_work(): void
    {
        $old    = $this->makeRequest($this->bank, $this->dataEntry);
        $recent = $this->makeRequest($this->bank, $this->dataEntry);

        ImportRequest::where('id', $old->id)->update(['created_at' => '2024-01-01 00:00:00']);
        ImportRequest::where('id', $recent->id)->update(['created_at' => '2026-05-01 00:00:00']);

        $response = $this->actingAs($this->cbyadmin)
            ->getJson('/api/requests?from_date=2026-01-01')
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertContains($recent->id, $ids->all());
        $this->assertNotContains($old->id, $ids->all());
    }

    // ── AC-1 / AC-3: amount_min / amount_max ─────────────────────────────────

    public function test_amount_min_filters_low_amount_requests(): void
    {
        $low  = $this->makeRequest($this->bank, $this->dataEntry, ['amount' => 500.00]);
        $high = $this->makeRequest($this->bank, $this->dataEntry, ['amount' => 50000.00]);

        $response = $this->actingAs($this->cbyadmin)
            ->getJson('/api/requests?amount_min=10000')
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertContains($high->id, $ids->all());
        $this->assertNotContains($low->id, $ids->all());
    }

    public function test_amount_max_filters_high_amount_requests(): void
    {
        $low  = $this->makeRequest($this->bank, $this->dataEntry, ['amount' => 500.00]);
        $high = $this->makeRequest($this->bank, $this->dataEntry, ['amount' => 50000.00]);

        $response = $this->actingAs($this->cbyadmin)
            ->getJson('/api/requests?amount_max=1000')
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertContains($low->id, $ids->all());
        $this->assertNotContains($high->id, $ids->all());
    }

    public function test_amount_range_filters_correctly(): void
    {
        $inRange  = $this->makeRequest($this->bank, $this->dataEntry, ['amount' => 15000.00]);
        $tooLow   = $this->makeRequest($this->bank, $this->dataEntry, ['amount' => 500.00]);
        $tooHigh  = $this->makeRequest($this->bank, $this->dataEntry, ['amount' => 999999.00]);

        $response = $this->actingAs($this->cbyadmin)
            ->getJson('/api/requests?amount_min=5000&amount_max=50000')
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertContains($inRange->id, $ids->all());
        $this->assertNotContains($tooLow->id, $ids->all());
        $this->assertNotContains($tooHigh->id, $ids->all());
    }

    // ── AC-4 / AC-5: assigned_reviewer_id ────────────────────────────────────

    public function test_assigned_reviewer_id_filters_by_reviewed_by_column(): void
    {
        $reviewer = $this->bankReviewer;

        $matched    = $this->makeRequest($this->bank, $this->dataEntry, ['reviewed_by' => $reviewer->id]);
        $unmatched  = $this->makeRequest($this->bank, $this->dataEntry);

        $response = $this->actingAs($this->cbyadmin)
            ->getJson("/api/requests?assigned_reviewer_id={$reviewer->id}")
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertContains($matched->id, $ids->all());
        $this->assertNotContains($unmatched->id, $ids->all());
    }

    public function test_assigned_reviewer_id_for_out_of_scope_reviewer_silently_ignored(): void
    {
        $inBankReq  = $this->makeRequest($this->bank, $this->dataEntry);
        $otherReq   = $this->makeRequest($this->otherBank, $this->otherBankReviewer);

        // Act as bank-scoped reviewer; pass the OTHER bank's reviewer id
        $response = $this->actingAs($this->bankReviewer)
            ->getJson("/api/requests?assigned_reviewer_id={$this->otherBankReviewer->id}")
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id');
        // Filter is silently ignored → own bank request appears
        $this->assertContains($inBankReq->id, $ids->all());
        // Cross-bank request is not visible (org scope still enforced)
        $this->assertNotContains($otherReq->id, $ids->all());
    }

    public function test_assigned_reviewer_id_in_scope_applied_for_bank_actor(): void
    {
        $reviewer = $this->bankReviewer;

        $matched   = $this->makeRequest($this->bank, $this->dataEntry, ['reviewed_by' => $reviewer->id]);
        $unmatched = $this->makeRequest($this->bank, $this->dataEntry);

        $secondBankReviewer = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);

        $response = $this->actingAs($secondBankReviewer)
            ->getJson("/api/requests?assigned_reviewer_id={$reviewer->id}")
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertContains($matched->id, $ids->all());
        $this->assertNotContains($unmatched->id, $ids->all());
    }

    // ── AC-3: composition with existing filters ───────────────────────────────

    public function test_advanced_filters_compose_with_currency_filter(): void
    {
        $usdHigh = $this->makeRequest($this->bank, $this->dataEntry, ['currency' => 'USD', 'amount' => 50000.00]);
        $eurLow  = $this->makeRequest($this->bank, $this->dataEntry, ['currency' => 'EUR', 'amount' => 100.00]);
        $usdLow  = $this->makeRequest($this->bank, $this->dataEntry, ['currency' => 'USD', 'amount' => 100.00]);

        $response = $this->actingAs($this->cbyadmin)
            ->getJson('/api/requests?currency=USD&amount_min=10000')
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertContains($usdHigh->id, $ids->all());
        $this->assertNotContains($eurLow->id, $ids->all());
        $this->assertNotContains($usdLow->id, $ids->all());
    }

    public function test_advanced_filters_respect_org_scoping(): void
    {
        $ownReq   = $this->makeRequest($this->bank, $this->dataEntry, ['amount' => 50000.00]);
        $otherReq = $this->makeRequest($this->otherBank, $this->otherBankReviewer, ['amount' => 50000.00]);

        $response = $this->actingAs($this->bankReviewer)
            ->getJson('/api/requests?amount_min=1000')
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertContains($ownReq->id, $ids->all());
        $this->assertNotContains($otherReq->id, $ids->all());
    }

    // ── AC-6: No N+1 ─────────────────────────────────────────────────────────

    public function test_index_query_count_does_not_grow_with_more_requests(): void
    {
        // Seed 10 requests
        for ($i = 0; $i < 10; $i++) {
            $this->makeRequest($this->bank, $this->dataEntry);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->cbyadmin)
            ->getJson('/api/requests?amount_min=0')
            ->assertOk();

        $queryCountWith10 = count(DB::getQueryLog());
        DB::flushQueryLog();

        // Seed 10 more (20 total)
        for ($i = 0; $i < 10; $i++) {
            $this->makeRequest($this->bank, $this->dataEntry);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->cbyadmin)
            ->getJson('/api/requests?amount_min=0')
            ->assertOk();

        $queryCountWith20 = count(DB::getQueryLog());

        $this->assertEquals($queryCountWith10, $queryCountWith20, 'N+1 detected: query count grew with more records');
    }
}

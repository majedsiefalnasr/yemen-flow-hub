<?php

namespace Tests\Feature\Documents;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DocumentTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;
    private Bank $otherBank;
    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bank = $this->makeBank('YCB');
        $this->otherBank = $this->makeBank('OTH');
        $this->merchant = Merchant::query()->create([
            'name' => 'شركة اختبار الاستيراد',
            'business_type' => 'تجارة عامة',
            'tax_number' => 'TAX-12345',
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);
    }

    public function test_data_entry_of_correct_bank_downloads_confirmation_request_template(): void
    {
        $user = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($user, RequestStatus::DRAFT);

        $response = $this->actingAs($user)
            ->get("/api/requests/{$request->id}/confirmation-request-template");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->streamedContent());
    }

    public function test_data_entry_of_wrong_bank_cannot_download_confirmation_request_template(): void
    {
        $owner = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $otherUser = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);
        $request = $this->makeRequest($owner, RequestStatus::DRAFT);

        $this->actingAs($otherUser)
            ->get("/api/requests/{$request->id}/confirmation-request-template")
            ->assertForbidden();
    }

    public function test_bank_reviewer_cannot_download_confirmation_request_template(): void
    {
        $owner = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $reviewer = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $request = $this->makeRequest($owner, RequestStatus::DRAFT);

        $this->actingAs($reviewer)
            ->get("/api/requests/{$request->id}/confirmation-request-template")
            ->assertForbidden();
    }

    public function test_committee_director_downloads_fx_confirmation_template_for_executive_approved_request(): void
    {
        $owner = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $request = $this->makeRequest($owner, RequestStatus::EXECUTIVE_APPROVED);

        $response = $this->actingAs($director)
            ->get("/api/requests/{$request->id}/fx-confirmation-template");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->streamedContent());
    }

    // ─── confirmation-request-preview ────────────────────────────────────────

    public function test_bank_reviewer_of_correct_bank_gets_inline_preview_pdf(): void
    {
        $owner = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $reviewer = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $request = $this->makeRequest($owner, RequestStatus::BANK_REVIEW);

        $response = $this->actingAs($reviewer)
            ->get("/api/requests/{$request->id}/confirmation-request-preview");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->streamedContent());
    }

    public function test_bank_reviewer_of_wrong_bank_cannot_get_preview(): void
    {
        $owner = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $reviewer = $this->makeUser(UserRole::BANK_REVIEWER, $this->otherBank);
        $request = $this->makeRequest($owner, RequestStatus::BANK_REVIEW);

        $this->actingAs($reviewer)
            ->get("/api/requests/{$request->id}/confirmation-request-preview")
            ->assertForbidden();
    }

    public function test_committee_director_can_get_preview(): void
    {
        $owner = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $request = $this->makeRequest($owner, RequestStatus::EXECUTIVE_APPROVED);

        $response = $this->actingAs($director)
            ->get("/api/requests/{$request->id}/confirmation-request-preview");

        $response->assertOk();
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_cby_admin_can_get_preview(): void
    {
        $owner = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $admin = $this->makeUser(UserRole::CBY_ADMIN);
        $request = $this->makeRequest($owner, RequestStatus::SUBMITTED);

        $response = $this->actingAs($admin)
            ->get("/api/requests/{$request->id}/confirmation-request-preview");

        $response->assertOk();
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_data_entry_cannot_get_preview(): void
    {
        $user = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($user, RequestStatus::DRAFT);

        $this->actingAs($user)
            ->get("/api/requests/{$request->id}/confirmation-request-preview")
            ->assertForbidden();
    }

    public function test_committee_director_gets_validation_error_for_fx_template_on_wrong_status(): void
    {
        $owner = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $request = $this->makeRequest($owner, RequestStatus::DRAFT);

        $this->actingAs($director)
            ->get("/api/requests/{$request->id}/fx-confirmation-template")
            ->assertStatus(422);
    }

    private function makeBank(string $code): Bank
    {
        return Bank::query()->create([
            'name' => "بنك {$code}",
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function makeUser(UserRole $role, ?Bank $bank = null): User
    {
        static $counter = 0;
        $counter++;

        return User::query()->create([
            'name' => "User {$counter}",
            'email' => "template{$counter}@example.test",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }

    private function makeRequest(User $creator, RequestStatus $status): ImportRequest
    {
        app()->instance('workflow.transition.active', true);
        try {
            return ImportRequest::query()->create([
                'bank_id' => $this->bank->id,
                'merchant_id' => $this->merchant->id,
                'created_by' => $creator->id,
                'currency' => 'USD',
                'amount' => 10000.00,
                'yer_equivalent' => 2500000.00,
                'quantity' => '20 طن',
                'supplier_name' => 'Supplier Co.',
                'goods_description' => 'معدات صناعية',
                'goods_type' => 'معدات صناعية',
                'payment_terms' => 'تحويل مصرفي',
                'port_of_entry' => 'Aden Port',
                'arrival_port' => 'ميناء عدن',
                'status' => $status,
                'current_owner_role' => $status->isEditable() ? UserRole::DATA_ENTRY : UserRole::COMMITTEE_DIRECTOR,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }
}

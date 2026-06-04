<?php

namespace Tests\Feature\Customs;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\CustomsDeclaration;
use App\Models\ImportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomsPreviewTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;

    private Bank $otherBank;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('local');

        $this->bank = $this->makeBank('YCB');
        $this->otherBank = $this->makeBank('OTH');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

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
            'email' => "cprev{$counter}@example.test",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }

    private function makeRequest(Bank $bank, RequestStatus $status = RequestStatus::COMPLETED): ImportRequest
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $bank);
        app()->instance('workflow.transition.active', true);
        try {
            return ImportRequest::query()->create([
                'bank_id' => $bank->id,
                'created_by' => $creator->id,
                'currency' => 'USD',
                'amount' => 10000.00,
                'supplier_name' => 'Supplier Co.',
                'goods_description' => 'Industrial equipment',
                'port_of_entry' => 'Aden Port',
                'status' => $status,
                'current_owner_role' => UserRole::COMMITTEE_DIRECTOR,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    private function makeDeclaration(ImportRequest $request): CustomsDeclaration
    {
        $declarationNumber = 'CD-'.now()->format('Y').'-000001';
        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);

        return CustomsDeclaration::query()->create([
            'request_id' => $request->id,
            'declaration_number' => $declarationNumber,
            'issued_by' => $director->id,
            'issued_at' => now(),
            'pdf_path' => "customs/{$request->id}/{$declarationNumber}.pdf",
            'metadata' => [
                'reference_number' => $request->reference_number,
                'bank' => ['id' => $request->bank_id, 'name' => 'بنك YCB', 'code' => 'YCB'],
                'supplier_name' => $request->supplier_name,
                'amount' => (float) $request->amount,
                'currency' => $request->currency,
                'goods_description' => $request->goods_description,
                'port_of_entry' => $request->port_of_entry,
            ],
        ]);
    }

    // ─── AC8: COMMITTEE_DIRECTOR can preview ──────────────────────────────────

    public function test_committee_director_can_get_customs_preview_by_request_id(): void
    {
        $request = $this->makeRequest($this->bank);
        $declaration = $this->makeDeclaration($request);

        $actor = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $response = $this->actingAs($actor)
            ->getJson("/api/requests/{$request->id}/customs-preview")
            ->assertOk();

        $response->assertJsonPath('data.id', $declaration->id);
        $response->assertJsonPath('data.declaration_number', $declaration->declaration_number);
        $response->assertJsonPath('data.request.id', $request->id);
    }

    // ─── AC8: CBY_ADMIN can preview ───────────────────────────────────────────

    public function test_cby_admin_can_get_customs_preview_for_any_bank(): void
    {
        $request = $this->makeRequest($this->otherBank);
        $this->makeDeclaration($request);

        $actor = $this->makeUser(UserRole::CBY_ADMIN);
        $this->actingAs($actor)
            ->getJson("/api/requests/{$request->id}/customs-preview")
            ->assertOk();
    }

    // ─── AC8: BANK_REVIEWER own bank allowed ──────────────────────────────────

    public function test_bank_reviewer_can_get_customs_preview_for_own_bank(): void
    {
        $request = $this->makeRequest($this->bank);
        $this->makeDeclaration($request);

        $actor = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $this->actingAs($actor)
            ->getJson("/api/requests/{$request->id}/customs-preview")
            ->assertOk();
    }

    // ─── AC6: BANK_REVIEWER other bank denied ─────────────────────────────────

    public function test_bank_reviewer_cannot_get_customs_preview_for_other_bank(): void
    {
        $request = $this->makeRequest($this->otherBank);
        $this->makeDeclaration($request);

        $actor = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $this->actingAs($actor)
            ->getJson("/api/requests/{$request->id}/customs-preview")
            ->assertStatus(403);
    }

    // ─── AC6: denied roles ────────────────────────────────────────────────────

    public function test_data_entry_cannot_get_customs_preview(): void
    {
        $request = $this->makeRequest($this->bank);
        $this->makeDeclaration($request);

        $actor = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->actingAs($actor)
            ->getJson("/api/requests/{$request->id}/customs-preview")
            ->assertStatus(403);
    }

    public function test_swift_officer_cannot_get_customs_preview(): void
    {
        $request = $this->makeRequest($this->bank);
        $this->makeDeclaration($request);

        $actor = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);
        $this->actingAs($actor)
            ->getJson("/api/requests/{$request->id}/customs-preview")
            ->assertStatus(403);
    }

    public function test_support_committee_cannot_get_customs_preview(): void
    {
        $request = $this->makeRequest($this->bank);
        $this->makeDeclaration($request);

        $actor = $this->makeUser(UserRole::SUPPORT_COMMITTEE);
        $this->actingAs($actor)
            ->getJson("/api/requests/{$request->id}/customs-preview")
            ->assertStatus(403);
    }

    public function test_executive_member_cannot_get_customs_preview(): void
    {
        $request = $this->makeRequest($this->bank);
        $this->makeDeclaration($request);

        $actor = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $this->actingAs($actor)
            ->getJson("/api/requests/{$request->id}/customs-preview")
            ->assertStatus(403);
    }

    // ─── AC7: no declaration returns 404 ─────────────────────────────────────

    public function test_returns_404_when_request_has_no_customs_declaration(): void
    {
        $request = $this->makeRequest($this->bank, RequestStatus::EXECUTIVE_APPROVED);

        $actor = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $this->actingAs($actor)
            ->getJson("/api/requests/{$request->id}/customs-preview")
            ->assertNotFound();
    }

    public function test_denied_user_does_not_learn_whether_request_has_customs_declaration(): void
    {
        $request = $this->makeRequest($this->bank, RequestStatus::EXECUTIVE_APPROVED);

        $actor = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->actingAs($actor)
            ->getJson("/api/requests/{$request->id}/customs-preview")
            ->assertStatus(403);
    }

    // ─── Auth guard ───────────────────────────────────────────────────────────

    public function test_unauthenticated_request_returns_401(): void
    {
        $request = $this->makeRequest($this->bank);
        $this->makeDeclaration($request);

        $this->getJson("/api/requests/{$request->id}/customs-preview")
            ->assertStatus(401);
    }

    // ─── Response shape ───────────────────────────────────────────────────────

    public function test_preview_response_contains_required_fields(): void
    {
        $request = $this->makeRequest($this->bank);
        $this->makeDeclaration($request);

        $actor = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $response = $this->actingAs($actor)
            ->getJson("/api/requests/{$request->id}/customs-preview")
            ->assertOk();

        $response->assertJsonStructure([
            'data' => [
                'id',
                'declaration_number',
                'issued_at',
                'issued_by',
                'issuer',
                'metadata',
                'download_url',
                'request' => ['id', 'reference_number'],
            ],
        ]);
    }

    // ─── No data leak in error response ──────────────────────────────────────

    public function test_denied_response_does_not_leak_customs_data(): void
    {
        $request = $this->makeRequest($this->bank);
        $this->makeDeclaration($request);

        $actor = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $response = $this->actingAs($actor)
            ->getJson("/api/requests/{$request->id}/customs-preview");

        $response->assertStatus(403);
        $responseBody = $response->json();
        $this->assertArrayNotHasKey('declaration_number', $responseBody);
        $this->assertArrayNotHasKey('metadata', $responseBody);
        $this->assertArrayNotHasKey('supplier_name', $responseBody);
    }
}

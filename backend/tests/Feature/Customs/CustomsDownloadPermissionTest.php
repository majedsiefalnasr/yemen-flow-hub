<?php

namespace Tests\Feature\Customs;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\CustomsDeclaration;
use App\Models\ImportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Story 4.3: Customs Declaration Download Permission Matrix
 * Tests the role-based permission matrix for GET /api/customs/{id}/download
 */
class CustomsDownloadPermissionTest extends TestCase
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
            'email' => "cdperm{$counter}@example.test",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }

    private function makeRequest(Bank $bank): ImportRequest
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
                'status' => RequestStatus::COMPLETED,
                'current_owner_role' => UserRole::COMMITTEE_DIRECTOR,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    private function makeCustomsDeclaration(ImportRequest $request): CustomsDeclaration
    {
        $declarationNumber = 'CD-'.now()->format('Y').'-000001';
        $pdfPath = "customs/{$request->id}/{$declarationNumber}.pdf";
        Storage::disk('local')->put('private/'.$pdfPath, '%PDF-1.4 fake customs pdf');

        $director = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);

        return CustomsDeclaration::query()->create([
            'request_id' => $request->id,
            'declaration_number' => $declarationNumber,
            'issued_by' => $director->id,
            'issued_at' => now(),
            'pdf_path' => $pdfPath,
            'metadata' => ['supplier_name' => 'Supplier Co.'],
        ]);
    }

    // ─── COMMITTEE_DIRECTOR: all banks ────────────────────────────────────────

    public function test_committee_director_can_download_customs_for_own_bank(): void
    {
        $request = $this->makeRequest($this->bank);
        $declaration = $this->makeCustomsDeclaration($request);

        $actor = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $response = $this->actingAs($actor)->getJson("/api/customs/{$declaration->id}/download");

        $response->assertStatus(200);
    }

    public function test_committee_director_can_download_customs_for_other_bank(): void
    {
        $request = $this->makeRequest($this->otherBank);
        $declaration = $this->makeCustomsDeclaration($request);

        $actor = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $response = $this->actingAs($actor)->getJson("/api/customs/{$declaration->id}/download");

        $response->assertStatus(200);
    }

    // ─── CBY_ADMIN: all banks ─────────────────────────────────────────────────

    public function test_cby_admin_can_download_customs_for_any_bank(): void
    {
        $request = $this->makeRequest($this->otherBank);
        $declaration = $this->makeCustomsDeclaration($request);

        $actor = $this->makeUser(UserRole::CBY_ADMIN);
        $response = $this->actingAs($actor)->getJson("/api/customs/{$declaration->id}/download");

        $response->assertStatus(200);
    }

    // ─── BANK_REVIEWER: own bank only ─────────────────────────────────────────

    public function test_bank_reviewer_can_download_customs_for_own_bank(): void
    {
        $request = $this->makeRequest($this->bank);
        $declaration = $this->makeCustomsDeclaration($request);

        $actor = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $response = $this->actingAs($actor)->getJson("/api/customs/{$declaration->id}/download");

        $response->assertStatus(200);
    }

    public function test_bank_reviewer_cannot_download_customs_for_other_bank(): void
    {
        $request = $this->makeRequest($this->otherBank);
        $declaration = $this->makeCustomsDeclaration($request);

        $actor = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $response = $this->actingAs($actor)->getJson("/api/customs/{$declaration->id}/download");

        $response->assertStatus(403);
    }

    // ─── Denied roles ─────────────────────────────────────────────────────────

    public function test_data_entry_cannot_download_customs(): void
    {
        $request = $this->makeRequest($this->bank);
        $declaration = $this->makeCustomsDeclaration($request);

        $actor = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $response = $this->actingAs($actor)->getJson("/api/customs/{$declaration->id}/download");

        $response->assertStatus(403);
    }

    public function test_swift_officer_cannot_download_customs(): void
    {
        $request = $this->makeRequest($this->bank);
        $declaration = $this->makeCustomsDeclaration($request);

        $actor = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);
        $response = $this->actingAs($actor)->getJson("/api/customs/{$declaration->id}/download");

        $response->assertStatus(403);
    }

    public function test_support_committee_cannot_download_customs(): void
    {
        $request = $this->makeRequest($this->bank);
        $declaration = $this->makeCustomsDeclaration($request);

        $actor = $this->makeUser(UserRole::SUPPORT_COMMITTEE);
        $response = $this->actingAs($actor)->getJson("/api/customs/{$declaration->id}/download");

        $response->assertStatus(403);
    }

    public function test_executive_member_cannot_download_customs(): void
    {
        $request = $this->makeRequest($this->bank);
        $declaration = $this->makeCustomsDeclaration($request);

        $actor = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $response = $this->actingAs($actor)->getJson("/api/customs/{$declaration->id}/download");

        $response->assertStatus(403);
    }

    // ─── AC5: Audit log on customs download ───────────────────────────────────

    public function test_customs_download_creates_audit_log_with_customs_type(): void
    {
        $request = $this->makeRequest($this->bank);
        $declaration = $this->makeCustomsDeclaration($request);

        $actor = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $this->actingAs($actor)->getJson("/api/customs/{$declaration->id}/download");

        $log = AuditLog::query()
            ->where('user_id', $actor->id)
            ->where('action', 'DOCUMENT_DOWNLOADED')
            ->first();

        $this->assertNotNull($log);
        $metadata = $log->metadata;
        $this->assertEquals('CUSTOMS', $metadata['document_type']);
        $this->assertEquals($declaration->id, $metadata['document_id']);
        $this->assertEquals($request->id, $metadata['request_id']);
    }

    // ─── Auth guard ───────────────────────────────────────────────────────────

    public function test_unauthenticated_customs_download_returns_401(): void
    {
        $request = $this->makeRequest($this->bank);
        $declaration = $this->makeCustomsDeclaration($request);

        $response = $this->getJson("/api/customs/{$declaration->id}/download");

        $response->assertStatus(401);
    }
}

<?php

namespace Tests\Feature\Documents;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\RequestDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Story 4.3: Document Download Permission Matrix
 * Tests the role-based permission matrix for GET /api/documents/{id}/download
 */
class DocumentDownloadPermissionTest extends TestCase
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
            'email' => "user{$counter}@example.com",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }

    private function makeRequest(Bank $bank, User $creator, RequestStatus $status = RequestStatus::SUBMITTED): ImportRequest
    {
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
                'current_owner_role' => UserRole::BANK_REVIEWER,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    private function makeDocument(ImportRequest $request, User $uploader, string $type = 'REQUEST_DOC'): RequestDocument
    {
        $storedPath = "requests/{$request->id}/test.pdf";
        Storage::disk('local')->put('private/'.$storedPath, 'fake-pdf-content');

        return RequestDocument::query()->create([
            'request_id' => $request->id,
            'uploaded_by' => $uploader->id,
            'type' => $type,
            'original_filename' => 'test.pdf',
            'stored_path' => $storedPath,
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'checksum' => 'abc123',
        ]);
    }

    // ─── REQUEST_DOC: Bank-scoped roles (own bank only) ───────────────────────

    public function test_data_entry_can_download_request_doc_for_own_bank(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($this->bank, $creator);
        $document = $this->makeDocument($request, $creator);

        $response = $this->actingAs($creator)->getJson("/api/documents/{$document->id}/download");

        $response->assertStatus(200);
    }

    public function test_data_entry_cannot_download_request_doc_for_other_bank(): void
    {
        $otherCreator = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);
        $request = $this->makeRequest($this->otherBank, $otherCreator);
        $document = $this->makeDocument($request, $otherCreator);

        $actor = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $response = $this->actingAs($actor)->getJson("/api/documents/{$document->id}/download");

        $response->assertStatus(403);
    }

    public function test_bank_reviewer_can_download_request_doc_for_own_bank(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($this->bank, $creator);
        $document = $this->makeDocument($request, $creator);

        $actor = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $response = $this->actingAs($actor)->getJson("/api/documents/{$document->id}/download");

        $response->assertStatus(200);
    }

    public function test_bank_reviewer_cannot_download_request_doc_for_other_bank(): void
    {
        $otherCreator = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);
        $request = $this->makeRequest($this->otherBank, $otherCreator);
        $document = $this->makeDocument($request, $otherCreator);

        $actor = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $response = $this->actingAs($actor)->getJson("/api/documents/{$document->id}/download");

        $response->assertStatus(403);
    }

    public function test_swift_officer_can_download_request_doc_for_own_bank(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($this->bank, $creator);
        $document = $this->makeDocument($request, $creator);

        $actor = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);
        $response = $this->actingAs($actor)->getJson("/api/documents/{$document->id}/download");

        $response->assertStatus(200);
    }

    public function test_swift_officer_cannot_download_request_doc_for_other_bank(): void
    {
        $otherCreator = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);
        $request = $this->makeRequest($this->otherBank, $otherCreator);
        $document = $this->makeDocument($request, $otherCreator);

        $actor = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);
        $response = $this->actingAs($actor)->getJson("/api/documents/{$document->id}/download");

        $response->assertStatus(403);
    }

    // ─── REQUEST_DOC: CBY/committee roles (all banks) ─────────────────────────

    public function test_support_committee_can_download_request_doc_for_any_bank(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);
        $request = $this->makeRequest($this->otherBank, $creator);
        $document = $this->makeDocument($request, $creator);

        $actor = $this->makeUser(UserRole::SUPPORT_COMMITTEE);
        $response = $this->actingAs($actor)->getJson("/api/documents/{$document->id}/download");

        $response->assertStatus(200);
    }

    public function test_executive_member_can_download_request_doc_for_any_bank(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);
        $request = $this->makeRequest($this->otherBank, $creator);
        $document = $this->makeDocument($request, $creator);

        $actor = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $response = $this->actingAs($actor)->getJson("/api/documents/{$document->id}/download");

        $response->assertStatus(200);
    }

    public function test_committee_director_can_download_request_doc_for_any_bank(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);
        $request = $this->makeRequest($this->otherBank, $creator);
        $document = $this->makeDocument($request, $creator);

        $actor = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $response = $this->actingAs($actor)->getJson("/api/documents/{$document->id}/download");

        $response->assertStatus(200);
    }

    public function test_cby_admin_can_download_request_doc_for_any_bank(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);
        $request = $this->makeRequest($this->otherBank, $creator);
        $document = $this->makeDocument($request, $creator);

        $actor = $this->makeUser(UserRole::CBY_ADMIN);
        $response = $this->actingAs($actor)->getJson("/api/documents/{$document->id}/download");

        $response->assertStatus(200);
    }

    // ─── SWIFT: DATA_ENTRY denied ─────────────────────────────────────────────

    public function test_data_entry_cannot_download_swift_document(): void
    {
        $uploader = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);
        $request = $this->makeRequest($this->bank, $uploader);
        $document = $this->makeDocument($request, $uploader, 'SWIFT');

        $actor = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $response = $this->actingAs($actor)->getJson("/api/documents/{$document->id}/download");

        $response->assertStatus(403);
    }

    // ─── SWIFT: SUPPORT_COMMITTEE denied ──────────────────────────────────────

    public function test_support_committee_cannot_download_swift_document(): void
    {
        $uploader = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);
        $request = $this->makeRequest($this->bank, $uploader);
        $document = $this->makeDocument($request, $uploader, 'SWIFT');

        $actor = $this->makeUser(UserRole::SUPPORT_COMMITTEE);
        $response = $this->actingAs($actor)->getJson("/api/documents/{$document->id}/download");

        $response->assertStatus(403);
    }

    // ─── SWIFT: Bank-scoped roles (own bank only) ─────────────────────────────

    public function test_bank_reviewer_can_download_swift_for_own_bank(): void
    {
        $uploader = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);
        $request = $this->makeRequest($this->bank, $uploader);
        $document = $this->makeDocument($request, $uploader, 'SWIFT');

        $actor = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $response = $this->actingAs($actor)->getJson("/api/documents/{$document->id}/download");

        $response->assertStatus(200);
    }

    public function test_bank_reviewer_cannot_download_swift_for_other_bank(): void
    {
        $uploader = $this->makeUser(UserRole::SWIFT_OFFICER, $this->otherBank);
        $request = $this->makeRequest($this->otherBank, $uploader);
        $document = $this->makeDocument($request, $uploader, 'SWIFT');

        $actor = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $response = $this->actingAs($actor)->getJson("/api/documents/{$document->id}/download");

        $response->assertStatus(403);
    }

    public function test_swift_officer_can_download_swift_for_own_bank(): void
    {
        $uploader = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);
        $request = $this->makeRequest($this->bank, $uploader);
        $document = $this->makeDocument($request, $uploader, 'SWIFT');

        $actor = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);
        $response = $this->actingAs($actor)->getJson("/api/documents/{$document->id}/download");

        $response->assertStatus(200);
    }

    public function test_swift_officer_cannot_download_swift_for_other_bank(): void
    {
        $uploader = $this->makeUser(UserRole::SWIFT_OFFICER, $this->otherBank);
        $request = $this->makeRequest($this->otherBank, $uploader);
        $document = $this->makeDocument($request, $uploader, 'SWIFT');

        $actor = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);
        $response = $this->actingAs($actor)->getJson("/api/documents/{$document->id}/download");

        $response->assertStatus(403);
    }

    // ─── SWIFT: All-bank roles ────────────────────────────────────────────────

    public function test_executive_member_can_download_swift_for_any_bank(): void
    {
        $uploader = $this->makeUser(UserRole::SWIFT_OFFICER, $this->otherBank);
        $request = $this->makeRequest($this->otherBank, $uploader);
        $document = $this->makeDocument($request, $uploader, 'SWIFT');

        $actor = $this->makeUser(UserRole::EXECUTIVE_MEMBER);
        $response = $this->actingAs($actor)->getJson("/api/documents/{$document->id}/download");

        $response->assertStatus(200);
    }

    public function test_committee_director_can_download_swift_for_any_bank(): void
    {
        $uploader = $this->makeUser(UserRole::SWIFT_OFFICER, $this->otherBank);
        $request = $this->makeRequest($this->otherBank, $uploader);
        $document = $this->makeDocument($request, $uploader, 'SWIFT');

        $actor = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $response = $this->actingAs($actor)->getJson("/api/documents/{$document->id}/download");

        $response->assertStatus(200);
    }

    public function test_cby_admin_can_download_swift_for_any_bank(): void
    {
        $uploader = $this->makeUser(UserRole::SWIFT_OFFICER, $this->otherBank);
        $request = $this->makeRequest($this->otherBank, $uploader);
        $document = $this->makeDocument($request, $uploader, 'SWIFT');

        $actor = $this->makeUser(UserRole::CBY_ADMIN);
        $response = $this->actingAs($actor)->getJson("/api/documents/{$document->id}/download");

        $response->assertStatus(200);
    }

    // ─── AC5: Audit log on successful download ────────────────────────────────

    public function test_download_creates_audit_log_with_document_type(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($this->bank, $creator);
        $document = $this->makeDocument($request, $creator, 'REQUEST_DOC');

        $actor = $this->makeUser(UserRole::BANK_REVIEWER, $this->bank);
        $this->actingAs($actor)->getJson("/api/documents/{$document->id}/download");

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $actor->id,
            'action' => 'DOCUMENT_DOWNLOADED',
        ]);

        $log = AuditLog::query()
            ->where('user_id', $actor->id)
            ->where('action', 'DOCUMENT_DOWNLOADED')
            ->first();

        $this->assertNotNull($log);
        $metadata = $log->metadata;
        $this->assertEquals($document->id, $metadata['document_id']);
        $this->assertEquals('REQUEST_DOC', $metadata['document_type']);
        $this->assertEquals($request->id, $metadata['request_id']);
    }

    public function test_swift_download_creates_audit_log_with_swift_type(): void
    {
        $uploader = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);
        $request = $this->makeRequest($this->bank, $uploader);
        $document = $this->makeDocument($request, $uploader, 'SWIFT');

        $actor = $this->makeUser(UserRole::COMMITTEE_DIRECTOR);
        $this->actingAs($actor)->getJson("/api/documents/{$document->id}/download");

        $log = AuditLog::query()
            ->where('user_id', $actor->id)
            ->where('action', 'DOCUMENT_DOWNLOADED')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('SWIFT', $log->metadata['document_type']);
    }

    // ─── Auth guard ───────────────────────────────────────────────────────────

    public function test_unauthenticated_download_returns_401(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($this->bank, $creator);
        $document = $this->makeDocument($request, $creator);

        $response = $this->getJson("/api/documents/{$document->id}/download");

        $response->assertStatus(401);
    }
}

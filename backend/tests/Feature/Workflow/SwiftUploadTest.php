<?php

namespace Tests\Feature\Workflow;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\ImportRequest;
use App\Models\RequestDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SwiftUploadTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;

    private Bank $otherBank;

    private User $swiftOfficer;

    private User $otherSwiftOfficer;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('local');
        $this->seedPermissions();

        $this->bank = $this->makeBank('YCB');
        $this->otherBank = $this->makeBank('OTH');
        $this->swiftOfficer = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);
        $this->otherSwiftOfficer = $this->makeUser(UserRole::SWIFT_OFFICER, $this->otherBank);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function seedPermissions(): void
    {
        $permissionId = DB::table('permissions')->insertGetId([
            'slug' => 'swift.upload',
            'name_ar' => 'رفع وثيقة السويفت',
            'name_en' => 'Upload SWIFT document',
            'group' => 'workflow',
        ]);

        DB::table('role_permissions')->insert([
            'permission_id' => $permissionId,
            'role' => UserRole::SWIFT_OFFICER->value,
        ]);
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
            'email' => "user{$counter}@example.com",
            'password' => Hash::make('password'),
            'role' => $role->value,
            'bank_id' => $bank?->id,
            'is_active' => true,
        ]);
    }

    private function makeRequest(Bank $bank, User $creator, RequestStatus $status): ImportRequest
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
                'current_owner_role' => UserRole::SWIFT_OFFICER,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    private function makePdf(string $name = 'swift.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 200, 'application/pdf');
    }

    // ─── AC-2: Success path ────────────────────────────────────────────────────

    public function test_swift_officer_can_upload_pdf_to_waiting_for_swift_request(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($this->bank, $creator, RequestStatus::WAITING_FOR_SWIFT);

        $response = $this->actingAs($this->swiftOfficer)
            ->postJson("/api/workflow/{$request->id}/swift-upload", [
                'file' => $this->makePdf(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_swift_upload_creates_request_document_with_swift_type(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($this->bank, $creator, RequestStatus::WAITING_FOR_SWIFT);

        $this->actingAs($this->swiftOfficer)
            ->postJson("/api/workflow/{$request->id}/swift-upload", [
                'file' => $this->makePdf(),
            ]);

        $this->assertDatabaseHas('request_documents', [
            'request_id' => $request->id,
            'uploaded_by' => $this->swiftOfficer->id,
            'type' => 'SWIFT',
        ]);
    }

    public function test_swift_upload_records_swift_uploaded_by_and_at_on_request(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($this->bank, $creator, RequestStatus::WAITING_FOR_SWIFT);

        $this->actingAs($this->swiftOfficer)
            ->postJson("/api/workflow/{$request->id}/swift-upload", [
                'file' => $this->makePdf(),
            ]);

        $request->refresh();
        $this->assertSame($this->swiftOfficer->id, $request->swift_uploaded_by);
        $this->assertNotNull($request->swift_uploaded_at);
    }

    public function test_swift_upload_transitions_to_waiting_for_voting_open(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($this->bank, $creator, RequestStatus::WAITING_FOR_SWIFT);

        $this->actingAs($this->swiftOfficer)
            ->postJson("/api/workflow/{$request->id}/swift-upload", [
                'file' => $this->makePdf(),
            ]);

        $request->refresh();
        // Auto-chains: WAITING_FOR_SWIFT → SWIFT_UPLOADED → WAITING_FOR_VOTING_OPEN
        $this->assertSame(RequestStatus::WAITING_FOR_VOTING_OPEN, $request->status);
    }

    public function test_swift_upload_creates_stage_history_records(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($this->bank, $creator, RequestStatus::WAITING_FOR_SWIFT);

        $this->actingAs($this->swiftOfficer)
            ->postJson("/api/workflow/{$request->id}/swift-upload", [
                'file' => $this->makePdf(),
            ]);

        $this->assertDatabaseHas('request_stage_history', [
            'request_id' => $request->id,
            'action' => 'swift_upload',
        ]);
    }

    public function test_swift_upload_creates_audit_log(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($this->bank, $creator, RequestStatus::WAITING_FOR_SWIFT);

        $this->actingAs($this->swiftOfficer)
            ->postJson("/api/workflow/{$request->id}/swift-upload", [
                'file' => $this->makePdf(),
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->swiftOfficer->id,
            'user_role' => UserRole::SWIFT_OFFICER->value,
        ]);
    }

    // ─── AC-3: Immutability — duplicate upload blocked ─────────────────────────

    public function test_second_swift_upload_returns_422_immutable(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($this->bank, $creator, RequestStatus::WAITING_FOR_SWIFT);

        // First upload — succeeds
        $this->actingAs($this->swiftOfficer)
            ->postJson("/api/workflow/{$request->id}/swift-upload", [
                'file' => $this->makePdf(),
            ]);

        // Second upload on same request (now WAITING_FOR_VOTING_OPEN) — blocked by status check
        $this->actingAs($this->swiftOfficer)
            ->postJson("/api/workflow/{$request->id}/swift-upload", [
                'file' => $this->makePdf('swift2.pdf'),
            ])
            ->assertStatus(422);
    }

    public function test_upload_when_swift_doc_already_exists_returns_422(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($this->bank, $creator, RequestStatus::WAITING_FOR_SWIFT);

        // Manually inject a SWIFT document to simulate immutability check
        RequestDocument::query()->create([
            'request_id' => $request->id,
            'uploaded_by' => $this->swiftOfficer->id,
            'type' => 'SWIFT',
            'original_filename' => 'swift.pdf',
            'stored_path' => "swift/{$request->id}/swift.pdf",
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'checksum' => 'abc123',
        ]);

        $this->actingAs($this->swiftOfficer)
            ->postJson("/api/workflow/{$request->id}/swift-upload", [
                'file' => $this->makePdf(),
            ])
            ->assertStatus(422);
    }

    // ─── Wrong status ──────────────────────────────────────────────────────────

    public function test_upload_on_wrong_status_returns_422(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($this->bank, $creator, RequestStatus::SUPPORT_APPROVED);

        $this->actingAs($this->swiftOfficer)
            ->postJson("/api/workflow/{$request->id}/swift-upload", [
                'file' => $this->makePdf(),
            ])
            ->assertStatus(422);
    }

    public function test_upload_on_draft_request_returns_422(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($this->bank, $creator, RequestStatus::DRAFT);

        $this->actingAs($this->swiftOfficer)
            ->postJson("/api/workflow/{$request->id}/swift-upload", [
                'file' => $this->makePdf(),
            ])
            ->assertStatus(422);
    }

    // ─── Cross-bank org-scope enforcement ─────────────────────────────────────

    public function test_swift_officer_cannot_upload_to_other_bank_request(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);
        $request = $this->makeRequest($this->otherBank, $creator, RequestStatus::WAITING_FOR_SWIFT);

        $this->actingAs($this->swiftOfficer)
            ->postJson("/api/workflow/{$request->id}/swift-upload", [
                'file' => $this->makePdf(),
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('request_documents', [
            'request_id' => $request->id,
            'type' => 'SWIFT',
        ]);
    }

    // ─── Request validation ────────────────────────────────────────────────────

    public function test_upload_non_pdf_returns_422(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($this->bank, $creator, RequestStatus::WAITING_FOR_SWIFT);

        $this->actingAs($this->swiftOfficer)
            ->postJson("/api/workflow/{$request->id}/swift-upload", [
                'file' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['file']]);
    }

    public function test_upload_missing_file_returns_422(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($this->bank, $creator, RequestStatus::WAITING_FOR_SWIFT);

        $this->actingAs($this->swiftOfficer)
            ->postJson("/api/workflow/{$request->id}/swift-upload", [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['file']]);
    }

    // ─── Authentication ────────────────────────────────────────────────────────

    public function test_unauthenticated_upload_returns_401(): void
    {
        $creator = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $request = $this->makeRequest($this->bank, $creator, RequestStatus::WAITING_FOR_SWIFT);

        $this->postJson("/api/workflow/{$request->id}/swift-upload", [
            'file' => $this->makePdf(),
        ])
            ->assertStatus(401);
    }
}

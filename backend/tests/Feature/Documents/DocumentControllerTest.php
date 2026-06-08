<?php

namespace Tests\Feature\Documents;

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

class DocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;

    private Bank $otherBank;

    private User $dataEntry;

    private User $otherDataEntry;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('local');
        $this->seedPermissions();

        $this->bank = $this->makeBank('YCB');
        $this->otherBank = $this->makeBank('OTH');
        $this->dataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->bank);
        $this->otherDataEntry = $this->makeUser(UserRole::DATA_ENTRY, $this->otherBank);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function seedPermissions(): void
    {
        $requestCreatePermissionId = DB::table('permissions')->insertGetId([
            'slug' => 'request.create',
            'name_ar' => 'إنشاء طلب',
            'name_en' => 'Create request',
            'group' => 'requests',
        ]);

        $swiftUploadPermissionId = DB::table('permissions')->insertGetId([
            'slug' => 'swift.upload',
            'name_ar' => 'رفع وثيقة السويفت',
            'name_en' => 'Upload SWIFT document',
            'group' => 'workflow',
        ]);

        DB::table('role_permissions')->insert([
            ['permission_id' => $requestCreatePermissionId, 'role' => UserRole::DATA_ENTRY->value],
            ['permission_id' => $swiftUploadPermissionId, 'role' => UserRole::SWIFT_OFFICER->value],
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

    private function makeRequest(Bank $bank, User $creator, RequestStatus $status = RequestStatus::DRAFT): ImportRequest
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
                'current_owner_role' => UserRole::DATA_ENTRY,
            ]);
        } finally {
            app()->offsetUnset('workflow.transition.active');
        }
    }

    private function makePdf(string $name = 'test.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 100, 'application/pdf');
    }

    private function makePdfWithOriginalName(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'yfh-upload-');
        file_put_contents($path, '%PDF-1.4 test');

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    private function makeDocument(ImportRequest $request, User $uploader): RequestDocument
    {
        $storedPath = "requests/{$request->id}/test.pdf";
        Storage::disk('local')->put('private/'.$storedPath, 'fake-pdf-content');

        return RequestDocument::query()->create([
            'request_id' => $request->id,
            'uploaded_by' => $uploader->id,
            'type' => 'REQUEST_DOC',
            'original_filename' => 'test.pdf',
            'stored_path' => $storedPath,
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'checksum' => 'abc123',
        ]);
    }

    // ─── AC-1: POST /api/documents/upload ─────────────────────────────────────

    public function test_data_entry_can_upload_pdf_to_draft_request(): void
    {
        $importRequest = $this->makeRequest($this->bank, $this->dataEntry);

        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/documents/upload', [
                'request_id' => $importRequest->id,
                'file' => $this->makePdf(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'type',
                    'original_filename',
                    'mime_type',
                    'size_bytes',
                    'checksum',
                    'uploaded_at',
                ],
            ])
            ->assertJsonMissingPath('data.download_url');

        $this->assertDatabaseHas('request_documents', [
            'request_id' => $importRequest->id,
            'uploaded_by' => $this->dataEntry->id,
            'type' => 'REQUEST_DOC',
            'mime_type' => 'application/pdf',
        ]);
    }

    public function test_upload_persists_sub_type_and_exposes_title(): void
    {
        $importRequest = $this->makeRequest($this->bank, $this->dataEntry);

        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/documents/upload', [
                'request_id' => $importRequest->id,
                'file' => $this->makePdf(),
                'sub_type' => 'tax_card',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.document_sub_type', 'tax_card')
            ->assertJsonPath('data.title', 'البطاقة الضريبية');

        $this->assertDatabaseHas('request_documents', [
            'request_id' => $importRequest->id,
            'type' => 'REQUEST_DOC',
            'document_sub_type' => 'tax_card',
        ]);
    }

    public function test_upload_accepts_fixed_national_committee_document_slot_sub_type(): void
    {
        $importRequest = $this->makeRequest($this->bank, $this->dataEntry);

        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/documents/upload', [
                'request_id' => $importRequest->id,
                'file' => $this->makePdf(),
                'sub_type' => 'certificate_of_origin',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.document_sub_type', 'certificate_of_origin')
            ->assertJsonPath('data.title', 'شهادة المنشأ');

        $this->assertDatabaseHas('request_documents', [
            'request_id' => $importRequest->id,
            'type' => 'REQUEST_DOC',
            'document_sub_type' => 'certificate_of_origin',
        ]);
    }

    public function test_upload_rejects_unknown_sub_type(): void
    {
        $importRequest = $this->makeRequest($this->bank, $this->dataEntry);

        $this->actingAs($this->dataEntry)
            ->postJson('/api/documents/upload', [
                'request_id' => $importRequest->id,
                'file' => $this->makePdf(),
                'sub_type' => 'not_a_real_slot',
            ])
            ->assertStatus(422);
    }

    public function test_confirmation_request_upload_gets_confirmation_sub_type(): void
    {
        $importRequest = $this->makeRequest($this->bank, $this->dataEntry);

        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/documents/upload', [
                'request_id' => $importRequest->id,
                'file' => $this->makePdf(),
                'confirmation_request' => '1',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.document_sub_type', 'confirmation_request')
            ->assertJsonPath('data.title', 'طلب وثيقة التأكيد');
    }

    public function test_upload_to_draft_rejected_internal_request_succeeds(): void
    {
        $importRequest = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::DRAFT_REJECTED_INTERNAL);

        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/documents/upload', [
                'request_id' => $importRequest->id,
                'file' => $this->makePdf(),
            ]);

        $response->assertStatus(201);
    }

    public function test_upload_stores_checksum_in_record(): void
    {
        $importRequest = $this->makeRequest($this->bank, $this->dataEntry);

        $this->actingAs($this->dataEntry)
            ->postJson('/api/documents/upload', [
                'request_id' => $importRequest->id,
                'file' => $this->makePdf(),
            ]);

        $document = RequestDocument::query()
            ->where('request_id', $importRequest->id)
            ->first();

        $this->assertNotNull($document->checksum);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $document->checksum);
    }

    public function test_upload_sanitizes_original_filename_without_changing_storage_or_checksum(): void
    {
        $importRequest = $this->makeRequest($this->bank, $this->dataEntry);
        $file = $this->makePdf("../../evil\r\nname<script>.pdf");
        $expectedChecksum = hash_file('sha256', $file->getRealPath());

        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/documents/upload', [
                'request_id' => $importRequest->id,
                'file' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.original_filename', 'evil name_script_.pdf');

        $document = RequestDocument::query()
            ->where('request_id', $importRequest->id)
            ->firstOrFail();

        $this->assertSame('evil name_script_.pdf', $document->original_filename);
        $this->assertStringNotContainsString('..', $document->original_filename);
        $this->assertStringNotContainsString('/', $document->original_filename);
        $this->assertStringNotContainsString('\\', $document->original_filename);
        $this->assertStringNotContainsString("\r", $document->original_filename);
        $this->assertStringNotContainsString("\n", $document->original_filename);
        $this->assertMatchesRegularExpression("#^requests/{$importRequest->id}/[0-9a-f-]{36}\.pdf$#", $document->stored_path);
        $this->assertSame($expectedChecksum, $document->checksum);
    }

    public function test_upload_preserves_arabic_filename_and_truncates_without_breaking_utf8(): void
    {
        $importRequest = $this->makeRequest($this->bank, $this->dataEntry);
        $file = $this->makePdfWithOriginalName(str_repeat('فاتورة', 70).'.pdf');

        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/documents/upload', [
                'request_id' => $importRequest->id,
                'file' => $file,
            ]);

        $response->assertStatus(201);

        $document = RequestDocument::query()
            ->where('request_id', $importRequest->id)
            ->firstOrFail();

        $this->assertTrue(mb_check_encoding($document->original_filename, 'UTF-8'));
        $this->assertLessThanOrEqual(255, mb_strlen($document->original_filename));
        $this->assertStringStartsWith('فاتورة', $document->original_filename);
        $this->assertStringEndsWith('.pdf', $document->original_filename);
    }

    public function test_upload_non_pdf_returns_422_validation_error(): void
    {
        $importRequest = $this->makeRequest($this->bank, $this->dataEntry);
        $jpegFile = UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/documents/upload', [
                'request_id' => $importRequest->id,
                'file' => $jpegFile,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['file']]);
    }

    public function test_upload_png_returns_422_validation_error(): void
    {
        $importRequest = $this->makeRequest($this->bank, $this->dataEntry);
        $pngFile = UploadedFile::fake()->create('image.png', 100, 'image/png');

        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/documents/upload', [
                'request_id' => $importRequest->id,
                'file' => $pngFile,
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['file']]);
    }

    public function test_upload_missing_file_returns_422(): void
    {
        $importRequest = $this->makeRequest($this->bank, $this->dataEntry);

        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/documents/upload', [
                'request_id' => $importRequest->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['file']]);
    }

    public function test_upload_missing_request_id_is_rejected(): void
    {
        // Authorization runs before field validation and fails closed: without a
        // request_id the uploader cannot be scoped to a request, so the request is
        // forbidden (403) rather than surfacing field-validation details (422).
        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/documents/upload', [
                'file' => $this->makePdf(),
            ]);

        $response->assertForbidden();
    }

    // ─── AC-3: WORKFLOW_LOCKED_STATE for upload on non-editable request ────────

    public function test_upload_on_submitted_request_returns_422_workflow_locked_state(): void
    {
        $importRequest = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::SUBMITTED);

        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/documents/upload', [
                'request_id' => $importRequest->id,
                'file' => $this->makePdf(),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'WORKFLOW_LOCKED_STATE');
    }

    public function test_upload_on_bank_approved_request_returns_422_workflow_locked_state(): void
    {
        $importRequest = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_APPROVED);

        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/documents/upload', [
                'request_id' => $importRequest->id,
                'file' => $this->makePdf(),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'WORKFLOW_LOCKED_STATE');
    }

    // ─── AC-2: DELETE /api/documents/{id} ─────────────────────────────────────

    public function test_data_entry_can_delete_document_from_draft_request(): void
    {
        $importRequest = $this->makeRequest($this->bank, $this->dataEntry);
        $document = $this->makeDocument($importRequest, $this->dataEntry);
        $storedPath = $document->stored_path;

        $response = $this->actingAs($this->dataEntry)
            ->deleteJson("/api/documents/{$document->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('request_documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing('private/'.$storedPath);
    }

    // ─── AC-3: WORKFLOW_LOCKED_STATE for delete on non-editable request ────────

    public function test_delete_on_submitted_request_returns_422_workflow_locked_state(): void
    {
        $importRequest = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::SUBMITTED);
        $document = $this->makeDocument($importRequest, $this->dataEntry);

        $response = $this->actingAs($this->dataEntry)
            ->deleteJson("/api/documents/{$document->id}");

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'WORKFLOW_LOCKED_STATE');
    }

    // ─── Org scope enforcement ────────────────────────────────────────────────

    public function test_data_entry_cannot_upload_to_other_bank_request(): void
    {
        $otherRequest = $this->makeRequest($this->otherBank, $this->otherDataEntry);

        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/documents/upload', [
                'request_id' => $otherRequest->id,
                'file' => $this->makePdf(),
            ]);

        // Cross-bank upload blocked at policy layer (uploadDocuments policy checks bank_id match)
        $response->assertStatus(403);

        $this->assertDatabaseMissing('request_documents', ['request_id' => $otherRequest->id]);
    }

    public function test_unauthenticated_upload_returns_401(): void
    {
        $importRequest = $this->makeRequest($this->bank, $this->dataEntry);

        $response = $this->postJson('/api/documents/upload', [
            'request_id' => $importRequest->id,
            'file' => $this->makePdf(),
        ]);

        $response->assertStatus(401);
    }

    public function test_document_upload_route_is_rate_limited(): void
    {
        $importRequest = $this->makeRequest($this->bank, $this->dataEntry);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($this->dataEntry)
                ->postJson('/api/documents/upload', [
                    'request_id' => $importRequest->id,
                    'file' => $this->makePdf("document-{$i}.pdf"),
                ])
                ->assertStatus(201);
        }

        $this->actingAs($this->dataEntry)
            ->postJson('/api/documents/upload', [
                'request_id' => $importRequest->id,
                'file' => $this->makePdf('document-11.pdf'),
            ])
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Too many requests. Please try again later.');
    }

    public function test_deprecated_request_document_upload_route_is_rate_limited(): void
    {
        $importRequest = $this->makeRequest($this->bank, $this->dataEntry);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($this->dataEntry)
                ->postJson("/api/requests/{$importRequest->id}/documents", [
                    'file' => $this->makePdf("legacy-document-{$i}.pdf"),
                ])
                ->assertStatus(201);
        }

        $this->actingAs($this->dataEntry)
            ->postJson("/api/requests/{$importRequest->id}/documents", [
                'file' => $this->makePdf('legacy-document-11.pdf'),
            ])
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Too many requests. Please try again later.');
    }

    public function test_swift_upload_route_is_rate_limited(): void
    {
        $swiftOfficer = $this->makeUser(UserRole::SWIFT_OFFICER, $this->bank);
        $request = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::WAITING_FOR_SWIFT);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($swiftOfficer)
                ->postJson("/api/workflow/{$request->id}/swift-upload", [])
                ->assertStatus(422);
        }

        $this->actingAs($swiftOfficer)
            ->postJson("/api/workflow/{$request->id}/swift-upload", [])
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Too many requests. Please try again later.');
    }

    // ─── AC-3: Additional locked-status coverage (Patch K) ───────────────────

    public function test_upload_on_bank_review_request_returns_422_workflow_locked_state(): void
    {
        $importRequest = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);

        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/documents/upload', [
                'request_id' => $importRequest->id,
                'file' => $this->makePdf(),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'WORKFLOW_LOCKED_STATE');
    }

    public function test_upload_on_executive_voting_open_request_returns_422_workflow_locked_state(): void
    {
        $importRequest = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::EXECUTIVE_VOTING_OPEN);

        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/documents/upload', [
                'request_id' => $importRequest->id,
                'file' => $this->makePdf(),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'WORKFLOW_LOCKED_STATE');
    }

    public function test_upload_on_completed_request_returns_422_workflow_locked_state(): void
    {
        $importRequest = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::COMPLETED);

        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/documents/upload', [
                'request_id' => $importRequest->id,
                'file' => $this->makePdf(),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'WORKFLOW_LOCKED_STATE');
    }

    public function test_delete_on_bank_review_request_returns_422_workflow_locked_state(): void
    {
        $importRequest = $this->makeRequest($this->bank, $this->dataEntry, RequestStatus::BANK_REVIEW);
        $document = $this->makeDocument($importRequest, $this->dataEntry);

        $response = $this->actingAs($this->dataEntry)
            ->deleteJson("/api/documents/{$document->id}");

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'WORKFLOW_LOCKED_STATE');
    }

    public function test_deprecated_endpoint_still_uploads_pdf_to_draft_request(): void
    {
        $importRequest = $this->makeRequest($this->bank, $this->dataEntry);

        $response = $this->actingAs($this->dataEntry)
            ->postJson("/api/requests/{$importRequest->id}/documents", [
                'file' => $this->makePdf(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('request_documents', [
            'request_id' => $importRequest->id,
            'uploaded_by' => $this->dataEntry->id,
        ]);
    }

    public function test_deprecated_endpoint_blocks_cross_bank_upload(): void
    {
        $otherRequest = $this->makeRequest($this->otherBank, $this->otherDataEntry);

        $response = $this->actingAs($this->dataEntry)
            ->postJson("/api/requests/{$otherRequest->id}/documents", [
                'file' => $this->makePdf(),
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('request_documents', ['request_id' => $otherRequest->id]);
    }
}

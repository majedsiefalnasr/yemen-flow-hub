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
        $permissionId = DB::table('permissions')->insertGetId([
            'slug' => 'request.create',
            'name_ar' => 'إنشاء طلب',
            'name_en' => 'Create request',
            'group' => 'requests',
        ]);

        DB::table('role_permissions')->insert([
            'permission_id' => $permissionId,
            'role' => UserRole::DATA_ENTRY->value,
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

    private function makeDocument(ImportRequest $request, User $uploader): RequestDocument
    {
        return RequestDocument::query()->create([
            'request_id' => $request->id,
            'uploaded_by' => $uploader->id,
            'type' => 'REQUEST_DOC',
            'original_filename' => 'test.pdf',
            'stored_path' => "requests/{$request->id}/test.pdf",
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
                    'download_url',
                ],
            ]);

        $this->assertDatabaseHas('request_documents', [
            'request_id' => $importRequest->id,
            'uploaded_by' => $this->dataEntry->id,
            'type' => 'REQUEST_DOC',
            'mime_type' => 'application/pdf',
        ]);
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

    public function test_upload_missing_request_id_returns_422(): void
    {
        $response = $this->actingAs($this->dataEntry)
            ->postJson('/api/documents/upload', [
                'file' => $this->makePdf(),
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['request_id']]);
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

        $response = $this->actingAs($this->dataEntry)
            ->deleteJson("/api/documents/{$document->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('request_documents', ['id' => $document->id]);
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

        // Cross-bank upload is blocked (422 from DocumentException — unauthorized bank scope)
        $response->assertStatus(422)
            ->assertJsonPath('success', false);

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
}

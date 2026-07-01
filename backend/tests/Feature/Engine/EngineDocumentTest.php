<?php

namespace Tests\Feature\Engine;

use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\StagePermission;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EngineDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $outsider;

    private WorkflowVersion $version;

    private WorkflowStage $stage;

    private Bank $bankA;

    private Bank $bankB;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $this->bankA = Bank::create(['name' => 'Bank A', 'code' => 'BKA', 'is_active' => true]);
        $this->bankB = Bank::create(['name' => 'Bank B', 'code' => 'BKB', 'is_active' => true]);

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@doc.test',
            'password' => bcrypt('pass'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $this->bankA->id,
            'is_active' => true,
        ]);

        $this->outsider = User::create([
            'name' => 'Outsider',
            'email' => 'outsider@doc.test',
            'password' => bcrypt('pass'),
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $this->bankB->id,
            'is_active' => true,
        ]);

        $def = WorkflowDefinition::create(['code' => 'DOC_WF', 'name' => 'Doc WF', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'published_at' => now(),
            'version' => 1,
        ]);

        $this->stage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'ENTRY',
            'name' => 'Entry',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'version' => 1,
        ]);

        StagePermission::create([
            'stage_id' => $this->stage->id,
            'user_id' => $this->owner->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Owner Exec',
            'version' => 1,
        ]);
    }

    private function makeRequest(): EngineRequest
    {
        return EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $this->stage->id,
            'reference' => 'ENG-DOC-'.uniqid(),
            'status' => 'ACTIVE',
            'created_by' => $this->owner->id,
            'bank_id' => $this->bankA->id,
            'data' => [],
            'version' => 1,
        ]);
    }

    private function uploadDoc(EngineRequest $request, string $name = 'file.pdf'): int
    {
        $file = UploadedFile::fake()->create($name, 100, 'application/pdf');
        $response = $this->actingAs($this->owner)->postJson(
            "/api/v1/engine-requests/{$request->id}/documents",
            ['file' => $file]
        );
        $response->assertCreated();

        return $response->json('data.id');
    }

    public function test_owner_can_list_documents(): void
    {
        $request = $this->makeRequest();
        $this->uploadDoc($request);

        $this->actingAs($this->owner)
            ->getJson("/api/v1/engine-requests/{$request->id}/documents")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_cross_org_access_to_document_list_is_blocked(): void
    {
        $request = $this->makeRequest();

        // The outsider belongs to bankB; the request belongs to bankA.
        // The policy's view gate (forUser scope) blocks cross-bank access.
        $this->actingAs($this->outsider)
            ->getJson("/api/v1/engine-requests/{$request->id}/documents")
            ->assertForbidden();
    }

    public function test_download_endpoint_returns_200_for_owner(): void
    {
        $request = $this->makeRequest();
        $docId = $this->uploadDoc($request, 'download_me.pdf');

        $this->actingAs($this->owner)
            ->get("/api/v1/engine-requests/{$request->id}/documents/{$docId}/download")
            ->assertOk();
    }

    public function test_download_is_blocked_for_cross_org_user(): void
    {
        $request = $this->makeRequest();
        $docId = $this->uploadDoc($request, 'blocked_download.pdf');

        $this->actingAs($this->outsider)
            ->get("/api/v1/engine-requests/{$request->id}/documents/{$docId}/download")
            ->assertForbidden();
    }

    public function test_upload_creates_audit_log_entry(): void
    {
        $request = $this->makeRequest();
        $this->uploadDoc($request, 'audit_doc.pdf');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'DOCUMENT_UPLOADED',
            'workflow_instance_id' => $request->id,
        ]);
    }

    public function test_delete_document_before_stage_change_succeeds(): void
    {
        $request = $this->makeRequest();
        $docId = $this->uploadDoc($request, 'deletable.pdf');

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/engine-requests/{$request->id}/documents/{$docId}")
            ->assertOk();

        $this->assertSoftDeleted('engine_request_documents', ['id' => $docId]);
    }

    public function test_document_for_wrong_request_returns_404(): void
    {
        $requestA = $this->makeRequest();
        $requestB = $this->makeRequest();
        $docId = $this->uploadDoc($requestA);

        // Document belongs to requestA but we query under requestB
        $this->actingAs($this->owner)
            ->get("/api/v1/engine-requests/{$requestB->id}/documents/{$docId}/download")
            ->assertNotFound();
    }
}

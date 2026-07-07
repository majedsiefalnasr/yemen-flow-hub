<?php

namespace Tests\Feature\Engine;

use App\Enums\DocumentScanStatus;
use App\Enums\DocumentStatus;
use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\EngineRequestDocument;
use App\Models\Organization;
use App\Models\Role;
use App\Models\StagePermission;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ScreenPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
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
        $this->seed([GovernanceSeeder::class, ScreenPermissionSeeder::class]);

        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $entryRole = Role::where('code', 'intake')->firstOrFail();
        $entryTeam = Team::where('code', 'entry')->firstOrFail();

        $this->bankA = Bank::create(['name' => 'Bank A', 'code' => 'BKA', 'is_active' => true, 'organization_id' => $bankOrg->id]);
        $this->bankB = Bank::create(['name' => 'Bank B', 'code' => 'BKB', 'is_active' => true, 'organization_id' => $bankOrg->id]);

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@doc.test',
            'password' => bcrypt('pass'),
            'bank_id' => $this->bankA->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $this->owner->teams()->attach($entryTeam);
        $this->owner->roles()->attach($entryRole);

        $this->outsider = User::create([
            'name' => 'Outsider',
            'email' => 'outsider@doc.test',
            'password' => bcrypt('pass'),
            'bank_id' => $this->bankB->id,
            'organization_id' => $bankOrg->id,
            'is_active' => true,
        ]);
        $this->outsider->teams()->attach($entryTeam);
        $this->outsider->roles()->attach($entryRole);

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
            'organization_id' => $bankOrg->id,
            'role_id' => $entryRole->id,
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

    public function test_upload_sets_scan_status_clean_when_enforcement_deferred(): void
    {
        Config::set('workflow.document_scan_enforced', false);
        $request = $this->makeRequest();
        $docId = $this->uploadDoc($request, 'scan_deferred.pdf');

        $this->assertDatabaseHas('engine_request_documents', [
            'id' => $docId,
            'scan_status' => DocumentScanStatus::Clean->value,
        ]);
    }

    public function test_upload_sets_scan_status_pending_when_enforcement_enabled(): void
    {
        Config::set('workflow.document_scan_enforced', true);
        $request = $this->makeRequest();
        $docId = $this->uploadDoc($request, 'scan_pending.pdf');

        $this->assertDatabaseHas('engine_request_documents', [
            'id' => $docId,
            'scan_status' => DocumentScanStatus::Pending->value,
        ]);
    }

    public function test_download_blocked_when_scan_pending_and_enforcement_enabled(): void
    {
        Config::set('workflow.document_scan_enforced', true);
        $request = $this->makeRequest();
        $docId = $this->uploadDoc($request, 'pending_scan.pdf');

        $this->actingAs($this->owner)
            ->get("/api/v1/engine-requests/{$request->id}/documents/{$docId}/download")
            ->assertForbidden()
            ->assertJsonPath('error_code', 'DOCUMENT_SCAN_BLOCKED')
            ->assertJsonPath('scan_status', DocumentScanStatus::Pending->value);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'DOCUMENT_SCAN_BLOCKED',
            'workflow_instance_id' => $request->id,
        ]);
    }

    public function test_download_allowed_when_scan_clean_and_enforcement_enabled(): void
    {
        Config::set('workflow.document_scan_enforced', true);
        $request = $this->makeRequest();
        $docId = $this->uploadDoc($request, 'clean_scan.pdf');

        EngineRequestDocument::query()->whereKey($docId)->update([
            'scan_status' => DocumentScanStatus::Clean->value,
        ]);

        $this->actingAs($this->owner)
            ->get("/api/v1/engine-requests/{$request->id}/documents/{$docId}/download")
            ->assertOk();
    }

    public function test_download_blocked_on_checksum_mismatch(): void
    {
        $request = $this->makeRequest();
        $docId = $this->uploadDoc($request, 'integrity.pdf');

        $document = EngineRequestDocument::query()->findOrFail($docId);
        Storage::disk('private')->put($document->path, 'tampered-bytes');

        $this->actingAs($this->owner)
            ->get("/api/v1/engine-requests/{$request->id}/documents/{$docId}/download")
            ->assertForbidden()
            ->assertJsonPath('error_code', 'DOCUMENT_CHECKSUM_MISMATCH');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'DOCUMENT_CHECKSUM_MISMATCH',
            'workflow_instance_id' => $request->id,
        ]);
    }

    public function test_replace_marks_old_document_superseded_and_links_replacement(): void
    {
        $request = $this->makeRequest();
        $docId = $this->uploadDoc($request, 'original.pdf');

        $replacementFile = UploadedFile::fake()->create('replacement.pdf', 120, 'application/pdf');
        $response = $this->actingAs($this->owner)->postJson(
            "/api/v1/engine-requests/{$request->id}/documents/{$docId}/replace",
            ['file' => $replacementFile, 'reason' => 'Corrected invoice']
        );

        $response->assertCreated()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.version', 2);

        $replacementId = $response->json('data.id');

        $this->assertDatabaseHas('engine_request_documents', [
            'id' => $docId,
            'status' => DocumentStatus::Superseded->value,
            'superseded_by' => $replacementId,
        ]);

        $this->assertDatabaseHas('engine_request_documents', [
            'id' => $replacementId,
            'status' => DocumentStatus::Active->value,
            'version' => 2,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'DOCUMENT_REPLACED',
            'workflow_instance_id' => $request->id,
        ]);
    }

    public function test_replace_works_for_prior_stage_document_when_delete_is_locked(): void
    {
        $bankOrg = Organization::where('code', 'commercial_banks')->firstOrFail();
        $entryRole = Role::where('code', 'intake')->firstOrFail();

        $priorStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'REVIEW',
            'name' => 'Review',
            'sort_order' => 2,
            'is_initial' => false,
            'is_final' => false,
            'version' => 1,
        ]);

        StagePermission::create([
            'stage_id' => $priorStage->id,
            'organization_id' => $bankOrg->id,
            'role_id' => $entryRole->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'Owner Exec Review',
            'version' => 1,
        ]);

        $request = $this->makeRequest();
        $docId = $this->uploadDoc($request, 'prior_stage.pdf');

        $request->update(['current_stage_id' => $priorStage->id]);

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/engine-requests/{$request->id}/documents/{$docId}")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'DOCUMENT_LOCKED');

        $replacementFile = UploadedFile::fake()->create('updated.pdf', 100, 'application/pdf');
        $this->actingAs($this->owner)->postJson(
            "/api/v1/engine-requests/{$request->id}/documents/{$docId}/replace",
            ['file' => $replacementFile]
        )->assertCreated();

        $this->assertDatabaseHas('engine_request_documents', [
            'id' => $docId,
            'status' => DocumentStatus::Superseded->value,
        ]);
    }

    public function test_cannot_replace_already_superseded_document(): void
    {
        $request = $this->makeRequest();
        $docId = $this->uploadDoc($request, 'first.pdf');

        $firstReplacement = UploadedFile::fake()->create('second.pdf', 100, 'application/pdf');
        $this->actingAs($this->owner)->postJson(
            "/api/v1/engine-requests/{$request->id}/documents/{$docId}/replace",
            ['file' => $firstReplacement]
        )->assertCreated();

        $secondReplacement = UploadedFile::fake()->create('third.pdf', 100, 'application/pdf');
        $this->actingAs($this->owner)->postJson(
            "/api/v1/engine-requests/{$request->id}/documents/{$docId}/replace",
            ['file' => $secondReplacement]
        )
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'DOCUMENT_NOT_REPLACEABLE');
    }

    public function test_list_includes_superseded_documents_with_status(): void
    {
        $request = $this->makeRequest();
        $docId = $this->uploadDoc($request, 'listed.pdf');

        $replacementFile = UploadedFile::fake()->create('listed_replacement.pdf', 100, 'application/pdf');
        $this->actingAs($this->owner)->postJson(
            "/api/v1/engine-requests/{$request->id}/documents/{$docId}/replace",
            ['file' => $replacementFile]
        )->assertCreated();

        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/engine-requests/{$request->id}/documents")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $statuses = collect($response->json('data'))->pluck('status')->all();
        $this->assertContains(DocumentStatus::Active->value, $statuses);
        $this->assertContains(DocumentStatus::Superseded->value, $statuses);
    }
}

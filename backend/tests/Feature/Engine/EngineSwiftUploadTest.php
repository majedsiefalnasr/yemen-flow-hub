<?php

namespace Tests\Feature\Engine;

use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\StagePermission;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EngineSwiftUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $swiftOfficer;

    private User $wrongRoleUser;

    private WorkflowVersion $version;

    private WorkflowStage $swiftStage;

    private Bank $bank;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $this->bank = Bank::create(['name' => 'SWIFT Bank', 'code' => 'SWB', 'is_active' => true]);

        $this->swiftOfficer = User::create([
            'name' => 'SWIFT Officer',
            'email' => 'swift@swift.test',
            'password' => bcrypt('pass'),
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);

        $this->wrongRoleUser = User::create([
            'name' => 'Wrong Role',
            'email' => 'wrong@swift.test',
            'password' => bcrypt('pass'),
            'bank_id' => $this->bank->id,
            'is_active' => true,
        ]);

        $def = WorkflowDefinition::create(['code' => 'SWIFT_WF', 'name' => 'SWIFT WF', 'is_active' => true]);
        $this->version = WorkflowVersion::create([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'state' => 'PUBLISHED',
            'published_at' => now(),
            'version' => 1,
        ]);

        $this->swiftStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'WAITING_FOR_SWIFT',
            'name' => 'Waiting for SWIFT',
            'sort_order' => 1,
            'is_initial' => true,
            'is_final' => false,
            'version' => 1,
        ]);

        $swiftUploadedStage = WorkflowStage::create([
            'workflow_version_id' => $this->version->id,
            'code' => 'SWIFT_UPLOADED',
            'name' => 'SWIFT Uploaded',
            'sort_order' => 2,
            'is_initial' => false,
            'is_final' => false,
            'version' => 1,
        ]);

        // Grant EXECUTE on SWIFT stage to the SWIFT officer (user-scoped)
        StagePermission::create([
            'stage_id' => $this->swiftStage->id,
            'user_id' => $this->swiftOfficer->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'SWIFT Upload',
            'version' => 1,
        ]);

        $action = WorkflowAction::create([
            'code' => 'UPLOAD_SWIFT',
            'name' => 'Upload SWIFT',
            'kind' => 'APPROVE',
            'is_active' => true,
            'version' => 1,
        ]);

        WorkflowTransition::create([
            'workflow_version_id' => $this->version->id,
            'from_stage_id' => $this->swiftStage->id,
            'to_stage_id' => $swiftUploadedStage->id,
            'action_id' => $action->id,
            'requires_comment' => false,
            'version' => 1,
        ]);
    }

    private function makeSwiftRequest(): EngineRequest
    {
        return EngineRequest::create([
            'workflow_version_id' => $this->version->id,
            'current_stage_id' => $this->swiftStage->id,
            'reference' => 'ENG-SWIFT-'.uniqid(),
            'status' => 'ACTIVE',
            'created_by' => $this->swiftOfficer->id,
            'bank_id' => $this->bank->id,
            'data' => [],
            'version' => 1,
        ]);
    }

    public function test_swift_officer_can_upload_pdf_document(): void
    {
        $request = $this->makeSwiftRequest();
        $file = UploadedFile::fake()->create('swift_doc.pdf', 200, 'application/pdf');

        $response = $this->actingAs($this->swiftOfficer)->postJson(
            "/api/v1/engine-requests/{$request->id}/documents",
            ['file' => $file]
        );

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('engine_request_documents', [
            'request_id' => $request->id,
            'original_name' => 'swift_doc.pdf',
            'uploaded_by' => $this->swiftOfficer->id,
        ]);
    }

    public function test_user_without_execute_cannot_upload(): void
    {
        $request = $this->makeSwiftRequest();
        $file = UploadedFile::fake()->create('blocked.pdf', 100, 'application/pdf');

        $this->actingAs($this->wrongRoleUser)->postJson(
            "/api/v1/engine-requests/{$request->id}/documents",
            ['file' => $file]
        )->assertStatus(403);
    }

    public function test_non_pdf_upload_is_rejected(): void
    {
        $request = $this->makeSwiftRequest();
        $file = UploadedFile::fake()->create('not_a_pdf.txt', 50, 'text/plain');

        $this->actingAs($this->swiftOfficer)->postJson(
            "/api/v1/engine-requests/{$request->id}/documents",
            ['file' => $file]
        )->assertStatus(422);
    }

    public function test_uploaded_document_appears_in_document_list(): void
    {
        $request = $this->makeSwiftRequest();
        $file = UploadedFile::fake()->create('swift_list.pdf', 100, 'application/pdf');

        $this->actingAs($this->swiftOfficer)->postJson(
            "/api/v1/engine-requests/{$request->id}/documents",
            ['file' => $file]
        )->assertCreated();

        $this->actingAs($this->swiftOfficer)
            ->getJson("/api/v1/engine-requests/{$request->id}/documents")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_transition_moves_request_from_waiting_for_swift_to_swift_uploaded(): void
    {
        $request = $this->makeSwiftRequest();

        $transition = WorkflowTransition::where('from_stage_id', $this->swiftStage->id)->first();
        $response = $this->actingAs($this->swiftOfficer)->postJson(
            "/api/v1/engine-requests/{$request->id}/actions",
            [
                'transition_id' => $transition->id,
                'data' => [],
                'version' => $request->version,
            ]
        );

        $response->assertOk()
            ->assertJsonPath('data.current_stage.code', 'SWIFT_UPLOADED');
    }
}

<?php

namespace Tests\Feature\Engine;

use App\Enums\AuditAction;
use App\Enums\StageAccessLevel;
use App\Enums\StageSemanticRole;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CustomsDeclaration;
use App\Models\EngineRequest;
use App\Models\StagePermission;
use App\Models\User;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\Support\EngineWorkflowFactory;
use Tests\TestCase;

class EngineCustomsSignedFxTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernance();
    }

    private function grantFxExecute(EngineRequest $request, User $user): WorkflowStage
    {
        $fxStage = WorkflowStage::create([
            'workflow_version_id' => $request->workflow_version_id,
            'code' => 'FX_CONFIRM',
            'name' => 'FX Confirmation',
            'sort_order' => 99,
            'is_initial' => false,
            'is_final' => false,
            'semantic_role' => StageSemanticRole::FX_CONFIRMATION,
            'version' => 1,
        ]);

        StagePermission::create([
            'stage_id' => $fxStage->id,
            'user_id' => $user->id,
            'access_level' => StageAccessLevel::EXECUTE,
            'display_label' => 'FX Execute',
            'version' => 1,
        ]);

        return $fxStage;
    }

    private function grantFxView(EngineRequest $request, User $user, WorkflowStage $fxStage): void
    {
        StagePermission::create([
            'stage_id' => $fxStage->id,
            'user_id' => $user->id,
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'FX View',
            'version' => 1,
        ]);
    }

    private function seedDeclaration(): array
    {
        ['request' => $request, 'executor' => $executor] = EngineWorkflowFactory::seedClaimStageWithTransition();

        $id = DB::table('customs_declarations')->insertGetId([
            'engine_request_id' => $request->id,
            'declaration_number' => 'FX-TEST-001',
            'issued_by' => $executor->id,
            'issued_at' => now()->toDateTimeString(),
            'pdf_path' => 'fx-confirmation/test.pdf',
            'metadata' => json_encode([]),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $declaration = CustomsDeclaration::findOrFail($id);

        return ['request' => $request, 'declaration' => $declaration, 'executor' => $executor];
    }

    public function test_fx_stage_executor_can_upload_signed_fx_doc_for_engine_declaration(): void
    {
        Storage::fake('local');

        ['request' => $request, 'declaration' => $declaration] = $this->seedDeclaration();
        $uploader = $this->assignGovernanceIdentity(
            User::factory()->create(['role' => UserRole::COMMITTEE_DIRECTOR]),
            UserRole::COMMITTEE_DIRECTOR
        );
        $this->grantFxExecute($request, $uploader);

        $file = UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf');

        $this->actingAs($uploader)
            ->postJson("/api/v1/engine-requests/{$request->id}/fx-confirmation-signed", [
                'signed_document' => $file,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $fresh = $declaration->fresh();
        $this->assertNotNull($fresh->signed_fx_doc_path);
        $this->assertNotNull($fresh->signed_fx_doc_uploaded_at);
        $this->assertSame($uploader->id, $fresh->signed_fx_doc_uploaded_by);

        $this->assertTrue(
            AuditLog::query()
                ->where('action', AuditAction::FX_CONFIRMATION_UPLOADED->value)
                ->where('subject_id', $request->id)
                ->exists()
        );
    }

    public function test_non_executor_cannot_upload_signed_fx_doc(): void
    {
        Storage::fake('local');

        ['request' => $request] = $this->seedDeclaration();
        $reviewer = $this->assignGovernanceIdentity(
            User::factory()->create(['role' => UserRole::BANK_REVIEWER]),
            UserRole::BANK_REVIEWER
        );
        $fxStage = WorkflowStage::create([
            'workflow_version_id' => $request->workflow_version_id,
            'code' => 'FX_CONFIRM',
            'name' => 'FX Confirmation',
            'sort_order' => 99,
            'is_initial' => false,
            'is_final' => false,
            'semantic_role' => StageSemanticRole::FX_CONFIRMATION,
            'version' => 1,
        ]);
        $this->grantFxView($request, $reviewer, $fxStage);

        $file = UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf');

        $this->actingAs($reviewer)
            ->postJson("/api/v1/engine-requests/{$request->id}/fx-confirmation-signed", [
                'signed_document' => $file,
            ])
            ->assertStatus(403);
    }

    public function test_upload_replaces_previous_signed_doc(): void
    {
        Storage::fake('local');

        ['request' => $request, 'declaration' => $declaration] = $this->seedDeclaration();
        $uploader = $this->assignGovernanceIdentity(
            User::factory()->create(['role' => UserRole::COMMITTEE_DIRECTOR]),
            UserRole::COMMITTEE_DIRECTOR
        );
        $this->grantFxExecute($request, $uploader);

        DB::table('customs_declarations')->where('id', $declaration->id)->update([
            'signed_fx_doc_path' => 'fx-confirmation/engine/'.$request->id.'/old_signed.pdf',
            'signed_fx_doc_uploaded_at' => now()->toDateTimeString(),
            'signed_fx_doc_uploaded_by' => $uploader->id,
            'updated_at' => now()->toDateTimeString(),
        ]);

        $file = UploadedFile::fake()->create('signed_new.pdf', 100, 'application/pdf');

        $this->actingAs($uploader)
            ->postJson("/api/v1/engine-requests/{$request->id}/fx-confirmation-signed", [
                'signed_document' => $file,
            ])
            ->assertOk();

        $fresh = $declaration->fresh();
        $this->assertNotNull($fresh->signed_fx_doc_path);
        $this->assertStringNotContainsString('old_signed.pdf', $fresh->signed_fx_doc_path);
    }

    public function test_stage_permission_is_authoritative_for_fx_upload_even_when_legacy_role_disagrees(): void
    {
        Storage::fake('local');

        ['request' => $request, 'declaration' => $declaration] = $this->seedDeclaration();

        $staleEnumUser = $this->assignGovernanceIdentity(
            User::factory()->create(['role' => UserRole::COMMITTEE_DIRECTOR]),
            UserRole::BANK_REVIEWER
        );

        $file = UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf');

        $this->actingAs($staleEnumUser)
            ->postJson("/api/v1/engine-requests/{$request->id}/fx-confirmation-signed", [
                'signed_document' => $file,
            ])
            ->assertStatus(403);

        $this->assertNull($declaration->fresh()->signed_fx_doc_path);

        $fxExecutor = $this->assignGovernanceIdentity(
            User::factory()->create(['role' => UserRole::BANK_REVIEWER]),
            UserRole::EXECUTIVE_MEMBER
        );
        $this->grantFxExecute($request, $fxExecutor);

        $file2 = UploadedFile::fake()->create('signed2.pdf', 100, 'application/pdf');

        $this->actingAs($fxExecutor)
            ->postJson("/api/v1/engine-requests/{$request->id}/fx-confirmation-signed", [
                'signed_document' => $file2,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $fresh = $declaration->fresh();
        $this->assertNotNull($fresh->signed_fx_doc_path);
        $this->assertSame($fxExecutor->id, $fresh->signed_fx_doc_uploaded_by);
    }

    public function test_upload_returns_404_if_no_declaration_exists_for_engine_request(): void
    {
        Storage::fake('local');

        ['request' => $request] = EngineWorkflowFactory::seedClaimStageWithTransition();
        $uploader = $this->assignGovernanceIdentity(
            User::factory()->create(['role' => UserRole::COMMITTEE_DIRECTOR]),
            UserRole::COMMITTEE_DIRECTOR
        );
        $this->grantFxExecute($request, $uploader);

        $file = UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf');

        $this->actingAs($uploader)
            ->postJson("/api/v1/engine-requests/{$request->id}/fx-confirmation-signed", [
                'signed_document' => $file,
            ])
            ->assertStatus(404);
    }
}

<?php

namespace Tests\Feature\Engine;

use App\Enums\AuditAction;
use App\Enums\StageAccessLevel;
use App\Enums\StageSemanticRole;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\CustomsDeclaration;
use App\Models\EngineRequest;
use App\Models\Organization;
use App\Models\StagePermission;
use App\Models\User;
use App\Models\WorkflowStage;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ImportFinancingWorkflowSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\WorkflowActionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    /**
     * Regression test for WP-8 F-12 seeder gap: the canonical
     * ImportFinancingWorkflowSeeder must grant commercial_banks a VIEW
     * StagePermission on FX_CONFIRM so bank users can download their own
     * bank's signed FX deliverable end-to-end through the real download
     * route, under the actual seeded workflow (not an ad hoc test stage).
     * DataScope (bank_id) must still block a user from a different bank.
     */
    public function test_bank_user_downloads_own_bank_signed_fx_under_seeded_workflow_permissions(): void
    {
        Storage::fake('local');

        $this->seed([
            GovernanceSeeder::class,
            ReferenceDataSeeder::class,
            WorkflowActionSeeder::class,
            ImportFinancingWorkflowSeeder::class,
        ]);

        $ownBank = Bank::query()->create(['name' => 'Own Bank', 'code' => 'OWN'.Str::random(4), 'is_active' => true]);
        $otherBank = Bank::query()->create(['name' => 'Other Bank', 'code' => 'OTH'.Str::random(4), 'is_active' => true]);

        ['request' => $request, 'declaration' => $declaration] = $this->seedDeclarationOnSeededFxStage($ownBank->id);

        $bankOrganization = Organization::query()->where('code', 'commercial_banks')->firstOrFail();

        $sameBankUser = $this->assignGovernanceIdentity(
            User::factory()->create(['role' => UserRole::BANK_REVIEWER, 'bank_id' => $ownBank->id, 'organization_id' => $bankOrganization->id]),
            UserRole::BANK_REVIEWER
        );
        $otherBankUser = $this->assignGovernanceIdentity(
            User::factory()->create(['role' => UserRole::BANK_REVIEWER, 'bank_id' => $otherBank->id, 'organization_id' => $bankOrganization->id]),
            UserRole::BANK_REVIEWER
        );

        Storage::disk('local')->put('private/'.$declaration->signed_fx_doc_path, 'signed-fx-pdf-bytes');

        $this->actingAs($sameBankUser)
            ->get("/api/v1/engine-requests/{$request->id}/customs-declaration/signed-fx-download")
            ->assertOk();

        $this->actingAs($otherBankUser)
            ->get("/api/v1/engine-requests/{$request->id}/customs-declaration/signed-fx-download")
            ->assertStatus(403);
    }

    /**
     * Builds an EngineRequest on the real seeded ImportFinancingWorkflowSeeder
     * workflow, parked on its FX_CONFIRM stage, scoped to $bankId, with a
     * CustomsDeclaration carrying a signed FX document path.
     *
     * @return array{request: EngineRequest, declaration: CustomsDeclaration}
     */
    private function seedDeclarationOnSeededFxStage(int $bankId): array
    {
        $fxStage = WorkflowStage::query()
            ->whereHas('workflowVersion.definition', fn ($q) => $q->where('code', 'IMPORT_FINANCING'))
            ->where('code', 'FX_CONFIRM')
            ->firstOrFail();

        $creator = User::factory()->create(['bank_id' => $bankId]);

        $request = EngineRequest::create([
            'workflow_version_id' => $fxStage->workflow_version_id,
            'current_stage_id' => $fxStage->id,
            'bank_id' => $bankId,
            'reference' => 'ENG-SEEDED-FX-'.Str::random(10),
            'status' => 'ACTIVE',
            'created_by' => $creator->id,
            'version' => 1,
        ]);

        $id = DB::table('customs_declarations')->insertGetId([
            'engine_request_id' => $request->id,
            'declaration_number' => 'FX-SEEDED-'.Str::random(6),
            'issued_by' => $creator->id,
            'issued_at' => now()->toDateTimeString(),
            'pdf_path' => 'fx-confirmation/seeded.pdf',
            'signed_fx_doc_path' => 'fx-confirmation/engine/'.$request->id.'/signed.pdf',
            'signed_fx_doc_uploaded_at' => now()->toDateTimeString(),
            'signed_fx_doc_uploaded_by' => $creator->id,
            'metadata' => json_encode([]),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        return ['request' => $request, 'declaration' => CustomsDeclaration::findOrFail($id)];
    }
}

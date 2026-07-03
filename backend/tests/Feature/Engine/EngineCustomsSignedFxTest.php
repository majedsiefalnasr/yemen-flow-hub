<?php

namespace Tests\Feature\Engine;

use App\Enums\AuditAction;
use App\Enums\StageAccessLevel;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CustomsDeclaration;
use App\Models\EngineRequest;
use App\Models\StagePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\Support\EngineWorkflowFactory;
use Tests\TestCase;

class EngineCustomsSignedFxTest extends TestCase
{
    use RefreshDatabase;
    use AssignsGovernanceIdentity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernance();
    }

    private function grantViewToUser(EngineRequest $request, User $user): void
    {
        StagePermission::create([
            'stage_id' => $request->current_stage_id,
            'user_id' => $user->id,
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'View',
            'version' => 1,
        ]);
    }

    private function seedDeclaration(): array
    {
        ['request' => $request, 'executor' => $executor] = EngineWorkflowFactory::seedClaimStageWithTransition();

        // Use DB::table to bypass CustomsDeclaration::booted() immutability guard
        // (which throws on update() for issued declarations).
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

    public function test_director_can_upload_signed_fx_doc_for_engine_declaration(): void
    {
        Storage::fake('local');

        ['request' => $request, 'declaration' => $declaration] = $this->seedDeclaration();
        $director = $this->assignGovernanceIdentity(
            User::factory()->create(['role' => UserRole::COMMITTEE_DIRECTOR]),
            UserRole::COMMITTEE_DIRECTOR
        );
        $this->grantViewToUser($request, $director);

        $file = UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf');

        $this->actingAs($director)
            ->postJson("/api/v1/engine-requests/{$request->id}/fx-confirmation-signed", [
                'signed_document' => $file,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $fresh = $declaration->fresh();
        $this->assertNotNull($fresh->signed_fx_doc_path);
        $this->assertNotNull($fresh->signed_fx_doc_uploaded_at);
        $this->assertSame($director->id, $fresh->signed_fx_doc_uploaded_by);

        $this->assertTrue(
            AuditLog::query()
                ->where('action', AuditAction::FX_CONFIRMATION_UPLOADED->value)
                ->where('subject_id', $request->id)
                ->exists()
        );
    }

    public function test_non_director_cannot_upload_signed_fx_doc(): void
    {
        Storage::fake('local');

        ['request' => $request] = $this->seedDeclaration();
        $reviewer = $this->assignGovernanceIdentity(
            User::factory()->create(['role' => UserRole::BANK_REVIEWER]),
            UserRole::BANK_REVIEWER
        );
        $this->grantViewToUser($request, $reviewer);

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
        $director = $this->assignGovernanceIdentity(
            User::factory()->create(['role' => UserRole::COMMITTEE_DIRECTOR]),
            UserRole::COMMITTEE_DIRECTOR
        );
        $this->grantViewToUser($request, $director);

        // Seed an existing signed doc path.
        DB::table('customs_declarations')->where('id', $declaration->id)->update([
            'signed_fx_doc_path' => 'fx-confirmation/engine/'.$request->id.'/old_signed.pdf',
            'signed_fx_doc_uploaded_at' => now()->toDateTimeString(),
            'signed_fx_doc_uploaded_by' => $director->id,
            'updated_at' => now()->toDateTimeString(),
        ]);

        $file = UploadedFile::fake()->create('signed_new.pdf', 100, 'application/pdf');

        $this->actingAs($director)
            ->postJson("/api/v1/engine-requests/{$request->id}/fx-confirmation-signed", [
                'signed_document' => $file,
            ])
            ->assertOk();

        $fresh = $declaration->fresh();
        $this->assertNotNull($fresh->signed_fx_doc_path);
        $this->assertStringNotContainsString('old_signed.pdf', $fresh->signed_fx_doc_path);
    }

    public function test_pivot_role_is_authoritative_for_director_upload_even_when_legacy_enum_disagrees(): void
    {
        Storage::fake('local');

        ['request' => $request, 'declaration' => $declaration] = $this->seedDeclaration();

        // Desync case 1: legacy enum says COMMITTEE_DIRECTOR, but the governance
        // pivot role is NOT committee_director. The pivot must be authoritative,
        // so this upload must be rejected.
        $staleEnumUser = $this->assignGovernanceIdentity(
            User::factory()->create(['role' => UserRole::COMMITTEE_DIRECTOR]),
            UserRole::BANK_REVIEWER
        );
        $this->grantViewToUser($request, $staleEnumUser);

        $file = UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf');

        $this->actingAs($staleEnumUser)
            ->postJson("/api/v1/engine-requests/{$request->id}/fx-confirmation-signed", [
                'signed_document' => $file,
            ])
            ->assertStatus(403);

        $this->assertNull($declaration->fresh()->signed_fx_doc_path);

        // Desync case 2: legacy enum does NOT say COMMITTEE_DIRECTOR, but the
        // governance pivot role IS committee_director. The pivot must be
        // authoritative, so this upload must succeed.
        $realDirector = $this->assignGovernanceIdentity(
            User::factory()->create(['role' => UserRole::BANK_REVIEWER]),
            UserRole::COMMITTEE_DIRECTOR
        );
        $this->grantViewToUser($request, $realDirector);

        $file2 = UploadedFile::fake()->create('signed2.pdf', 100, 'application/pdf');

        $this->actingAs($realDirector)
            ->postJson("/api/v1/engine-requests/{$request->id}/fx-confirmation-signed", [
                'signed_document' => $file2,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $fresh = $declaration->fresh();
        $this->assertNotNull($fresh->signed_fx_doc_path);
        $this->assertSame($realDirector->id, $fresh->signed_fx_doc_uploaded_by);
    }

    public function test_upload_returns_404_if_no_declaration_exists_for_engine_request(): void
    {
        Storage::fake('local');

        ['request' => $request] = EngineWorkflowFactory::seedClaimStageWithTransition();
        $director = $this->assignGovernanceIdentity(
            User::factory()->create(['role' => UserRole::COMMITTEE_DIRECTOR]),
            UserRole::COMMITTEE_DIRECTOR
        );
        $this->grantViewToUser($request, $director);

        $file = UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf');

        $this->actingAs($director)
            ->postJson("/api/v1/engine-requests/{$request->id}/fx-confirmation-signed", [
                'signed_document' => $file,
            ])
            ->assertStatus(404);
    }
}

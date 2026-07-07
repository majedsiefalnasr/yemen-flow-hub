<?php

namespace Tests\Unit\Policies;

use App\Enums\StageAccessLevel;
use App\Enums\StageSemanticRole;
use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\CustomsDeclaration;
use App\Models\EngineRequest;
use App\Models\StagePermission;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Policies\CustomsDeclarationPolicy;
use App\Services\Customs\FxConfirmationAuthorizationService;
use App\Services\Workflow\StagePermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssignsGovernanceIdentity;
use Tests\Support\EngineWorkflowFactory;
use Tests\TestCase;

class CustomsDeclarationPolicyTest extends TestCase
{
    use AssignsGovernanceIdentity;
    use RefreshDatabase;

    private CustomsDeclarationPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new CustomsDeclarationPolicy(new FxConfirmationAuthorizationService(new StagePermissionResolver));
        $this->seedGovernance();
    }

    public function test_fx_stage_viewer_can_download_engine_issued_declaration(): void
    {
        $bank = $this->makeBank();
        $engineRequest = $this->makeEngineRequest($bank->id);
        $declaration = $this->makeEngineDeclaration($engineRequest);
        $viewer = $this->assignGovernanceIdentity(
            User::factory()->create([]),
            UserRole::COMMITTEE_DIRECTOR
        );
        $this->grantFxView($engineRequest, $viewer);

        $this->assertTrue($this->policy->download($viewer, $declaration));
    }

    public function test_bank_reviewer_of_same_bank_can_download_engine_issued_declaration(): void
    {
        $bank = $this->makeBank();
        $otherBank = $this->makeBank();
        $engineRequest = $this->makeEngineRequest($bank->id);
        $declaration = $this->makeEngineDeclaration($engineRequest);

        $sameReviewer = $this->assignGovernanceIdentity(
            User::factory()->create([, 'bank_id' => $bank->id]),
            UserRole::BANK_REVIEWER
        );
        $otherReviewer = $this->assignGovernanceIdentity(
            User::factory()->create([, 'bank_id' => $otherBank->id]),
            UserRole::BANK_REVIEWER
        );
        $this->grantFxView($engineRequest, $sameReviewer);
        $this->grantFxView($engineRequest, $otherReviewer);

        $this->assertTrue($this->policy->download($sameReviewer, $declaration));
        $this->assertFalse($this->policy->download($otherReviewer, $declaration));
    }

    public function test_fx_stage_viewer_can_download_signed_fx_for_engine_declaration(): void
    {
        $bank = $this->makeBank();
        $engineRequest = $this->makeEngineRequest($bank->id);
        $declaration = $this->makeEngineDeclaration($engineRequest);

        $viewer = $this->assignGovernanceIdentity(
            User::factory()->create([]),
            UserRole::COMMITTEE_DIRECTOR
        );
        $this->grantFxView($engineRequest, $viewer);

        $this->assertTrue($this->policy->downloadSignedFx($viewer, $declaration));
    }

    public function test_bank_user_of_same_bank_can_download_signed_fx(): void
    {
        $bank = $this->makeBank();
        $otherBank = $this->makeBank();
        $engineRequest = $this->makeEngineRequest($bank->id);
        $declaration = $this->makeEngineDeclaration($engineRequest);

        $sameDataEntry = $this->assignGovernanceIdentity(
            User::factory()->create([, 'bank_id' => $bank->id]),
            UserRole::DATA_ENTRY
        );
        $otherDataEntry = $this->assignGovernanceIdentity(
            User::factory()->create([, 'bank_id' => $otherBank->id]),
            UserRole::DATA_ENTRY
        );
        $this->grantFxView($engineRequest, $sameDataEntry);
        $this->grantFxView($engineRequest, $otherDataEntry);

        $this->assertTrue($this->policy->downloadSignedFx($sameDataEntry, $declaration));
        $this->assertFalse($this->policy->downloadSignedFx($otherDataEntry, $declaration));
    }

    private function grantFxView(EngineRequest $engineRequest, User $user): WorkflowStage
    {
        $fxStage = WorkflowStage::query()->firstOrCreate(
            [
                'workflow_version_id' => $engineRequest->workflow_version_id,
                'code' => 'FX_CONFIRM',
            ],
            [
                'name' => 'FX Confirmation',
                'sort_order' => 99,
                'is_initial' => false,
                'is_final' => false,
                'semantic_role' => StageSemanticRole::FX_CONFIRMATION,
                'version' => 1,
            ],
        );

        StagePermission::create([
            'stage_id' => $fxStage->id,
            'user_id' => $user->id,
            'access_level' => StageAccessLevel::VIEW,
            'display_label' => 'FX View',
            'version' => 1,
        ]);

        return $fxStage;
    }

    private function makeBank(): Bank
    {
        static $counter = 0;
        $counter++;

        return Bank::create([
            'name' => "Policy Test Bank {$counter}",
            'code' => "PTB{$counter}",
            'is_active' => true,
        ]);
    }

    private function makeEngineRequest(int $bankId): EngineRequest
    {
        ['request' => $request] = EngineWorkflowFactory::seedClaimStageWithTransition();
        DB::table('engine_requests')
            ->where('id', $request->id)
            ->update(['bank_id' => $bankId]);

        return $request->fresh();
    }

    private function makeEngineDeclaration(EngineRequest $engineRequest): CustomsDeclaration
    {
        $id = DB::table('customs_declarations')->insertGetId([
            'engine_request_id' => $engineRequest->id,
            'declaration_number' => 'FX-TEST-'.uniqid(),
            'issued_by' => $engineRequest->created_by,
            'issued_at' => now()->toDateTimeString(),
            'pdf_path' => 'fx-confirmation/test.pdf',
            'metadata' => json_encode([]),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        return CustomsDeclaration::findOrFail($id);
    }
}

<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\CustomsDeclaration;
use App\Models\EngineRequest;
use App\Models\User;
use App\Policies\CustomsDeclarationPolicy;
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
        $this->policy = new CustomsDeclarationPolicy;
        $this->seedGovernance();
    }

    // ── download() ───────────────────────────────────────────────────────

    public function test_director_can_download_engine_issued_declaration(): void
    {
        $bank = $this->makeBank();
        $engineRequest = $this->makeEngineRequest($bank->id);
        $declaration = $this->makeEngineDeclaration($engineRequest);

        $director = $this->assignGovernanceIdentity(
            User::factory()->create(['role' => UserRole::COMMITTEE_DIRECTOR]),
            UserRole::COMMITTEE_DIRECTOR
        );

        $this->assertTrue($this->policy->download($director, $declaration));
    }

    public function test_bank_reviewer_of_same_bank_can_download_engine_issued_declaration(): void
    {
        $bank = $this->makeBank();
        $otherBank = $this->makeBank();
        $engineRequest = $this->makeEngineRequest($bank->id);
        $declaration = $this->makeEngineDeclaration($engineRequest);

        $sameReviewer = $this->assignGovernanceIdentity(
            User::factory()->create(['role' => UserRole::BANK_REVIEWER, 'bank_id' => $bank->id]),
            UserRole::BANK_REVIEWER
        );
        $otherReviewer = $this->assignGovernanceIdentity(
            User::factory()->create(['role' => UserRole::BANK_REVIEWER, 'bank_id' => $otherBank->id]),
            UserRole::BANK_REVIEWER
        );

        $this->assertTrue($this->policy->download($sameReviewer, $declaration));
        $this->assertFalse($this->policy->download($otherReviewer, $declaration));
    }

    // ── downloadSignedFx() ───────────────────────────────────────────────

    public function test_director_can_download_signed_fx_for_engine_declaration(): void
    {
        $bank = $this->makeBank();
        $engineRequest = $this->makeEngineRequest($bank->id);
        $declaration = $this->makeEngineDeclaration($engineRequest);

        $director = $this->assignGovernanceIdentity(
            User::factory()->create(['role' => UserRole::COMMITTEE_DIRECTOR]),
            UserRole::COMMITTEE_DIRECTOR
        );

        $this->assertTrue($this->policy->downloadSignedFx($director, $declaration));
    }

    public function test_bank_user_of_same_bank_can_download_signed_fx(): void
    {
        $bank = $this->makeBank();
        $otherBank = $this->makeBank();
        $engineRequest = $this->makeEngineRequest($bank->id);
        $declaration = $this->makeEngineDeclaration($engineRequest);

        $sameDataEntry = $this->assignGovernanceIdentity(
            User::factory()->create(['role' => UserRole::DATA_ENTRY, 'bank_id' => $bank->id]),
            UserRole::DATA_ENTRY
        );
        $otherDataEntry = $this->assignGovernanceIdentity(
            User::factory()->create(['role' => UserRole::DATA_ENTRY, 'bank_id' => $otherBank->id]),
            UserRole::DATA_ENTRY
        );

        $this->assertTrue($this->policy->downloadSignedFx($sameDataEntry, $declaration));
        $this->assertFalse($this->policy->downloadSignedFx($otherDataEntry, $declaration));
    }

    // ── Helpers ──────────────────────────────────────────────────────────

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

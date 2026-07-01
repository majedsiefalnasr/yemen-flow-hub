<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\Bank;
use App\Models\CustomsDeclaration;
use App\Models\EngineRequest;
use App\Models\ImportRequest;
use App\Models\User;
use App\Policies\CustomsDeclarationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\EngineWorkflowFactory;
use Tests\TestCase;

class CustomsDeclarationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private CustomsDeclarationPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new CustomsDeclarationPolicy;
    }

    // ── download() ───────────────────────────────────────────────────────

    public function test_director_can_download_engine_issued_declaration(): void
    {
        $bank = $this->makeBank();
        $engineRequest = $this->makeEngineRequest($bank->id);
        $declaration = $this->makeEngineDeclaration($engineRequest);

        $director = User::factory()->create(['role' => UserRole::COMMITTEE_DIRECTOR]);

        $this->assertTrue($this->policy->download($director, $declaration));
    }

    public function test_bank_reviewer_of_same_bank_can_download_engine_issued_declaration(): void
    {
        $bank = $this->makeBank();
        $otherBank = $this->makeBank();
        $engineRequest = $this->makeEngineRequest($bank->id);
        $declaration = $this->makeEngineDeclaration($engineRequest);

        $sameReviewer = User::factory()->create([
            'role' => UserRole::BANK_REVIEWER,
            'bank_id' => $bank->id,
        ]);
        $otherReviewer = User::factory()->create([
            'role' => UserRole::BANK_REVIEWER,
            'bank_id' => $otherBank->id,
        ]);

        $this->assertTrue($this->policy->download($sameReviewer, $declaration));
        $this->assertFalse($this->policy->download($otherReviewer, $declaration));
    }

    public function test_legacy_declaration_download_still_works(): void
    {
        $bank = $this->makeBank();
        $importRequest = $this->makeImportRequest($bank->id);
        $declaration = $this->makeLegacyDeclaration($importRequest);

        $director = User::factory()->create(['role' => UserRole::COMMITTEE_DIRECTOR]);

        $this->assertTrue($this->policy->download($director, $declaration));
    }

    // ── downloadSignedFx() ───────────────────────────────────────────────

    public function test_director_can_download_signed_fx_for_engine_declaration(): void
    {
        $bank = $this->makeBank();
        $engineRequest = $this->makeEngineRequest($bank->id);
        $declaration = $this->makeEngineDeclaration($engineRequest);

        $director = User::factory()->create(['role' => UserRole::COMMITTEE_DIRECTOR]);

        $this->assertTrue($this->policy->downloadSignedFx($director, $declaration));
    }

    public function test_bank_user_of_same_bank_can_download_signed_fx(): void
    {
        $bank = $this->makeBank();
        $otherBank = $this->makeBank();
        $engineRequest = $this->makeEngineRequest($bank->id);
        $declaration = $this->makeEngineDeclaration($engineRequest);

        $sameDataEntry = User::factory()->create([
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $bank->id,
        ]);
        $otherDataEntry = User::factory()->create([
            'role' => UserRole::DATA_ENTRY,
            'bank_id' => $otherBank->id,
        ]);

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

    private function makeImportRequest(int $bankId): ImportRequest
    {
        $user = User::factory()->create(['bank_id' => $bankId]);

        app()->instance('workflow.transition.active', true);

        try {
            return ImportRequest::create([
                'bank_id' => $bankId,
                'created_by' => $user->id,
                'status' => 'SUBMITTED',
                'current_owner_role' => UserRole::BANK_REVIEWER,
                'currency' => 'USD',
                'amount' => 1000,
                'supplier_name' => 'Test Supplier',
                'goods_description' => 'Test goods',
                'port_of_entry' => 'Aden',
            ]);
        } finally {
            app()->forgetInstance('workflow.transition.active');
        }
    }

    private function makeLegacyDeclaration(ImportRequest $importRequest): CustomsDeclaration
    {
        $id = DB::table('customs_declarations')->insertGetId([
            'request_id' => $importRequest->id,
            'declaration_number' => 'FX-LEGACY-'.uniqid(),
            'issued_by' => $importRequest->created_by,
            'issued_at' => now()->toDateTimeString(),
            'pdf_path' => 'fx-confirmation/legacy.pdf',
            'metadata' => json_encode([]),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        return CustomsDeclaration::findOrFail($id);
    }
}

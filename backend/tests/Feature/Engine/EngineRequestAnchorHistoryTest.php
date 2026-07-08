<?php

namespace Tests\Feature\Engine;

use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Merchant;
use App\Models\User;
use App\Models\WorkflowHistoryEntry;
use App\Models\WorkflowStage;
use App\Models\WorkflowVersion;
use App\Support\EngineRequestStatus;
use Database\Seeders\GovernanceSeeder;
use Database\Seeders\ImportFinancingWorkflowSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\Support\EngineRequestAnchorInvariantValidator;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkflowActionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Feature tests for anchor history path construction and validation.
 *
 * Tests the specific history path scenarios defined in the spec:
 * - INTERNAL→CREATE/REJECT (returned to entry)
 * - FX_CONFIRM→FX/REJECT (returned from FX confirmation)
 * - FINAL→FX_CONFIRM/REJECT (returned from final)
 * - EXEC→CLOSED_REJECTED/REJECT_FINAL (rejected terminal)
 * - FINAL→CLOSED_COMPLETED/FINAL_APPROVE (completed terminal)
 */
class EngineRequestAnchorHistoryTest extends TestCase
{
    use RefreshDatabase;

    private EngineRequestAnchorInvariantValidator $validator;

    private Bank $bank;

    private Merchant $merchant;

    private User $creator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new EngineRequestAnchorInvariantValidator;

        $this->seed([
            GovernanceSeeder::class,
            ReferenceDataSeeder::class,
            WorkflowActionSeeder::class,
            ImportFinancingWorkflowSeeder::class,
            UserSeeder::class,
        ]);

        $this->bank = Bank::create(['code' => 'TEST_BANK', 'name' => 'Test Bank']);
        $this->merchant = Merchant::create([
            'bank_id' => $this->bank->id,
            'name' => 'Test Merchant',
            'tax_number' => '12345',
        ]);
        $this->creator = User::factory()->create([
            'email' => 'creator@example.com',
            'bank_id' => $this->bank->id,
        ]);
    }

    /**
     * History path: CREATE → INTERNAL → CREATE (INTERNAL→CREATE/REJECT)
     *
     * Request is back in CREATE stage after being rejected from INTERNAL.
     */
    public function test_history_path_internal_to_create_reject(): void
    {
        $version = $this->getWorkflowVersion();
        $createStage = $version->stages()->where('code', 'CREATE')->firstOrFail();
        $internalStage = $version->stages()->where('code', 'INTERNAL')->firstOrFail();

        $request = $this->makeRequest($version, $createStage, 'ENG-2026-TEST-RET-001', EngineRequestStatus::ACTIVE, [
            'amount' => 1000,
            'invoice_number' => 'INV-001',
        ]);

        // Path: CREATE (init) → INTERNAL (approve) → CREATE (reject)
        $this->addHistory($request, null, $createStage, 'APPROVE');
        $this->addHistory($request, $createStage, $internalStage, 'APPROVE');
        $this->addHistory($request, $internalStage, $createStage, 'REJECT');

        $this->validator->validate($request);
        $this->assertTrue(true);
    }

    /**
     * History path: FX_CONFIRM → FX (FX_CONFIRM→FX/REJECT)
     *
     * Request is back in FX stage after being rejected from FX_CONFIRM.
     */
    public function test_history_path_fx_confirm_to_fx_reject(): void
    {
        $version = $this->getWorkflowVersion();
        $createStage = $version->stages()->where('code', 'CREATE')->firstOrFail();
        $fxStage = $version->stages()->where('code', 'FX')->firstOrFail();
        $fxConfirmStage = $version->stages()->where('code', 'FX_CONFIRM')->firstOrFail();

        $internalStage = $version->stages()->where('code', 'INTERNAL')->firstOrFail();
        $supportStage = $version->stages()->where('code', 'SUPPORT')->firstOrFail();
        $execStage = $version->stages()->where('code', 'EXEC')->firstOrFail();
        $request = $this->makeRequest($version, $fxStage, 'ENG-2026-TEST-RET-002', EngineRequestStatus::ACTIVE, [
            'amount' => 1000,
            'invoice_number' => 'INV-002',
        ]);

        // Minimal path through to FX_CONFIRM then back
        $this->addHistory($request, null, $createStage, 'APPROVE');
        $this->addHistory($request, $createStage, $internalStage, 'APPROVE');
        $this->addHistory($request, $internalStage, $supportStage, 'APPROVE');
        $this->addHistory($request, $supportStage, $execStage, 'APPROVE');
        $this->addHistory($request, $execStage, $fxStage, 'APPROVE');
        $this->addHistory($request, $fxStage, $fxConfirmStage, 'APPROVE');
        $this->addHistory($request, $fxConfirmStage, $fxStage, 'REJECT');

        $this->validator->validate($request);
        $this->assertTrue(true);
    }

    /**
     * History path: FINAL → FX_CONFIRM (FINAL→FX_CONFIRM/REJECT)
     *
     * Request is back in FX_CONFIRM stage after being rejected from FINAL.
     */
    public function test_history_path_final_to_fx_confirm_reject(): void
    {
        $version = $this->getWorkflowVersion();
        $createStage = $version->stages()->where('code', 'CREATE')->firstOrFail();
        $fxConfirmStage = $version->stages()->where('code', 'FX_CONFIRM')->firstOrFail();
        $finalStage = $version->stages()->where('code', 'FINAL')->firstOrFail();

        $internalStage = $version->stages()->where('code', 'INTERNAL')->firstOrFail();
        $supportStage = $version->stages()->where('code', 'SUPPORT')->firstOrFail();
        $execStage = $version->stages()->where('code', 'EXEC')->firstOrFail();
        $fxStage = $version->stages()->where('code', 'FX')->firstOrFail();
        $request = $this->makeRequest($version, $fxConfirmStage, 'ENG-2026-TEST-RET-003', EngineRequestStatus::ACTIVE, [
            'amount' => 1000,
            'invoice_number' => 'INV-003',
        ]);

        // Minimal path to FINAL then back to FX_CONFIRM
        $this->addHistory($request, null, $createStage, 'APPROVE');
        $this->addHistory($request, $createStage, $internalStage, 'APPROVE');
        $this->addHistory($request, $internalStage, $supportStage, 'APPROVE');
        $this->addHistory($request, $supportStage, $execStage, 'APPROVE');
        $this->addHistory($request, $execStage, $fxStage, 'APPROVE');
        $this->addHistory($request, $fxStage, $fxConfirmStage, 'APPROVE');
        $this->addHistory($request, $fxConfirmStage, $finalStage, 'APPROVE');
        $this->addHistory($request, $finalStage, $fxConfirmStage, 'REJECT');

        $this->validator->validate($request);
        $this->assertTrue(true);
    }

    /**
     * History path: EXEC → CLOSED_REJECTED (EXEC→CLOSED_REJECTED/REJECT_FINAL)
     *
     * Terminal rejection from EXEC to CLOSED_REJECTED.
     */
    public function test_history_path_exec_to_closed_rejected_reject_final(): void
    {
        $version = $this->getWorkflowVersion();
        $createStage = $version->stages()->where('code', 'CREATE')->firstOrFail();
        $execStage = $version->stages()->where('code', 'EXEC')->firstOrFail();
        $rejectedStage = $version->stages()->where('code', 'CLOSED_REJECTED')->firstOrFail();

        $internalStage = $version->stages()->where('code', 'INTERNAL')->firstOrFail();
        $supportStage = $version->stages()->where('code', 'SUPPORT')->firstOrFail();
        $request = $this->makeRequest($version, $rejectedStage, 'ENG-2026-TEST-REJ-001', EngineRequestStatus::REJECTED, [
            'amount' => 1000,
            'invoice_number' => 'INV-004',
        ]);

        // Path to EXEC then reject
        $this->addHistory($request, null, $createStage, 'APPROVE');
        $this->addHistory($request, $createStage, $internalStage, 'APPROVE');
        $this->addHistory($request, $internalStage, $supportStage, 'APPROVE');
        $this->addHistory($request, $supportStage, $execStage, 'APPROVE');
        $this->addHistory($request, $execStage, $rejectedStage, 'REJECT_FINAL');

        $this->validator->validate($request);
        $this->assertTrue(true);
    }

    /**
     * History path: FINAL → CLOSED_COMPLETED (FINAL→CLOSED_COMPLETED/FINAL_APPROVE)
     *
     * Terminal completion from FINAL to CLOSED_COMPLETED.
     */
    public function test_history_path_final_to_closed_completed_final_approve(): void
    {
        $version = $this->getWorkflowVersion();
        $createStage = $version->stages()->where('code', 'CREATE')->firstOrFail();
        $finalStage = $version->stages()->where('code', 'FINAL')->firstOrFail();
        $completedStage = $version->stages()->where('code', 'CLOSED_COMPLETED')->firstOrFail();

        $internalStage = $version->stages()->where('code', 'INTERNAL')->firstOrFail();
        $supportStage = $version->stages()->where('code', 'SUPPORT')->firstOrFail();
        $execStage = $version->stages()->where('code', 'EXEC')->firstOrFail();
        $fxStage = $version->stages()->where('code', 'FX')->firstOrFail();
        $fxConfirmStage = $version->stages()->where('code', 'FX_CONFIRM')->firstOrFail();
        $request = $this->makeRequest($version, $completedStage, 'ENG-2026-TEST-COMP-001', EngineRequestStatus::CLOSED, [
            'amount' => 1000,
            'invoice_number' => 'INV-005',
        ]);

        // Path to FINAL then complete
        $this->addHistory($request, null, $createStage, 'APPROVE');
        $this->addHistory($request, $createStage, $internalStage, 'APPROVE');
        $this->addHistory($request, $internalStage, $supportStage, 'APPROVE');
        $this->addHistory($request, $supportStage, $execStage, 'APPROVE');
        $this->addHistory($request, $execStage, $fxStage, 'APPROVE');
        $this->addHistory($request, $fxStage, $fxConfirmStage, 'APPROVE');
        $this->addHistory($request, $fxConfirmStage, $finalStage, 'APPROVE');
        $this->addHistory($request, $finalStage, $completedStage, 'FINAL_APPROVE');

        $this->validator->validate($request);
        $this->assertTrue(true);
    }

    /**
     * Broken history path (from/to mismatch) fails validation.
     */
    public function test_broken_history_path_fails(): void
    {
        $version = $this->getWorkflowVersion();
        $createStage = $version->stages()->where('code', 'CREATE')->firstOrFail();
        $internalStage = $version->stages()->where('code', 'INTERNAL')->firstOrFail();
        $supportStage = $version->stages()->where('code', 'SUPPORT')->firstOrFail();

        $request = $this->makeRequest($version, $supportStage, 'ENG-2026-TEST-BROKEN-001', EngineRequestStatus::ACTIVE, [
            'amount' => 1000,
            'invoice_number' => 'INV-ERR-001',
        ]);

        // Broken path: CREATE → INTERNAL, but then FROM CREATE (wrong!)
        $this->addHistory($request, null, $createStage, 'APPROVE');
        $this->addHistory($request, $createStage, $internalStage, 'APPROVE');
        $this->addHistory($request, $createStage, $supportStage, 'APPROVE');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/history path broken/');

        $this->validator->validate($request);
    }

    /**
     * History doesn't end at current terminal stage for terminal request fails.
     */
    public function test_terminal_history_not_ending_at_current_stage_fails(): void
    {
        $version = $this->getWorkflowVersion();
        $createStage = $version->stages()->where('code', 'CREATE')->firstOrFail();
        $completedStage = $version->stages()->where('code', 'CLOSED_COMPLETED')->firstOrFail();
        $rejectedStage = $version->stages()->where('code', 'CLOSED_REJECTED')->firstOrFail();

        // Create request in rejected stage
        $request = $this->makeRequest($version, $rejectedStage, 'ENG-2026-TEST-TERM-ERR-001', EngineRequestStatus::REJECTED, [
            'amount' => 1000,
            'invoice_number' => 'INV-ERR-002',
        ]);

        // History ends at completed stage instead
        $this->addHistory($request, null, $createStage, 'APPROVE');
        $this->addHistory($request, $createStage, $completedStage, 'FINAL_APPROVE');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/latest history to_stage must equal current_stage_id/');

        $this->validator->validate($request);
    }

    private function getWorkflowVersion(): WorkflowVersion
    {
        return WorkflowVersion::query()
            ->whereHas('definition', fn ($q) => $q->where('code', 'IMPORT_FINANCING'))
            ->firstOrFail();
    }

    private function makeRequest(
        WorkflowVersion $version,
        WorkflowStage $stage,
        string $reference,
        string $status,
        array $data
    ): EngineRequest {
        return EngineRequest::create([
            'workflow_version_id' => $version->id,
            'current_stage_id' => $stage->id,
            'reference' => $reference,
            'status' => $status,
            'bank_id' => $this->bank->id,
            'merchant_id' => $this->merchant->id,
            'created_by' => $this->creator->id,
            'data' => $data,
            'amount' => $data['amount'] ?? null,
            'invoice_number' => $data['invoice_number'] ?? null,
        ]);
    }

    private function addHistory(
        EngineRequest $request,
        ?WorkflowStage $from,
        WorkflowStage $to,
        string $actionCode
    ): void {
        WorkflowHistoryEntry::create([
            'request_id' => $request->id,
            'from_stage_id' => $from?->id,
            'to_stage_id' => $to->id,
            'action_code' => $actionCode,
            'performed_by' => $this->creator->id,
            'created_at' => now(),
        ]);
    }
}

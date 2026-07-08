<?php

namespace Tests\Unit\Seeders;

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
 * Unit tests for EngineRequestAnchorInvariantValidator.
 *
 * Tests valid anchor states and intentionally invalid synthetic violations.
 */
class EngineRequestAnchorInvariantTest extends TestCase
{
    use RefreshDatabase;

    private EngineRequestAnchorInvariantValidator $validator;

    private WorkflowVersion $workflowVersion;

    private Bank $bank;

    private Merchant $merchant;

    private User $creator;

    private WorkflowStage $createStage;

    private WorkflowStage $supportStage;

    private WorkflowStage $completedStage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new EngineRequestAnchorInvariantValidator;

        // Seed workflow and governance
        $this->seed([
            GovernanceSeeder::class,
            ReferenceDataSeeder::class,
            WorkflowActionSeeder::class,
            ImportFinancingWorkflowSeeder::class,
            UserSeeder::class,
        ]);

        // Get workflow version
        $this->workflowVersion = WorkflowVersion::query()
            ->whereHas('definition', fn ($q) => $q->where('code', 'IMPORT_FINANCING'))
            ->firstOrFail();

        // Get stages
        $this->createStage = $this->workflowVersion->stages()->where('code', 'CREATE')->firstOrFail();
        $this->supportStage = $this->workflowVersion->stages()->where('code', 'SUPPORT')->firstOrFail();
        $this->completedStage = $this->workflowVersion->stages()->where('code', 'CLOSED_COMPLETED')->firstOrFail();

        $this->bank = Bank::create(['code' => 'TEST_BANK', 'name' => 'Test Bank']);
        $this->merchant = Merchant::create([
            'bank_id' => $this->bank->id,
            'name' => 'Test Merchant',
            'tax_number' => '12345',
        ]);
        $this->creator = User::factory()->create([
            'email' => 'test@example.com',
            'bank_id' => $this->bank->id,
        ]);
    }

    public function test_valid_active_create_stage_passes(): void
    {
        $request = $this->makeRequest(
            'ENG-2026-TEST-A001',
            $this->createStage,
            EngineRequestStatus::ACTIVE,
            ['amount' => 1000, 'invoice_number' => 'INV-001', 'request_percentage' => 50]
        );
        $this->addHistory($request, null, $this->createStage, 'APPROVE');

        $this->validator->validate($request);
        $this->assertTrue(true);
    }

    public function test_valid_terminal_completed_passes(): void
    {
        $finalStage = $this->workflowVersion->stages()->where('code', 'FINAL')->firstOrFail();
        $request = $this->makeRequest(
            'ENG-2026-TEST-A002',
            $this->completedStage,
            EngineRequestStatus::CLOSED,
            ['amount' => 1000, 'invoice_number' => 'INV-002']
        );
        $this->addHistory($request, null, $this->createStage, 'APPROVE');
        $this->addHistory($request, $this->createStage, $finalStage, 'APPROVE');
        $this->addHistory($request, $finalStage, $this->completedStage, 'FINAL_APPROVE');

        $this->validator->validate($request);
        $this->assertTrue(true);
    }

    public function test_valid_claimed_support_stage_passes(): void
    {
        $claimer = User::factory()->create([
            'email' => 'claimer@example.com',
            'bank_id' => $this->bank->id,
        ]);
        $request = $this->makeRequest(
            'ENG-2026-TEST-A004',
            $this->supportStage,
            EngineRequestStatus::ACTIVE,
            ['amount' => 1000, 'invoice_number' => 'INV-004'],
            [
                'claimed_by' => $claimer->id,
                'claimed_at' => now(),
                'claim_expires_at' => now()->addMinutes(15),
                'claim_stage_id' => $this->supportStage->id,
            ]
        );
        $this->addHistory($request, null, $this->supportStage, 'ADD_NOTES');

        $this->validator->validate($request);
        $this->assertTrue(true);
    }

    public function test_active_stage_with_wrong_status_fails(): void
    {
        $request = $this->makeRequest(
            'ENG-2026-TEST-FAIL-001',
            $this->createStage,
            EngineRequestStatus::CLOSED,
            ['amount' => 1000, 'invoice_number' => 'INV-ERR-001']
        );
        $this->addHistory($request, null, $this->createStage, 'APPROVE');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/non-terminal stage must have status=ACTIVE/');

        $this->validator->validate($request);
    }

    public function test_terminal_request_with_claim_fails(): void
    {
        $finalStage = $this->workflowVersion->stages()->where('code', 'FINAL')->firstOrFail();
        $claimer = User::factory()->create(['email' => 'claimer2@example.com', 'bank_id' => $this->bank->id]);

        $request = $this->makeRequest(
            'ENG-2026-TEST-FAIL-002',
            $this->completedStage,
            EngineRequestStatus::CLOSED,
            ['amount' => 1000, 'invoice_number' => 'INV-ERR-002'],
            [
                'claimed_by' => $claimer->id,
                'claimed_at' => now(),
                'claim_expires_at' => now()->addMinutes(15),
                'claim_stage_id' => $this->completedStage->id,
            ]
        );
        $this->addHistory($request, null, $this->createStage, 'APPROVE');
        $this->addHistory($request, $this->createStage, $finalStage, 'APPROVE');
        $this->addHistory($request, $finalStage, $this->completedStage, 'FINAL_APPROVE');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/terminal request must have claim columns null/');

        $this->validator->validate($request);
    }

    public function test_claimed_request_with_mismatched_claim_stage_fails(): void
    {
        $claimer = User::factory()->create(['email' => 'claimer3@example.com', 'bank_id' => $this->bank->id]);
        $request = $this->makeRequest(
            'ENG-2026-TEST-FAIL-003',
            $this->supportStage,
            EngineRequestStatus::ACTIVE,
            ['amount' => 1000, 'invoice_number' => 'INV-ERR-003'],
            [
                'claimed_by' => $claimer->id,
                'claimed_at' => now(),
                'claim_expires_at' => now()->addMinutes(15),
                'claim_stage_id' => $this->createStage->id,
            ]
        );
        $this->addHistory($request, null, $this->supportStage, 'ADD_NOTES');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/claim_stage_id must equal current_stage_id/');

        $this->validator->validate($request);
    }

    public function test_data_with_camel_case_key_fails(): void
    {
        $request = $this->makeRequest(
            'ENG-2026-TEST-FAIL-004',
            $this->createStage,
            EngineRequestStatus::ACTIVE,
            ['financeAmount' => 1000]
        );
        $this->addHistory($request, null, $this->createStage, 'APPROVE');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be snake_case/');

        $this->validator->validate($request);
    }

    public function test_data_with_unpublished_field_key_fails(): void
    {
        $request = $this->makeRequest(
            'ENG-2026-TEST-FAIL-005',
            $this->createStage,
            EngineRequestStatus::ACTIVE,
            ['unknown_field' => 'value']
        );
        $this->addHistory($request, null, $this->createStage, 'APPROVE');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/unpublished field keys/');

        $this->validator->validate($request);
    }

    public function test_amount_projection_mismatch_fails(): void
    {
        $request = $this->makeRequest(
            'ENG-2026-TEST-FAIL-006',
            $this->createStage,
            EngineRequestStatus::ACTIVE,
            ['amount' => 1000, 'invoice_number' => 'INV-ERR-006'],
            ['amount' => 2000]
        );
        $this->addHistory($request, null, $this->createStage, 'APPROVE');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/amount mismatch/');

        $this->validator->validate($request);
    }

    public function test_missing_history_entry_fails(): void
    {
        $request = $this->makeRequest(
            'ENG-2026-TEST-FAIL-007',
            $this->createStage,
            EngineRequestStatus::ACTIVE,
            ['amount' => 1000, 'invoice_number' => 'INV-ERR-007']
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/history must have at least one entry/');

        $this->validator->validate($request);
    }

    public function test_history_latest_stage_must_match_current_stage(): void
    {
        $internalStage = $this->workflowVersion->stages()->where('code', 'INTERNAL')->firstOrFail();
        $request = $this->makeRequest(
            'ENG-2026-TEST-FAIL-008',
            $this->createStage,
            EngineRequestStatus::ACTIVE,
            ['amount' => 1000, 'invoice_number' => 'INV-ERR-008']
        );
        $this->addHistory($request, null, $internalStage, 'APPROVE');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/latest history to_stage must equal current_stage_id/');

        $this->validator->validate($request);
    }

    public function test_history_action_must_exist_in_workflow_transitions(): void
    {
        $request = $this->makeRequest(
            'ENG-2026-TEST-FAIL-009',
            $this->createStage,
            EngineRequestStatus::ACTIVE,
            ['amount' => 1000, 'invoice_number' => 'INV-ERR-009']
        );
        $this->addHistory($request, null, $this->createStage, 'UNKNOWN_ACTION');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/action code .* not found on workflow/');

        $this->validator->validate($request);
    }

    private function makeRequest(
        string $reference,
        WorkflowStage $stage,
        string $status,
        array $data,
        array $overrides = []
    ): EngineRequest {
        $defaults = [
            'workflow_version_id' => $this->workflowVersion->id,
            'current_stage_id' => $stage->id,
            'reference' => $reference,
            'status' => $status,
            'bank_id' => $this->bank->id,
            'merchant_id' => $this->merchant->id,
            'created_by' => $this->creator->id,
            'data' => $data,
            'amount' => $data['amount'] ?? null,
            'invoice_number' => $data['invoice_number'] ?? null,
            'request_percentage' => $data['request_percentage'] ?? null,
        ];

        return EngineRequest::create(array_merge($defaults, $overrides));
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

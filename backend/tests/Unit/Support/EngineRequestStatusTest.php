<?php

namespace Tests\Unit\Support;

use App\Enums\FinalOutcome;
use App\Models\EngineRequest;
use App\Services\Workflow\Engine\EngineFinancingLedger;
use App\Support\EngineRequestStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EngineRequestStatusTest extends TestCase
{
    #[DataProvider('finalOutcomeStatusProvider')]
    public function test_final_outcome_maps_to_request_status(FinalOutcome $outcome, string $expected): void
    {
        $this->assertSame($expected, $outcome->toRequestStatus());
        $this->assertSame($expected, EngineRequestStatus::fromFinalOutcome($outcome));
    }

    public static function finalOutcomeStatusProvider(): array
    {
        return [
            [FinalOutcome::COMPLETED, EngineRequestStatus::CLOSED],
            [FinalOutcome::REJECTED, EngineRequestStatus::REJECTED],
            [FinalOutcome::CANCELLED, EngineRequestStatus::CANCELLED],
            [FinalOutcome::ABANDONED, EngineRequestStatus::ABANDONED],
        ];
    }

    public function test_null_final_outcome_falls_back_to_closed(): void
    {
        $this->assertSame(EngineRequestStatus::CLOSED, EngineRequestStatus::fromFinalOutcome(null));
    }

    public function test_is_terminal_and_is_closed_align(): void
    {
        $request = new EngineRequest(['status' => EngineRequestStatus::CANCELLED]);
        $this->assertTrue(EngineRequestStatus::isTerminal(EngineRequestStatus::CANCELLED));
        $this->assertTrue($request->isClosed());
        $this->assertFalse((new EngineRequest(['status' => EngineRequestStatus::ACTIVE]))->isClosed());
    }

    public function test_ledger_capacity_freeing_uses_shared_constant(): void
    {
        $this->assertSame(EngineRequestStatus::CAPACITY_FREEING, EngineFinancingLedger::NOT_ELIGIBLE_STATUSES);
    }
}

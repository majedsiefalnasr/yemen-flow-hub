<?php

namespace Tests\Unit\Rules;

use App\Exceptions\FinancingLimitExceededException;
use App\Rules\FinancingLimitRule;
use App\Services\FinancingLedgerService;
use Mockery;
use Tests\TestCase;

class FinancingLimitRuleTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_passes_when_service_reports_capacity_available(): void
    {
        $service = Mockery::mock(FinancingLedgerService::class);
        $service->shouldReceive('wouldExceed')
            ->once()
            ->with('TAX-1', 'INV-1', 25.0, null)
            ->andReturnFalse();
        $this->app->instance(FinancingLedgerService::class, $service);

        $rule = new FinancingLimitRule('TAX-1', 'INV-1');
        $rule->validate('request_percentage', 25, fn () => $this->fail('Validation should pass.'));
        $this->assertTrue(true);
    }

    public function test_throws_financing_limit_exceeded_when_service_reports_overflow(): void
    {
        $service = Mockery::mock(FinancingLedgerService::class);
        $service->shouldReceive('wouldExceed')
            ->once()
            ->with('TAX-1', 'INV-1', 80.0, null)
            ->andReturnTrue();
        $this->app->instance(FinancingLedgerService::class, $service);

        $this->expectException(FinancingLimitExceededException::class);

        $rule = new FinancingLimitRule('TAX-1', 'INV-1');
        $rule->validate('request_percentage', 80, fn () => null);
    }
}

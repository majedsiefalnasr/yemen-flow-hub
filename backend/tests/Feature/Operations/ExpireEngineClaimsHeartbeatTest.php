<?php

namespace Tests\Feature\Operations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EngineWorkflowFactory;
use Tests\TestCase;

class ExpireEngineClaimsHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_expire_claims_command_records_scheduler_heartbeat(): void
    {
        EngineWorkflowFactory::seedClaimStageWithTransition();

        $this->artisan('workflow:expire-engine-claims')->assertSuccessful();

        $this->assertDatabaseHas('scheduler_run_logs', [
            'command' => 'workflow:expire-engine-claims',
            'status' => 'success',
        ]);
    }

    public function test_notify_sla_signals_command_records_scheduler_heartbeat(): void
    {
        $this->artisan('workflow:notify-sla-signals')->assertSuccessful();

        $this->assertDatabaseHas('scheduler_run_logs', [
            'command' => 'workflow:notify-sla-signals',
            'status' => 'success',
        ]);
    }
}

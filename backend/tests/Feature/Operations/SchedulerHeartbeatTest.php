<?php

namespace Tests\Feature\Operations;

use App\Models\SchedulerRunLog;
use App\Services\Operations\SchedulerRunLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_successful_command_run(): void
    {
        app(SchedulerRunLogger::class)->recordSuccess('workflow:expire-engine-claims', affected: 3);

        $this->assertDatabaseHas('scheduler_run_logs', [
            'command' => 'workflow:expire-engine-claims',
            'status' => 'success',
            'affected_count' => 3,
        ]);
    }

    public function test_records_failed_command_run(): void
    {
        app(SchedulerRunLogger::class)->recordFailure(
            'workflow:notify-sla-signals',
            new \RuntimeException('boom'),
        );

        $log = SchedulerRunLog::query()->where('command', 'workflow:notify-sla-signals')->first();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('boom', $log->error_message);
    }

    public function test_last_run_returns_most_recent_entry(): void
    {
        $logger = app(SchedulerRunLogger::class);
        $logger->recordSuccess('audit:archive-old', affected: 1);
        $logger->recordSuccess('audit:archive-old', affected: 2);

        $last = $logger->lastRun('audit:archive-old');
        $this->assertSame(2, $last->affected_count);
    }
}

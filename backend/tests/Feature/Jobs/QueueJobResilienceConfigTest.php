<?php

namespace Tests\Feature\Jobs;

use App\Jobs\DispatchNotification;
use App\Jobs\GenerateReportExport;
use Tests\TestCase;

/**
 * Guards QUEUE-002: DispatchNotification and GenerateReportExport must carry
 * explicit $tries/$timeout/backoff() instead of inheriting worker/connection
 * defaults, mirroring the pattern already applied to ScanEngineRequestDocument
 * (QUEUE-001). GenerateReportExport gets a generous timeout since export rows
 * are bounded (ROW_LIMIT) but can still involve real I/O (CSV write to disk).
 */
class QueueJobResilienceConfigTest extends TestCase
{
    public function test_dispatch_notification_has_explicit_resilience_config(): void
    {
        $job = new DispatchNotification('type', 'info', 'title', null, null, null, null, [1]);

        $this->assertSame(3, $job->tries);
        $this->assertSame(30, $job->timeout);
        $this->assertSame([5, 15, 30], $job->backoff());
    }

    public function test_generate_report_export_has_explicit_resilience_config(): void
    {
        $job = new GenerateReportExport(1);

        $this->assertSame(3, $job->tries);
        $this->assertSame(300, $job->timeout);
        $this->assertSame([15, 60, 180], $job->backoff());
    }
}

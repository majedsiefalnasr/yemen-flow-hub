<?php

namespace Tests\Feature\Jobs;

use App\Jobs\DispatchNotification;
use App\Jobs\GenerateAuditLogExport;
use App\Jobs\GenerateReportExport;
use App\Jobs\ScanEngineRequestDocument;
use App\Jobs\SendEmailDelivery;
use Tests\TestCase;

/**
 * Guards QUEUE-003: fan-out, exports, and scans must land on dedicated
 * queues instead of competing on `default` with everything else, mirroring
 * the pattern already shipped for SendEmailDelivery's `emails` queue.
 */
class QueueSeparationTest extends TestCase
{
    public function test_dispatch_notification_uses_the_notifications_queue(): void
    {
        $job = new DispatchNotification('type', 'info', 'title', null, null, null, null, [1]);

        $this->assertSame('notifications', $job->queue);
    }

    public function test_scan_engine_request_document_uses_the_scans_queue(): void
    {
        $job = new ScanEngineRequestDocument(1);

        $this->assertSame('scans', $job->queue);
    }

    public function test_generate_report_export_uses_the_exports_queue_and_connection(): void
    {
        $job = new GenerateReportExport(1);

        $this->assertSame('exports', $job->queue);
        $this->assertSame('exports', $job->connection);
    }

    public function test_generate_audit_log_export_uses_the_exports_queue_and_connection(): void
    {
        $job = new GenerateAuditLogExport(1);

        $this->assertSame('exports', $job->queue);
        $this->assertSame('exports', $job->connection);
    }

    public function test_send_email_delivery_still_uses_the_emails_queue(): void
    {
        // Regression guard: QUEUE-003 must not disturb the existing emails queue.
        $job = new SendEmailDelivery(1);

        $this->assertSame('emails', $job->queue);
        $this->assertSame('emails', $job->connection);
    }
}

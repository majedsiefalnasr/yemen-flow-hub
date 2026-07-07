<?php

namespace Tests\Unit\Operations;

use Tests\TestCase;

class RetentionConfigTest extends TestCase
{
    public function test_retention_config_has_conservative_defaults(): void
    {
        $this->assertSame(12, config('retention.audit_hot_months'));
        $this->assertSame(30, config('retention.export_file_days'));
        $this->assertSame(90, config('retention.notification_unread_max_days'));
        $this->assertSame(365, config('retention.notification_read_days'));
        $this->assertSame(90, config('retention.superseded_document_file_days'));
        $this->assertTrue(config('retention.archive_first'));
    }
}

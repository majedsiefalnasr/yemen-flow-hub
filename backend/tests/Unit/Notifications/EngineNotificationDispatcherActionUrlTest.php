<?php

namespace Tests\Unit\Notifications;

use App\Services\Notifications\EngineNotificationDispatcher;
use Tests\TestCase;

class EngineNotificationDispatcherActionUrlTest extends TestCase
{
    public function test_engine_request_notifications_use_workflow_instance_route(): void
    {
        $this->assertSame(
            '/workflows/instances/123',
            app(EngineNotificationDispatcher::class)->engineRequestActionUrl(123),
        );
    }
}

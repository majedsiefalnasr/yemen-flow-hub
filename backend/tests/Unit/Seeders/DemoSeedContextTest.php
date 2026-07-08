<?php

namespace Tests\Unit\Seeders;

use Database\Seeders\Support\DemoSeedContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Testing\Fakes\BusFake;
use Illuminate\Support\Testing\Fakes\MailFake;
use Illuminate\Support\Testing\Fakes\QueueFake;
use RuntimeException;
use Tests\TestCase;

/**
 * Unit tests for DemoSeedContext.
 *
 * Asserts that fakes are properly installed/reset and that side effects
 * within the context are suppressed.
 */
class DemoSeedContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_context_returns_callback_result(): void
    {
        $result = DemoSeedContext::run(function (): string {
            return 'ok';
        });

        $this->assertSame('ok', $result);
    }

    public function test_fakes_installed_inside_callback(): void
    {
        DemoSeedContext::run(function (): void {
            $this->assertInstanceOf(MailFake::class, Mail::getFacadeRoot());
            $this->assertInstanceOf(QueueFake::class, Queue::getFacadeRoot());
            $this->assertInstanceOf(BusFake::class, Bus::getFacadeRoot());

            // Dispatches are captured by fakes (no real mail/job runs).
            Mail::raw('Seed test', fn ($message) => $message->to('seed@example.com'));
            Queue::push('seed-job', ['id' => 1]);
            Bus::dispatch(new DemoSeedContextFakeJob('demo'));

            Queue::assertPushed('seed-job');
            Bus::assertDispatched(DemoSeedContextFakeJob::class);
        });
    }

    public function test_callback_exceptions_are_rethrown(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        DemoSeedContext::run(function (): void {
            throw new RuntimeException('boom');
        });
    }
}

class DemoSeedContextFakeJob
{
    public function __construct(public string $value) {}
}

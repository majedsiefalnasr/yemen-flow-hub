<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/**
 * Demo seed execution context — scoped side-effect management.
 *
 * Wraps bulk demo seeding in fakes to suppress real email, queue jobs, and event buses.
 * This ensures that demo seeders can never send real mail or dispatch untracked jobs.
 *
 * Spec: backend/docs/superpowers/specs/2026-07-07-engine-demo-seeder-redesign-design.md § Demo seed execution context
 */
final class DemoSeedContext
{
    /**
     * Run a callable with faked mail, queue, and bus.
     *
     * Example:
     *   $result = DemoSeedContext::run(function () {
     *       // code that would normally dispatch jobs/send mail
     *       // jobs are faked, mail is faked, events are faked
     *       return 'done';
     *   });
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function run(callable $callback): mixed
    {
        Mail::fake();
        Queue::fake();
        Bus::fake();

        return $callback();
    }
}

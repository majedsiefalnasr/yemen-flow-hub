<?php

namespace App\Console\Commands;

use App\Console\Concerns\RecordsSchedulerHeartbeat;
use App\Services\Operations\OperationalAlertLogger;
use App\Services\Operations\SchedulerRunLogger;
use Illuminate\Console\Command;

class CheckSchedulerHealthCommand extends Command
{
    use RecordsSchedulerHeartbeat;

    protected $signature = 'ops:check-scheduler-health';

    protected $description = 'Detect scheduler commands that missed their expected run window';

    public function handle(SchedulerRunLogger $logger): int
    {
        return $this->runWithHeartbeat(function () use ($logger): array {
            $staleCommands = [];

            foreach (config('retention.scheduler_stale_minutes') as $command => $minutes) {
                $last = $logger->lastRun($command);
                $stale = $last === null || $last->ran_at->lt(now()->subMinutes($minutes));

                if (! $stale) {
                    continue;
                }

                $staleCommands[] = $command;
                OperationalAlertLogger::failure(
                    'scheduler_stale',
                    new \RuntimeException("Scheduled command missed run window: {$command}"),
                    [
                        'command' => $command,
                        'last_ran_at' => $last?->ran_at?->toIso8601String(),
                        'threshold_minutes' => $minutes,
                    ],
                );
            }

            return [
                'affected' => count($staleCommands),
                'meta' => ['stale_commands' => $staleCommands],
            ];
        });
    }
}

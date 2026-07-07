<?php

namespace App\Console\Concerns;

use App\Services\Operations\SchedulerRunLogger;

trait RecordsSchedulerHeartbeat
{
    protected function runWithHeartbeat(callable $callback): int
    {
        $command = (string) $this->signature;
        $logger = app(SchedulerRunLogger::class);

        try {
            $affected = (int) $callback();
            $logger->recordSuccess($command, $affected);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $logger->recordFailure($command, $e);
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}

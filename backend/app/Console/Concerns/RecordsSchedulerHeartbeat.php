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
            $result = $callback();
            $affected = is_array($result) ? (int) ($result['affected'] ?? 0) : (int) $result;
            $meta = is_array($result) ? ($result['meta'] ?? []) : [];
            $logger->recordSuccess($command, $affected, $meta);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $logger->recordFailure($command, $e);
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}

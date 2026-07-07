<?php

namespace App\Services\Operations;

use App\Models\SchedulerRunLog;

class SchedulerRunLogger
{
    public function recordSuccess(string $command, int $affected = 0, array $meta = []): void
    {
        SchedulerRunLog::create([
            'command' => $command,
            'status' => 'success',
            'affected_count' => $affected,
            'meta' => $meta ?: null,
            'ran_at' => now(),
        ]);
    }

    public function recordFailure(string $command, \Throwable $e, array $meta = []): void
    {
        SchedulerRunLog::create([
            'command' => $command,
            'status' => 'failed',
            'meta' => $meta ?: null,
            'error_message' => $e->getMessage(),
            'ran_at' => now(),
        ]);
    }

    public function lastRun(string $command): ?SchedulerRunLog
    {
        return SchedulerRunLog::query()
            ->where('command', $command)
            ->orderByDesc('ran_at')
            ->first();
    }
}

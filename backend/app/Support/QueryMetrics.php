<?php

namespace App\Support;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;

/**
 * OBS-001: request-scoped query counter. Bound as a singleton so one instance
 * accumulates across the whole request lifecycle; DB::listen feeds it from
 * AppServiceProvider::boot(). Slow individual queries are logged immediately
 * against the default channel tagged 'slow_query', independent of the running
 * totals below.
 */
class QueryMetrics
{
    private int $count = 0;

    private float $totalTimeMs = 0.0;

    public function record(QueryExecuted $event): void
    {
        $this->count++;
        $this->totalTimeMs += $event->time;

        $threshold = (int) config('observability.slow_query_threshold_ms', 200);
        if ($event->time >= $threshold) {
            Log::warning('slow_query', [
                'sql' => $event->sql,
                'time_ms' => $event->time,
                'connection' => $event->connectionName,
            ]);
        }
    }

    public function count(): int
    {
        return $this->count;
    }

    public function totalTimeMs(): float
    {
        return round($this->totalTimeMs, 2);
    }

    /**
     * In real HTTP-per-process deployment the container (and this singleton)
     * is fresh per request, so this only matters where a process serves
     * multiple requests: the testing kernel, queue workers with a persistent
     * app, and octane/swoole-style servers. The metrics middleware calls this
     * at the start of every request so the header always reflects only that
     * request's queries.
     */
    public function reset(): void
    {
        $this->count = 0;
        $this->totalTimeMs = 0.0;
    }
}

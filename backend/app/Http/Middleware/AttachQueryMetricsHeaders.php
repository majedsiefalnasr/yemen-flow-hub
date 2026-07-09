<?php

namespace App\Http\Middleware;

use App\Support\QueryMetrics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * OBS-001: exposes the request's accumulated query count/time as response
 * headers so load runs and regression tests can assert query volume without
 * a profiler attached. Hard-blocked in production regardless of config, so
 * internal query shape never reaches a client outside controlled environments.
 */
class AttachQueryMetricsHeaders
{
    public function __construct(private readonly QueryMetrics $metrics) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Reset first: in a persistent-process context (test kernel, queue
        // workers, octane) the QueryMetrics singleton otherwise carries over
        // queries from prior work in the same process.
        $this->metrics->reset();

        $response = $next($request);

        if (! config('app.env') || config('app.env') === 'production') {
            return $response;
        }

        if (! config('observability.expose_query_metrics_headers')) {
            return $response;
        }

        $response->headers->set('X-Query-Count', (string) $this->metrics->count());
        $response->headers->set('X-Query-Time-Ms', (string) $this->metrics->totalTimeMs());

        return $response;
    }
}

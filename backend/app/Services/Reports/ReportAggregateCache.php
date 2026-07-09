<?php

namespace App\Services\Reports;

use App\DTOs\Authorization\DataScopeContext;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CACHE-001: read-through cache for ReportController's pure aggregate
 * endpoints (grouped counts/sums — no live queue/claim state). The scope key
 * MUST encode org-classification + bank_id so cached aggregates never leak
 * across banks or get shared per-raw-user. A short TTL plus Cache::lock
 * around regeneration avoids a stampede of identical rebuilds when the entry
 * expires under concurrent load; a Redis outage falls through to a live
 * compute rather than a 500.
 */
class ReportAggregateCache
{
    private const TTL_SECONDS = 60;

    private const LOCK_WAIT_SECONDS = 5;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function remember(string $endpoint, DataScopeContext $scope, array $filters, Closure $compute): mixed
    {
        $key = $this->buildKey($endpoint, $scope, $filters);

        try {
            $cached = Cache::get($key);
            if ($cached !== null) {
                return $cached;
            }

            $lock = Cache::lock("{$key}:lock", self::LOCK_WAIT_SECONDS);

            return $lock->block(self::LOCK_WAIT_SECONDS, function () use ($key, $compute) {
                // Re-check inside the lock: another request may have already
                // populated the entry while this one waited for the lock.
                $cached = Cache::get($key);
                if ($cached !== null) {
                    return $cached;
                }

                $value = $compute();
                Cache::put($key, $value, self::TTL_SECONDS);

                return $value;
            });
        } catch (LockTimeoutException) {
            // Another process is already regenerating this exact key; rather
            // than block indefinitely, compute live for this request. The
            // cache will be warm for the next caller once the lock holder finishes.
            return $compute();
        } catch (Throwable $e) {
            // Redis down (or any cache-layer failure) must never surface as a
            // 500 on a report endpoint — fall through to a live, uncached compute.
            Log::warning('report_aggregate_cache_unavailable', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return $compute();
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildKey(string $endpoint, DataScopeContext $scope, array $filters): string
    {
        // Scope segment: never the raw user id. systemWide is its own bucket
        // (shared across all systemWide users, who are authorized to see the
        // same system-wide data); a bank-scoped user's bucket is keyed on
        // their bank_id specifically, never shared across banks.
        $scopeSegment = $scope->systemWide ? 'systemwide' : 'bank:'.($scope->ownBankId ?? 'none');

        ksort($filters);
        $filterHash = substr(hash('sha256', json_encode($filters, JSON_UNESCAPED_UNICODE) ?: '{}'), 0, 16);

        return "report_aggregate:{$endpoint}:{$scopeSegment}:{$filterHash}";
    }
}

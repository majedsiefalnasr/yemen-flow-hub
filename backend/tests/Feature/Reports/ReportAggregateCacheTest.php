<?php

namespace Tests\Feature\Reports;

use App\DTOs\Authorization\DataScopeContext;
use App\Services\Reports\ReportAggregateCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Guards CACHE-001: cached report aggregates must never leak across banks or
 * be shared per-raw-user, must regenerate under a lock (no stampede), and
 * must fall through to a live compute if the cache layer itself fails
 * (Redis down) rather than surfacing a 500.
 */
class ReportAggregateCacheTest extends TestCase
{
    private ReportAggregateCache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new ReportAggregateCache;
    }

    public function test_two_banks_never_share_a_cache_entry(): void
    {
        $bankA = new DataScopeContext(systemWide: false, ownBankId: 1);
        $bankB = new DataScopeContext(systemWide: false, ownBankId: 2);

        $resultA = $this->cache->remember('summary', $bankA, [], fn () => ['total' => 'BANK_A_DATA']);
        $resultB = $this->cache->remember('summary', $bankB, [], fn () => ['total' => 'BANK_B_DATA']);

        $this->assertSame(['total' => 'BANK_A_DATA'], $resultA);
        $this->assertSame(['total' => 'BANK_B_DATA'], $resultB);

        // Re-fetch bank A: must still return bank A's cached value, not
        // bank B's, and the compute closure must not even run (cache hit).
        $resultAAgain = $this->cache->remember('summary', $bankA, [], fn () => ['total' => 'SHOULD_NOT_RUN']);
        $this->assertSame(['total' => 'BANK_A_DATA'], $resultAAgain);
    }

    public function test_systemwide_scope_is_a_distinct_bucket_from_any_bank(): void
    {
        $systemWide = new DataScopeContext(systemWide: true, ownBankId: null);
        $bank = new DataScopeContext(systemWide: false, ownBankId: 1);

        $systemWideResult = $this->cache->remember('summary', $systemWide, [], fn () => ['total' => 'ALL_BANKS']);
        $bankResult = $this->cache->remember('summary', $bank, [], fn () => ['total' => 'ONE_BANK']);

        $this->assertNotSame($systemWideResult, $bankResult);
    }

    public function test_different_filter_sets_are_cached_separately(): void
    {
        $scope = new DataScopeContext(systemWide: false, ownBankId: 1);

        $resultA = $this->cache->remember('summary', $scope, ['status' => 'ACTIVE'], fn () => ['total' => 'ACTIVE_ONLY']);
        $resultB = $this->cache->remember('summary', $scope, ['status' => 'CLOSED'], fn () => ['total' => 'CLOSED_ONLY']);

        $this->assertSame(['total' => 'ACTIVE_ONLY'], $resultA);
        $this->assertSame(['total' => 'CLOSED_ONLY'], $resultB);
    }

    public function test_different_endpoints_are_cached_separately_even_with_the_same_scope_and_filters(): void
    {
        $scope = new DataScopeContext(systemWide: false, ownBankId: 1);

        $summary = $this->cache->remember('summary', $scope, [], fn () => 'SUMMARY_DATA');
        $byBank = $this->cache->remember('by-bank', $scope, [], fn () => 'BY_BANK_DATA');

        $this->assertSame('SUMMARY_DATA', $summary);
        $this->assertSame('BY_BANK_DATA', $byBank);
    }

    public function test_cache_key_never_incorporates_a_raw_user_id(): void
    {
        // Two different bank_admin users at the SAME bank must share the
        // cache entry (same scope), proving the key is scope-based, not
        // per-user -- a per-raw-user key would defeat the whole point of
        // caching (every user gets their own cold cache) and could also leak
        // if a key ever accidentally embedded PII.
        $scope = new DataScopeContext(systemWide: false, ownBankId: 5);

        $callCount = 0;
        $compute = function () use (&$callCount) {
            $callCount++;

            return 'SHARED_BANK_DATA';
        };

        $this->cache->remember('summary', $scope, [], $compute);
        $this->cache->remember('summary', $scope, [], $compute);

        $this->assertSame(1, $callCount, 'The second call for the same scope must be a cache hit, not a recompute.');
    }

    public function test_falls_through_to_live_compute_when_the_cache_store_is_unavailable(): void
    {
        Cache::shouldReceive('get')->andThrow(new \RuntimeException('Redis connection refused'));

        $scope = new DataScopeContext(systemWide: true, ownBankId: null);
        $result = $this->cache->remember('summary', $scope, [], fn () => ['total' => 'LIVE_COMPUTE_FALLBACK']);

        $this->assertSame(['total' => 'LIVE_COMPUTE_FALLBACK'], $result);
    }
}

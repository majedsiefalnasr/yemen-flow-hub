# CACHE-001 — Report aggregate cache

## What changed

### 1. `App\Services\Reports\ReportAggregateCache`

New read-through cache service: `remember(string $endpoint, DataScopeContext $scope, array $filters, Closure $compute): mixed`.

- **Scope key never leaks across banks or per-raw-user.** `buildKey()` derives a `scopeSegment` from the same `DataScopeContext` already used for query-level scoping — `systemwide` (one shared bucket for all systemWide-authorized users) or `bank:{id}` (one bucket per bank, never shared, never keyed on the acting user's id).
- **Filter-set and endpoint separation.** The key also folds in a truncated sha256 of the ksorted filter array and the endpoint name, so `reports/summary?bank=1` and `reports/by-bank?bank=1` never collide, and different filter combinations for the same endpoint/scope never collide.
- **Stampede guard.** On a miss, regeneration runs inside `Cache::lock()` (5s wait); a concurrent request that can't acquire the lock in time computes live rather than blocking indefinitely (`LockTimeoutException` → live compute).
- **Redis-down fail-open.** Any `Throwable` from the cache layer (not just lock timeout) falls through to a live, uncached compute and logs a `report_aggregate_cache_unavailable` warning — a report endpoint never surfaces a 500 because the cache store is unreachable.
- **TTL: 60 seconds.** Short enough that a stale aggregate is never visible for more than a minute; long enough to absorb bursts of dashboard/report views from the same scope.

### 2. `ReportController` wiring

All 10 pure-aggregate methods (`summary`, `requestsOverTime`, `byWorkflowStage`, `byBank`, `byMerchant`, `bySector`, `byCurrency`, `stageDuration`, `sla`, `teamPerformance`) now wrap their query-and-format logic in a private `cached(string $endpoint, Request $request, Closure $compute)` helper, which resolves `DataScope::forUser($request->user())` and delegates to `ReportAggregateCache::remember()` with `$request->query()` as the filter set.

No response shape changed — each closure still returns the same array/Collection structure previously returned directly; the only difference is the array now flows through the cache before being wrapped in `response()->json(['data' => $data])`.

## Why this is safe

- Confirmed via a full read of `ReportController.php` (397 lines pre-change) that all 10 methods are grouped SQL aggregates (`COUNT`/`SUM`/`AVG` with `GROUP BY`) with no live queue/claim state — matches the finding's own "clean target" description. No method that reads live-mutable per-row state (claims, in-flight votes) was cached.
- Cross-bank leakage and raw-user-id leakage are proven by dedicated unit tests against the cache service itself (see below), not just asserted.
- Redis-down fallback is proven by forcing `Cache::get()` to throw and asserting the endpoint still returns the live-computed value rather than erroring.

## Residual (found, not fixed — out of CACHE-001's scope)

`ReportController::applyScope()` does not special-case `isSystemAdmin()`. `ScreenPermissionSeeder` grants `system_admin` the `reports.VIEW`/`EXPORT` capabilities, but `DataScope::forUser()`'s default match arm returns `systemWide: false, ownBankId: null` for an org classified as `OTHER` (which is where `system_admin` lands) — so a `system_admin` user passes the capability gate but the underlying query scoping returns zero rows today. This is a pre-existing authorization/UX bug independent of caching (it would misbehave identically with or without this cache layer) and is not part of CACHE-001's stated scope (cache-key correctness), so it was **not fixed here**. Flagging as a follow-up candidate.

## Test evidence

`backend/tests/Feature/Reports/ReportAggregateCacheTest.php` (6 tests, new, unit-level against `ReportAggregateCache` directly):

1. `test_two_banks_never_share_a_cache_entry` — bank A and bank B get distinct cached values; re-fetching bank A returns bank A's value without re-running the compute closure (the explicit cross-bank leakage test the roadmap gate calls for).
2. `test_systemwide_scope_is_a_distinct_bucket_from_any_bank` — systemWide and a specific bank never share a bucket.
3. `test_different_filter_sets_are_cached_separately` — same scope, different filters → different cache entries.
4. `test_different_endpoints_are_cached_separately_even_with_the_same_scope_and_filters` — no cross-endpoint collision.
5. `test_cache_key_never_incorporates_a_raw_user_id` — two different users at the same bank share one cache entry (compute closure runs once), proving the key is scope-based, not per-user.
6. `test_falls_through_to_live_compute_when_the_cache_store_is_unavailable` — `Cache::get()` throwing still returns the live-computed value (the explicit Redis-down gate the roadmap calls for).

```
ReportAggregateCacheTest: 6 tests, 6 assertions
```

## Regression check

Full `tests/Feature/Report/` directory (all report endpoints, including pre-existing bank-scoping tests in `WP7Task5Test`):

```
Report/ (V1ReportsTest, SlaReportTest, WP7Task5Test): 34 tests, 101 assertions — all pass
```

No test needed updating; the cache layer is transparent to response shape and existing scoping assertions.

## Pint

`vendor/bin/pint app/Http/Controllers/Api/V1/ReportController.php --test` — passed clean.

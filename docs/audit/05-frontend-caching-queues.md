# 05 — Frontend Consumption, Caching & Queues

Block 4 deliverable (prompt Phases 6–8). Owns **how the frontend consumes the API**, the backend caching strategy, and the queue/background-processing design. Backend endpoint behavior is Block 2 (cross-referenced, not duplicated). Evidence at baseline SHA `be652fdd`.

## Part A — Frontend consumption (35 pages, 222 fetch sites)

### Already good (recorded, no finding)

- **Server-side pagination + filtering + sorting.** The engine-requests list/queue store passes `options` (page, per_page, filters, sort) straight to the API (`app/stores/engineRequests.store.ts`, `useEngineRequests`); there is **no client-side filtering or sorting of large sets**. Reference/audit/report lists are all server-paginated. The frontend never downloads bulk data to filter locally.
- **Stale-response protection without AbortController.** `useReferenceData` (and peers) use a monotonic **request-token** guard (`tablesRequestToken`) so a slow earlier response cannot overwrite a newer one (`useReferenceData.ts:22-49`). This is a valid substitute for cancellation for correctness (not for saving bandwidth — see FE-001).
- **Debounce is used** (31 sites) on search inputs.
- **Claim heartbeat is correct.** `useEngineClaim` pings every 60 s only while the claim is held by the current user, and stops on release, claim loss (`CLAIM_NOT_HELD`), or `onUnmounted` (`useEngineClaim.ts:50-118`). No leaked timers.
- **Unread count uses the dedicated cheap endpoint** `notifications/unread-count` in `useNotifications.ts:90` (the earlier full-list path in `notifications.store.ts` is a fallback, not the polling path — see FE-002).

### Findings

| ID | Severity | Issue |
| --- | --- | --- |
| FE-001 | Medium | No request cancellation anywhere: `useApi` wraps `$fetch` with no `signal`/`AbortController` support, so navigating away from a list/detail mid-request leaves the request in flight (wasted bandwidth + a late token-guarded no-op). |
| FE-002 | Low | `notifications.store.ts` `refreshUnreadCount()`/`fetchRecent()` fetch the **full** notifications page 1 to derive the count in PHP-land, duplicating what `unread-count` already returns cheaply; if any caller uses these instead of `useNotifications().unread-count`, it over-fetches. |
| FE-003 | Low | Reference/lookup data (workflow definitions, reference tables/values, banks) is refetched on every use — no client-side cache of stable reference data (`useReferenceData` holds no cross-call cache). |

No finding for: duplicate mount fetches (the token guard + store dedup make these harmless), polling storms (only the 60 s claim heartbeat and a 1 s countdown timer exist — no aggressive dashboard polling), virtualization (queues are server-paginated to ≤100 rows/page, so DOM size is bounded), or bundle (not measured; noted as out-of-scope unless flagged later).

## Part B — Caching strategy

### Current state (disciplined; no over-caching)

Every `Cache::` usage was inventoried. All are correct and none leak permissions:

| Cache user | Key | TTL | Invalidation | Isolation |
| --- | --- | --- | --- | --- |
| `MfaService`, `PasswordRecoveryService`, `StepUpService` | per-email / per-user-id challenge keys | short (challenge window) | explicit `forget` on use/expire | per-user ✓ |
| `SettingResolver` | `setting:{key}` | 1 h | `forget` on write | global (settings are global) ✓ |
| `PermissionService` | `screen_permissions.role.{roleId}` | 1 h | `forget` per role + `clearAllScreenPermissionCaches()` on workflow/permission change | **per-role**, not per-user ✓ (documented limitation: team-scoped rows handled by an uncached per-user overlay, `PermissionService.php:254-322`) |
| `EngineFinancingLedger` | financing lock name | — | `Cache::lock` (stampede/concurrency guard) ✓ | per-resource ✓ |

Store: Redis (`CACHE_STORE=redis`). No list/detail/dashboard response is cached — so there is **no stale-data or permission-leak risk** from caching today.

### The gap (finding)

| ID | Severity | Issue |
| --- | --- | --- |
| CACHE-001 | Medium | Dashboard (`DashboardStatsService`) and the 10 `reports/*` aggregates recompute from scratch on **every** request over the largest tables (Block 3 evidence: `reports/summary` ~0.96 s at 1M rows), with **no caching**. These are the prime cacheable surface (aggregates change slowly, are read-heavy). |

**Recommended cache design for CACHE-001** (not a blanket cache — the root cause is also being fixed in API-002/005):
- Cache dashboard/report aggregates **per (user-scope-key, filter-set)**, where scope-key encodes org classification + bank_id (never per raw user — share across a bank) so no cross-bank leakage.
- Short TTL (e.g. 60–300 s) + `Cache::lock`-guarded regeneration to prevent stampede on expiry.
- Invalidate opportunistically on transition (or accept short TTL staleness — acceptable for dashboards).
- **Redis-down fallback:** `CACHE_STORE` must degrade to computing live (never fail the request); confirm the fallback store config in the deploy runbook.
- **Do not cache** list/queue/detail responses, permission-sensitive per-request data, or anything a user mutates and immediately re-reads. Caching must not paper over the query cost — CACHE-001 pairs with API-002/005 query fixes, not replaces them.

## Part C — Queues & background processing

### Current state

Queue driver Redis; 4 jobs; **no synchronous `dispatchSync`/`dispatchNow` in the HTTP path** ✓; notification dispatch correctly deferred via `DB::afterCommit` (Block 1). Recipient fan-out uses `NotificationRecipient::insertOrIgnore` → **idempotent on retry** (`DispatchNotification.php:71-73`), which compensates for the absence of `ShouldBeUnique`.

| Job | tries | backoff | timeout | failed() | queue | Assessment |
| --- | --- | --- | --- | --- | --- | --- |
| `SendEmailDelivery` | 3 | [60,300] | — | ✓ | **`emails`** (separated) | Hardened ✓ |
| `DispatchNotification` | default | — | — | ✓ | default | Idempotent via insertOrIgnore; missing explicit tries/backoff/timeout |
| `GenerateReportExport` | default | — | — | ✓ | default | Has failed-row safety net; missing explicit tries/backoff/timeout |
| `ScanEngineRequestDocument` | default | — | — | **✗** | default | **No resilience config and no `failed()`** — a virus scan that silently disappears on failure |

### Findings

| ID | Severity | Issue |
| --- | --- | --- |
| QUEUE-001 | Medium | `ScanEngineRequestDocument` (document virus scan) has no `tries`/`timeout`/`backoff`/`failed()` — a failed or stuck scan leaves the document in an indeterminate scan state with no dead-letter handling. Security-relevant (uploaded PDFs). |
| QUEUE-002 | Low | `DispatchNotification` and `GenerateReportExport` lack explicit `tries`/`backoff`/`timeout`; they inherit worker defaults. Report generation (potentially long) especially wants an explicit `timeout`. |
| QUEUE-003 | Low | No queue separation beyond `emails`, and **no Horizon** (not in composer.json) → no queue depth/latency/failure visibility. At scale, notification fan-out and report exports compete with everything on `default`. |

**Recommendations:**
- QUEUE-001: add `public int $tries`, `public int $timeout`, `backoff`, and a `failed()` that records the scan as failed (fail-closed: a document whose scan failed must not be treated as clean). Tier: Pre-production (security-relevant).
- QUEUE-002: set explicit `$tries`/`$backoff`/`$timeout`; give `GenerateReportExport` a generous `$timeout`. Tier: Threshold-gated.
- QUEUE-003: separate queues by workload (`notifications`, `exports`, `scans`, `emails`) and add Horizon for monitoring (ties into Block 5 observability). Tier: Threshold-gated; Horizon Pre-production if queue volume is expected at launch.

### Transaction interaction (verified)

Notification dispatch is `DB::afterCommit` so a rolled-back transition emits no notification (Block 1). Audit + history rows are written **inside** the transition transaction, before the after-commit job — ordering is correct: the audit record is durable before the async notification fires.

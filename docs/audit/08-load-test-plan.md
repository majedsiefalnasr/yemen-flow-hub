# 08 — Load & Stress Test Plan

Block 5 deliverable (prompt Phase 11). **Plan only — not executed in this audit.** It defines how to validate the Block 3/4 findings and the Block 6 roadmap fixes against the design target. All success criteria trace to the measurable targets in `06-security-observability.md`.

## Prerequisites

- The seeded `yfh_audit`-equivalent dataset at design target (1M `engine_requests`, ~5M `workflow_history`/`audit_logs`) — reuse the profile in `evidence/dataset-profile.md` (regenerate via the local harness; see Block 6 keep/discard recommendation).
- Observability from OBS-001 enabled (slow-query log + per-request query count) so tests capture query counts, not just latency.
- A dedicated non-production environment sized to the intended production tier (the audit's local box — 128 MB buffer pool — is **not** valid for absolute-latency validation; use production-representative hardware).
- Tooling: **k6** (recommended — scriptable, good percentile reporting) or Artillery. Trade-off: k6 needs a separate binary; Artillery is Node-native. Either is fine.

## Test scenarios

| # | Scenario | Load | Validates |
| --- | --- | --- | --- |
| 1 | **Queue browsing** — `GET /v1/engine-requests/my-queue` + `index` with filters/search, mixed banks | 100–300 concurrent users, ramp 1→300 over 5 min, 10 min hold | ARCH-001, ARCH-002, ARCH-004, DB-001/002; p95 ≤ 300 ms, queries/req ≤ 15 |
| 2 | **Dashboard/report burst** — `dashboard/stats` + `reports/summary`/`sla` under concurrent load | 200 users hitting dashboards every 30–60 s | API-002/005/006, CACHE-001; cached p95 ≤ 500 ms; verify cache hit-ratio + no cross-bank leakage |
| 3 | **Concurrent transitions on one request** — many actors POST `actions` on the same `engineRequest` | 20–50 concurrent submitters, same id | Optimistic `version` lock + `lockForUpdate` correctness (ARCH-007); exactly one succeeds, others get `requestStale`; no deadlock; audit/history integrity |
| 4 | **Voting-session close under contention** — director closes while members vote | 10–30 concurrent votes + 1 close | Row-lock correctness; `AUTO_ABSTAIN_TIMEOUT` applied atomically; no lost/duplicate votes |
| 5 | **Request creation storm** — concurrent `POST /v1/engine-requests` | 50–100 concurrent creators | API-003 allocator contention; unique references, no `REFERENCE_ALLOCATION_FAILED`, bounded retry |
| 6 | **Large export** — `reports/exports` + `audit-logs/export` (post API-004 async) | 10 concurrent exports at 1M+ rows | API-004, QUEUE-002; exports run async, don't block HTTP workers, complete within job timeout |
| 7 | **Queue burst** — trigger mass notification fan-out (bulk transitions) | 1000+ notifications queued rapidly | QUEUE-003 separation, worker throughput, `insertOrIgnore` idempotency on retry |
| 8 | **Redis outage** — kill Redis mid-load (cache + queue driver) | Scenario 1+2 load, Redis down 60 s | CACHE-001 Redis-down fallback (compute live, no 500s); queue backpressure/recovery behavior |
| 9 | **Slow external service** — throttle the FX/PDF path | Scenario 3 with slowed PDF render | ARCH-007 lock-hold impact; confirm lock-wait/timeout behavior under a slow synchronous effect |
| 10 | **DB connection pressure** — sustained mixed load near `max_connections` | ramp to connection ceiling | connection exhaustion behavior; confirm long transactions (ARCH-007) don't hold connections excessively |

## Metrics to collect (every scenario)

- Latency p50 / p95 / p99 per endpoint.
- **Queries per request** (from OBS-001 instrumentation) — a latency pass with an unnoticed query-count regression is still a failure.
- Error rate + status-code distribution (esp. 409/422 for contention, 429 for throttle, 500 for failures).
- Response sizes (bounded?).
- Queue depth, job latency, failure rate (scenarios 6–8).
- Cache hit-ratio (scenario 2).
- DB: rows examined, temp-table/filesort incidence (slow-query log), lock waits, deadlocks, connection count.

## Success criteria & failure thresholds

- **Pass:** p95 within the `06-security-observability.md` targets; queries/request within target; zero cross-bank data leakage (scenario 2); zero lost/duplicate votes or double-transitions (3–4); zero `REFERENCE_ALLOCATION_FAILED` (5); no HTTP 500s during Redis outage (8, fallback works); no deadlocks.
- **Fail thresholds:** any p95 > 2× target; queries/request > target; any cross-bank leak (**critical, blocks release**); any lost update / duplicate vote (**critical**); worker pool saturation blocking HTTP (6); unrecovered errors after Redis returns (8).

## Before/after validation

Run the suite **twice**: once against baseline (findings unfixed) to reproduce the Block 3 numbers at production hardware, once after each roadmap phase (Block 6, Phases B–E) to prove the improvement with real percentiles — never claim an improvement without this measured pair.

## Explicitly out of scope for this audit

Execution. This document is the plan; running it belongs to Phase F of the roadmap (`07-roadmap.md`) after the fixes land.

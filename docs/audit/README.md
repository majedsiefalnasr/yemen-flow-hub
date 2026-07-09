# Performance & Scalability Audit

**Status:** ✅ Complete (Blocks 1–6) · Baseline SHA `be652fdd` · 2026-07-08

Read `00-scope-and-method.md` first — findings must not be interpreted without its calibration context. This is a **pre-production** audit: no production metrics exist, and all dynamic evidence is local-synthetic.

| Document | Contents | Status |
| --- | --- | --- |
| [00-scope-and-method.md](00-scope-and-method.md) | Decision & assumption register, baseline, approval history | Live |
| [01-architecture.md](01-architecture.md) | Current architecture and request lifecycle | Block 1 ✓ |
| [02-findings.md](02-findings.md) | Consolidated findings table | Live (29 findings; SEC-001 fixed) |
| [03-database-plan.md](03-database-plan.md) | Schema/index/query/archival plan | Block 3 ✓ (1M-row EXPLAIN evidence) |
| [04-api-plan.md](04-api-plan.md) | Pagination/filtering/response/rate-limit plan | Block 2 ✓ |
| [05-frontend-caching-queues.md](05-frontend-caching-queues.md) | Frontend consumption, caching, queue findings | Block 4 ✓ |
| [06-security-observability.md](06-security-observability.md) | Security gate, monitoring targets | Block 5 ✓ |
| [07-roadmap.md](07-roadmap.md) | Phased roadmap, before/after, verification checklist | Block 6 ✓ |
| [08-load-test-plan.md](08-load-test-plan.md) | Load & stress test plan (not executed) | Block 5 ✓ |
| [evidence/](evidence/) | Environment, dataset profile, captured SQL and plans | Live (5 queries, 6 plans) |

## Executive summary

**System health: fundamentally sound, not yet scale-ready.** Yemen Flow Hub is a well-architected Laravel 11 / Nuxt 4 platform — service-oriented, single data-access idiom, mandatory `EngineTransitionService`, query-level org/bank scoping, append-only audit logging enforced at the model layer, and idempotent transitions via optimistic locking. It is more optimization-aware than typical (deliberate serialization memoization, a JSON→column projection pattern, server-side pagination/filtering throughout). **No rewrite is warranted.** The gaps are concentrated in a handful of hot query paths, missing indexes, an absent aggregate-cache layer, and — most importantly — a total absence of performance observability.

**This is a pre-production audit.** No production metrics exist; all dynamic evidence is local-synthetic, captured against a **design-target dataset (1,000,000 `engine_requests`, ~5,000,000 each `workflow_history`/`audit_logs`)** on a modest local box (128 MB buffer pool — so query *shape* is the primary evidence, timings are directional). See `00-scope-and-method.md`.

**29 findings** (1 Critical — **fixed during the audit**; 8 High; 12 Medium; 8 Low).

**Main performance risks (all measured):**
- **my-queue SLA ordering** sorts ~96k rows with a per-row correlated subquery → **~2.6 s per queue page** (ARCH-002). A covering index alone takes it to ~0.7 s.
- **Engine list** with `whereDate` + leading-wildcard search + deep offset → 492k-row filter+sort, ~0.3 s (ARCH-004); a composite index makes it a ~0.08 s covering scan.
- **Whole `stage_permissions` table hydrated into PHP on every list/queue/stats request** (ARCH-001) — the highest-leverage single fix.
- **Per-row `fx_panel` authorization** = ~2 uncached queries per serialized row (API-001).
- **Dashboard/report aggregates recomputed uncached** every request (CACHE-001; `reports/summary` ~0.96 s).

**Main scalability risks:** `audit_logs` + `workflow_history` grow forever with no retention wired in (ARCH-006); the reference allocator **breaks permanently at ~1,000,000 requests/year** via lexicographic overflow (API-003); the FX-PDF render runs synchronously inside the transition row lock (ARCH-007).

**Most urgent (Pre-production):** enable observability (OBS-001 — you cannot safely operate or validate anything without it); the three evidence-backed indexes + range-date fix (DB-001/002, ARCH-004); a default rate-limit (ARCH-003); fail-closed document scanning (QUEUE-001); the reference-allocator fix (API-003).

**Security assessment: strong.** The one Critical (an unauthenticated `/test-api` route dumping all users' PII, SEC-001) was fixed during the audit (`375fe5f2`). Sort/filter inputs are whitelisted (no injection surface), audit logs are tamper-proof at the model layer, enumeration is mitigated, and transitions are idempotent. Every performance recommendation passed the consolidated security gate — with two conditions to honor at implementation: cache keys must be org+bank-scoped (CACHE-001), and scan failures must fail closed (QUEUE-001).

**Overall:** ship the Phase A–B–C work (measurement, critical fixes, the indexed hot paths) before go-live; the rest is threshold-gated. Advanced infrastructure (partitioning, read replicas, search engines) is **not** recommended now — simpler optimizations are demonstrably sufficient at the design target. Full plan in [`07-roadmap.md`](07-roadmap.md).

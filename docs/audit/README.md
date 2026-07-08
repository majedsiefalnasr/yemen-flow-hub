# Performance & Scalability Audit

**Status:** Blocks 1–2 complete · Block 3 next · Baseline SHA `be652fdd` · Started 2026-07-08

Read `00-scope-and-method.md` first — findings must not be interpreted without its calibration context. This is a **pre-production** audit: no production metrics exist, and all dynamic evidence is local-synthetic.

| Document | Contents | Status |
| --- | --- | --- |
| [00-scope-and-method.md](00-scope-and-method.md) | Decision & assumption register, baseline, approval history | Live |
| [01-architecture.md](01-architecture.md) | Current architecture and request lifecycle | Block 1 ✓ |
| [02-findings.md](02-findings.md) | Consolidated findings table | Live (15 findings; SEC-001 fixed) |
| [03-database-plan.md](03-database-plan.md) | Schema/index/query/archival plan | Pending |
| [04-api-plan.md](04-api-plan.md) | Pagination/filtering/response/rate-limit plan | Block 2 ✓ |
| [05-frontend-caching-queues.md](05-frontend-caching-queues.md) | Frontend consumption, caching, queue findings | Pending |
| [06-security-observability.md](06-security-observability.md) | Security gate, monitoring targets | Pending |
| [07-roadmap.md](07-roadmap.md) | Phased roadmap, before/after, verification checklist | Pending |
| [08-load-test-plan.md](08-load-test-plan.md) | Load & stress test plan (not executed) | Pending |
| [evidence/](evidence/) | Environment, dataset profile, captured SQL and plans | Live |

## Executive summary

(written in Block 6)

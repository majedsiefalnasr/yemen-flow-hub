# 06 — Security-Correctness Gate & Observability

Block 5 deliverable (prompt Phases 9–10; the load-test plan is `08-load-test-plan.md`). Security and correctness were checked cross-cutting in every earlier block; this block **consolidates and verifies** those checks and adds standalone security review + observability. Evidence at baseline SHA `be652fdd`.

## Part A — Consolidated security-correctness gate

Every open finding's recommendation was checked against: (a) bank/org scoping at query level, (b) policy/permission behavior, (c) transaction boundaries and locking, (d) audit-log completeness and ordering, (e) idempotency where relevant.

| Finding | Gate verdict | Condition to preserve during implementation |
| --- | --- | --- |
| ARCH-001 (SQL-ify stage-permission resolution) | **Pass with condition** | Must reproduce exact identity-set semantics (null=wildcard, AND-in-row, OR-across-rows, EXECUTE⊃VIEW). Add parity tests vs the current PHP evaluator before switching. |
| ARCH-002 / DB-001 (SLA index + projection column) | Pass | Ordering-only change; scoping via `forUser` unaffected. |
| ARCH-004 / DB-002 (composite index + range dates) | Pass | Query keeps its `bank_id` predicate; index does not widen visibility. |
| ARCH-003 (default throttle) | Pass | Additive control; size above legitimate polling. |
| ARCH-006 / archival (audit/history retention) | **Pass with condition** | Archival ≠ deletion — archived rows stay queryable; auditability fully preserved. `audit_logs` model is append-only-enforced (`AuditLog::booted`), so archival must move rows via a privileged path, not `delete()`. |
| ARCH-007 (shorten transition critical section) | **Pass with condition** | Must preserve atomicity: a committed FX confirmation always has its document. No committed-transition-without-document window. |
| API-001 (batch fx_panel/can_execute) | Pass | Per-row authorization output must be byte-identical to the current per-row resolution. |
| API-002 / API-005 (grouped aggregates) | Pass | Merged query keeps `forUser` + accessible-stage scoping. |
| API-003 (reference allocator) | Pass | Reference uniqueness preserved by the monotonic allocator. |
| API-004 (async audit export) | Pass | Job retains `viewAny` policy + scope; export stays audit-logged. |
| API-006 (SQL-side SLA report) | Pass | `applyScope` preserved; move derivation PHP→SQL with parity tests. |
| API-007 / DB-003 (exact subject_type + range dates) | Pass | Filter behavior only. |
| CACHE-001 (cache aggregates) | **Pass with condition (critical)** | Cache key **must** encode org classification + bank_id so no cross-bank aggregate leak; never key per-raw-user-shared; TTL short; `Cache::lock` regeneration. |
| QUEUE-001 (scan resilience) | **Pass with condition (critical)** | `failed()` must mark the document scan **failed / fail-closed** — a scan-failed document must never be treated as clean/available. |
| QUEUE-002/003, FE-001/002/003, ARCH-005 | Pass | No scoping/authz/audit impact. |

**Result:** no recommendation weakens security if its stated condition is honored. The two conditions to watch hardest at implementation time are **CACHE-001 scope-key** and **QUEUE-001 fail-closed**.

## Part B — Standalone security review (SEC series)

Verified **strong** (recorded, no finding):

- **User-supplied sort/filter is whitelisted everywhere** — every sortable endpoint uses `in_array($request->input('sort'), [allowed], true)` with a safe fallback, and `direction` is ternary-clamped to `asc`/`desc` (`RoleController.php:42-43`, `BankController.php:31`, `UserController.php:51`, `OrganizationController.php:45`, `TeamController.php:47`). **No SQL-injection surface via sort/filter.** Optimized queries must keep this whitelist.
- **Mass assignment safe** — models use explicit `$fillable`; no `$guarded = []`. `AuditLog` is **append-only enforced at the model layer** (`updating`/`deleting` throw, `AuditLog.php:16-25`) — the audit trail cannot be altered/erased from the app.
- **Enumeration mitigated** — `authorize('view'|'execute')` runs before loading detail (`EngineRequestController.php:127,153,290`); out-of-scope ids get 403, not data. Route id pattern constrained (`[0-9]+`).
- **Idempotency / double-submit** — every transition checks the optimistic `version` and throws `requestStale` on mismatch (`EngineTransitionService.php:55,187,248`), inside the `lockForUpdate` transaction. Concurrent/duplicate submits are safe.
- **Auth baseline** — login 5/min, OTP/reset throttled, notification/settings/document mutations throttled (Block 1 route inventory).

### SEC findings

| ID | Severity | Issue |
| --- | --- | --- |
| SEC-002 | Medium | `audit_logs` has **no `bank_id`**, so audit logs cannot be bank-scoped at the query level. `AuditLogController::show` works around this by denying **all** non-system-wide users (`:69-73`), meaning bank admins get **no** scoped audit access at all — a functional gap and a scoping-model weakness. Adding `bank_id` (derived from `workflow_instance_id`/subject at write time) would enable proper scoped audit reads + a scoped index (DB-003). |
| SEC-003 | Low | `bootstrap/app.php` writes an `audit_logs` row on every 403 authorization failure including unauthenticated ones (Block 1 ARCH-006). Without the ARCH-003 default throttle, an unauthenticated scanner can drive unbounded audit writes (write-amplification / cheap DoS on the audit table). Mitigated once ARCH-003 lands; tracked here as the security framing of ARCH-006. |

SEC-001 (unauthenticated `/test-api`) was the Critical, **fixed** in Block 1 (`375fe5f2`).

## Part C — Observability audit (OBS series)

**Current state: essentially no application performance observability.** No Telescope, Pulse, Horizon, Sentry, or Bugsnag in `composer.json`; no `DB::listen`/slow-query/query-count instrumentation; no `config/logging.php` override (framework default stack). For a system targeting millions of rows and audit-sensitivity, this is the single largest operational-readiness gap.

### OBS findings

| ID | Severity | Issue |
| --- | --- | --- |
| OBS-001 | High | No performance observability: no per-endpoint latency/query-count, no slow-query log, no queue metrics, no cache hit-ratio, no error-rate/response-size tracking. Regressions and the Block 3/4 hot paths would be invisible in production. |
| OBS-002 | Medium | No queue monitoring (no Horizon) — queue depth, job latency, and failure rate are unobservable (ties to QUEUE-003). |

### Recommendations (sized to this project — not everything)

- **Pre-production:** enable MySQL **slow-query log** (threshold e.g. 200 ms) + `log_queries_not_using_indexes` in staging; add lightweight per-request **query-count + duration logging** (a `DB::listen` counter logged at terminate, or Laravel Pulse). Pulse is the low-overhead fit here (first-party, minimal deps) — recommend **Laravel Pulse** for request/slow-query/exception/cache panels.
- **Threshold-gated:** **Horizon** when queue volume warrants (OBS-002/QUEUE-003); an APM/error tracker (Sentry) for exception + p95 tracking if an external service is acceptable for a government deployment (confirm data-residency constraints first).
- **Telescope:** local/staging only (never production — it stores request/DB payloads).

### Measurable performance targets (to validate against, not claimed results)

| Endpoint class | p95 first page | Max queries/request | Response size | Notes |
| --- | --- | --- | --- | --- |
| List / queue (`engine-requests`, `my-queue`) | ≤ 300 ms | ≤ 15 | bounded to page size (≤100) | after ARCH-001/002 + DB-001/002 |
| Detail (`engine-requests/{id}`) | ≤ 250 ms | ≤ 20 | single request | after API-001 batch |
| Dashboard / reports | ≤ 500 ms (cached) / ≤ 1.5 s (cold) | ≤ 10 | bounded | after API-002/005 + CACHE-001 |
| Transition (`actions`) | ≤ 400 ms (excl. FX PDF) | ≤ 15 | single | lock-hold minimized (ARCH-007) |
| Audit list | ≤ 400 ms | ≤ 10 | bounded to page | after API-007/DB-003 |

Global targets: no normal list endpoint loads unbounded rows; API response sizes bounded by pagination; queue-based work never blocks HTTP; per-endpoint query count controlled (targets above).

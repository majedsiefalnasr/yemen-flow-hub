# 03 — Database & Query Plan

Block 3 deliverable. Schema model, index inventory, captured query plans, index proposals with migrations + rollbacks, and archival plan. All dynamic evidence is local-synthetic against `yfh_audit` at the design-target volume (1,000,000 `engine_requests`; 5,095,870 each `workflow_history` / `audit_logs`). See `evidence/environment.md` (128 MB buffer pool — disk-bound; plan shape is primary evidence) and `evidence/dataset-profile.md` (distributions + set-based build note). Captured SQL in `evidence/queries/`, plans in `evidence/explain/`.

## 1. Schema model (49 tables)

Grouped by growth class:

| Growth class | Tables (selected) | Notes |
| --- | --- | --- |
| **Grows forever (hot)** | `engine_requests`, `workflow_history`, `audit_logs`, `engine_notifications`, `notification_recipients` | The scale-critical set. `audit_logs` + `workflow_history` are the two largest (~2 GB + ~1.4 GB at target). |
| Slow growth | `engine_request_documents`, `report_exports`, `login_histories`, `password_histories`, `scheduler_run_logs`, `audit_log_archives` | Bounded by activity; retention candidates. |
| Config / near-static | `workflow_definitions/versions/stages/transitions/actions`, `stage_permissions`, `field_definitions/groups`, `stage_field_rules`, `roles`, `screens`, `screen_permissions`, `reference_tables/values`, `system_settings` | Small; but `stage_permissions` drives ARCH-001 PHP cost. |
| Entity masters | `banks`, `organizations`, `users`, `teams`, `merchants`, `merchant_*` | Tens–hundreds of rows expected. |

Key column facts (`engine_requests`): JSON `data` column (projected to typed columns `amount`/`currency`/`invoice_number`/`invoice_number_normalized`/`request_percentage` via `RequestProjectionSync`); optimistic `version int`; soft-delete only on `merchants`. `audit_logs` has JSON `metadata`/`old_values`/`new_values`, nullable `user_id` (unauthenticated failures), no `bank_id` (scoping gap — see §5). `workflow_history` is append-only, no unique constraint, FK cascade from `engine_requests`.

## 2. Index inventory (hot tables) & gaps

`engine_requests` is heavily single-column indexed (status, bank_id, current_stage_id, claimed_by, claim_expires_at, merchant_id, amount, currency, invoice_number(+normalized), request_percentage, reference unique) plus composites `(status, current_stage_id)` and `(workflow_version_id, status)`.

**Confirmed gaps (evidence-backed below):**

| Gap | Table | Impact |
| --- | --- | --- |
| No `(bank_id, created_at, id)` | `engine_requests` | Default list sort (`created_at DESC`) cannot seek+order within a bank scope → full filter+sort of the bank's rows (ARCH-004). |
| No `(request_id, to_stage_id, created_at)` | `workflow_history` | SLA correlated subquery filters `to_stage_id` *after* the `(request_id, created_at)` index lookup → per-row cost in the my-queue sort (ARCH-002). |
| No usable predicate index for `whereDate`/infix-LIKE | `engine_requests`, `audit_logs` | Function-wrapped column + leading `%` defeat all indexes (ARCH-004, API-007). |

Existing single-column indexes on low-selectivity columns (`status`, `currency`, `request_percentage`, `amount`) are **write-cost suspects** — candidates for review/removal once composites cover the real access patterns (see §4 note). No action without confirming they are unused by other queries (deferred, needs query-log evidence).

## 3. Captured plan evidence (headline findings)

### ARCH-002 — my-queue SLA ordering (Critical hot path)

`evidence/explain/ARCH-002-my-queue-sla.txt`. Plan (1M rows):

- Scoped set resolved by index **intersection** (`status='ACTIVE'` ∩ `bank_id=1`) → **96,186 rows**.
- Those 96,186 rows are **sorted by the SLA expression before `LIMIT 25`** — unindexable arithmetic in ORDER BY.
- The correlated `workflow_history` subquery runs **per row** during projection/sort; it hits `(request_id, created_at)` but filters `to_stage_id` post-lookup.
- **Wall-clock: ~2.6 s for one queue page** (3 runs: 2.66 / 2.69 / 2.54).

Before/after covering index `workflow_history(request_id, to_stage_id, created_at)` (`ARCH-002-my-queue-sla-after-index.txt`): subquery becomes a **covering index lookup** (cost 4.31 → 1.14), **wall-clock 2.6 s → ~0.7 s (~3.7×)**. The 96k-row sort remains — the index fixes the per-row subquery, not the sort itself.

### ARCH-004 — engine list search + date + deep offset

`evidence/explain/ARCH-004-list-search-offset.txt`. `whereDate` (`cast(created_at as date)`) + `reference/invoice_number LIKE '%…%'` + `OFFSET 40000`: filters **492,814 rows** (all of bank 1), sorts ≥40,025 for the offset. **~0.3 s.** With range-date + composite `(bank_id, created_at, id)` (`ARCH-004-list-after-composite.txt`): becomes a **covering index range scan**, **~0.08 s (≈4×)**.

### API-005 — reports/summary multi-pass

Seven separate counts ≈ **0.96 s**; single `GROUP BY status` grouped pass ≈ **0.33 s**. `evidence/queries/API-005-*.sql`.

### API-007 / ARCH-006 — audit filter

`evidence/explain/API-007-audit-filter.txt`: `whereDate` + `subject_type LIKE '%…%'` with `ORDER BY id DESC LIMIT 30` walks PRIMARY backward and stops early **when recent rows match** (cheap in the common case), but the post-scan `Filter` degenerates toward a full reverse scan when matches are old/sparse — unpredictable latency on a 5M-row table.

### API-003 — reference allocator

`evidence/explain/API-003-reference-max.txt`: `MAX(reference) LIKE 'ENG-2026-%'` is a **covering index range scan** (not the bottleneck). The defect is logic: lexicographic `MAX` mis-orders once a 7-digit suffix exists (`"1000000" < "999999"`), demonstrated arithmetically; plus per-create recompute + unique-retry race. Write path — assessed statically, no `EXPLAIN ANALYZE`.

### ARCH-001 — stage-permission load

`SELECT * FROM stage_permissions` is a trivial 28-row table scan **in SQL**. The cost is **PHP-side**: `StagePermissionResolver::accessibleStageIds` hydrates the whole table into Eloquent models and groups/filters in PHP on **every** list/queue/stats/graph request, and it scales with published-workflow count, not with the querying user's scope. Not a plan problem — an application-layer problem (see `04-api-plan.md`).

## 4. Index proposals (each with migration + rollback)

Only three indexes are proposed — all justified by captured before/after evidence. No blind indexing.

### DB-IDX-1 — `workflow_history (request_id, to_stage_id, created_at)`

- **Benefits:** ARCH-002 SLA subquery (my-queue, stats SLA metrics, `reports/sla`), any "stage entry time" lookup.
- **Evidence:** subquery cost 4.31 → 1.14; my-queue 2.6 s → 0.7 s.
- **Write cost:** one more index on a 5M-row, append-heavy table; every transition inserts one history row → one extra index maintenance. Acceptable — history writes are once-per-transition, not hot-loop.
- **Migration / rollback:**
  ```php
  // up
  Schema::table('workflow_history', fn (Blueprint $t) =>
      $t->index(['request_id', 'to_stage_id', 'created_at'], 'wh_req_tostage_created'));
  // down
  Schema::table('workflow_history', fn (Blueprint $t) => $t->dropIndex('wh_req_tostage_created'));
  ```

### DB-IDX-2 — `engine_requests (bank_id, created_at, id)`

- **Benefits:** default list sort within bank scope (ARCH-004 index-path), report date-range scans; covering for id-only projections.
- **Evidence:** 492k-row filter+sort → covering range scan; ~0.3 s → ~0.08 s.
- **Write cost:** one composite on the 1M-row hot table; inserts/updates touching `bank_id`/`created_at` maintain it. `created_at` is set-once; `bank_id` rarely changes → low churn.
- **Caveat:** pairs with the code change to replace `whereDate` with range bounds (API-007/ARCH-004) — the index is only usable if the query stops wrapping `created_at` in `DATE()`.
- **Migration / rollback:**
  ```php
  // up
  Schema::table('engine_requests', fn (Blueprint $t) =>
      $t->index(['bank_id', 'created_at', 'id'], 'er_bank_created'));
  // down
  Schema::table('engine_requests', fn (Blueprint $t) => $t->dropIndex('er_bank_created'));
  ```

### DB-IDX-3 — `audit_logs (subject_type, created_at)` *(conditional)*

- **Benefits:** audit list/export filtered by entity + date, once the code switches to exact `subject_type = ?` and range dates (API-007).
- **Evidence:** current plan relies on reverse-PRIMARY early-stop; selective+old filters degrade. Index proposal is **gated on the code fix** — without it the index is unused.
- **Write cost:** one index on the largest (5M, append-only) table; every audited action inserts one row. Justify only if audit filtering is a real workload (confirm in Block 5 observability). Tier: Threshold-gated.
- **Migration / rollback:** analogous `index(['subject_type','created_at'], 'al_subject_created')` / `dropIndex`.

**Not proposed:** no index for leading-wildcard search — indexes cannot help infix LIKE. Search needs a code/UX change (prefix search or FULLTEXT on `invoice_number_normalized`), decided with product (ARCH-004 recommendation).

## 5. Archival, retention, and consistency

- **`audit_logs` / `workflow_history` grow forever** (ARCH-006). At target they are the two largest tables and slow every historical query. `AuditLogArchiveService` + `audit_log_archives` table already exist — Block 6 roadmap wires a scheduled archival/retention policy (move rows older than a retention window to the archive table / partition), preserving auditability (archival ≠ deletion). Tier: Threshold-gated on row count.
- **Partitioning** (`audit_logs` by `created_at` range, `workflow_history` by `request_id` range) is an **Optional/Threshold-gated** option only if archival proves insufficient — per the infrastructure trigger rule, not recommended for initial implementation.
- **`audit_logs` has no `bank_id`** — `AuditLogController::show` already denies non-system-wide users (`:69-73`) because logs can't be bank-scoped at the query level. This is a scoping limitation, not a perf issue; flagged for Block 5 (SEC series) — adding `bank_id` would enable proper scoped audit access and a scoped index.
- **Locking (ARCH-007 / API-003):** the transition holds `engine_requests` row lock across validation, projection UPDATE, 2 log inserts, audience queries, and (on FX stages) synchronous PDF render. Assessed statically per protocol — no `EXPLAIN ANALYZE` on the write path. Block 6 roadmap carries the "shorten the critical section" recommendation with its atomicity constraint.

## 6. Finding status updates from Block 3 evidence

- ARCH-002 → **Verified** (plan + before/after timing).
- ARCH-004 → **Verified** (plan + before/after timing).
- API-005 → **Verified** (before/after timing).
- API-002, API-006 → remain **Partially Verified** (share ARCH-001/002 roots now measured; per-endpoint capture not separately run — the shared root evidence is sufficient for the recommendation).
- API-007 / ARCH-006 → **Partially Verified** (plan shows the early-stop nuance; worst-case not force-measured).
- API-003 → **Verified** (logic + index plan).
- ARCH-001 → **Verified** as an application-layer (not SQL) cost.

# WP-7 — Two-Layer Visibility Wave

**Status:** Draft for review (Phase 6) — **HIGH RISK** (broadest package; touches every read surface)
**Source of authority:** `2026-07-05-feature-review-notes.md` — Phase 4 SW-1/SW-4 architectural decision
**Traceability:** D17 (two-layer model), D17-N1/N2 (audit + compliance scope), D17-N3 (exports), D17-N4 (duplicate masking + normalization), D18-N1/N2/N3 (report exports), D18-N3 (classification scope), D19-N2 (notification audiences), D21-N2/N3 (dashboard + search scope), D22-N3 (financing advisory scope), D8-N1 + R4 step 4 (inactive filtering), D22-N5 step 2 (invoice normalization upgrade + backfill)
**Dependencies:** WP-1 (classification field + enum) **hard prerequisite**; WP-R (R4 audience matcher, R5s1 normalization helper) **hard prerequisite**. WP-0 T-3 (audience matrix, inactive cases pinned) is the equivalence oracle whose pins flip here.
**Enables:** WP-8 (field-visibility-on-output uses the same scope primitive); WP-13 (retention respects scope).
**Overall risk:** high — repeats one pattern across ~8 read surfaces. Mitigated by extracting one shared `DataScope` primitive applied everywhere, with a characterization test per surface.

## Change classification

| Item | Kind |
|------|------|
| S-0 `DataScope` primitive + resolver | Approved functional (SW-4) |
| S-1 audit read scope | Approved functional (D17-N1) |
| S-2 compliance scope | Approved functional (D17-N2) |
| S-3 report + export scope + EXPORT capability + audit | Approved functional (D17-N3, D18-N1/N2/N3) |
| S-4 dashboard scope | Approved functional (D21-N2) |
| S-5 search scope | Approved functional (D21-N3) |
| S-6 notification audience scope | Approved functional (D19-N2) |
| S-7 financing advisory scope + masking | Approved functional (D22-N3) |
| S-8 duplicate-invoice masking | Approved functional (D17-N4) |
| S-9 invoice normalization upgrade + backfill | Approved functional (D22-N5 step 2 / D17-N4) |
| S-10 inactive team/role filtering (R4 step 4) | Approved functional (D8-N1) — flips T-3 pins |

**Explicitly out of scope:** field-level output visibility (D3-N2/D10-N3 → WP-8); server-side list pagination/KPI redesign (D3-N1 → WP-12); retention (WP-13); role-model migration (WP-10); envelope standardization (R9 → WP-14).

---

## S-0 — `DataScope` primitive (the keystone)

**Why one primitive:** every surface repeats "screen permission gates access; classification bounds scope." Codify it once so drift is impossible.

**Required:**
- `App\Services\Authorization\DataScope` — given a `User`, resolves to a value object: `{ classification, ownBankId, systemWide: bool }`. Rules:
  - `BANKING_SECTOR` → `{ systemWide: false, ownBankId: user->bank_id }` (bank_id required for BANKING_SECTOR per WP-1 C-3/C-4; null ⇒ no scope, no data).
  - `NATIONAL_COMMITTEE` → `{ systemWide: true }` (when the relevant screen/capability is granted; capability check is separate).
  - `OTHER` → `{ systemWide: false, ownBankId: null }` (no broad scope; only explicitly-assigned data, none by default).
  - **Null `bank_id` never implies system-wide** (D18-N3) — a null-bank user is `OTHER`-equivalent unless their org is NATIONAL_COMMITTEE.
- One Eloquent scope applier: `DataScope::applyTo($query, $bankColumn = 'bank_id')` — adds `where($bankColumn, $ownBankId)` when not system-wide; no-op (but capability-gated at the controller) when system-wide; matches-nothing when scoped-without-bank.
- Capability checks stay in controllers/policies (screen permission layer); `DataScope` is purely the data-bounding layer. The two never merge.

## S-1 — Audit read scope (D17-N1)

**Current:** `AuditLogPolicy::view/viewAny` = `audit VIEW` capability only; platform-wide reads for any grant holder.
**Required:**
- NATIONAL_COMMITTEE + `audit VIEW` → platform-wide (current behavior preserved for NC).
- BANKING_SECTOR users: **no platform-wide audit**. If bank-scoped audit is desired (decision: out of scope here — park), it would be a separate bank-scoped view of safe own-bank events. For WP-7, BANKING_SECTOR gets **no audit access** (default-deny) unless a future rule adds bank-scoped audit.
- Query: `AuditLogController::index/show/export` apply `DataScope` — but audit logs aren't bank-column-keyed cleanly. MVP: NC-only enforcement — non-NC users with the capability are denied at the policy layer (`viewAny` returns false unless NC or system_admin). Bank-scoped audit is a documented future item.
**Acceptance:** bank-admin granted `audit VIEW` via the matrix sees nothing (403); NC sees all.

## S-2 — Compliance scope (D17-N2)

**Current:** `ComplianceController` bank-scopes bank users via `applyScope`; CBY-side (null bank) sees all.
**Required:**
- `DataScope` replaces the ad-hoc `applyScope`: NATIONAL_COMMITTEE → system-wide; BANKING_SECTOR → own-bank; OTHER → none.
- Cross-bank compliance intelligence (duplicate-invoice grouping) **masked** for BANKING_SECTOR (S-8).
- A dedicated `compliance` capability is **not** introduced yet (rides `audit VIEW` per current) — flag as follow-up; S-2 only fixes scope.
**Acceptance:** bank user sees only own-bank duplicates/expired/SLA; NC sees cross-bank.

## S-3 — Reports + exports (D17-N3, D18-N1/N2/N3)

**Current:** `V1\ReportController::applyScope` + `GenerateReportExport` bank-scope from `bank_id`; `reports EXPORT` capability unenforced (rides VIEW); exports unaudited.
**Required:**
- `DataScope` in all report endpoints + the export job (job re-derives from stored requester — extend from bank_id to classification).
- `reports:EXPORT` capability required on export **create** and **download** (D18-N1).
- Export create + download audited with actor, org, classification, type, filters, format, row count (job writes count on completion) (D18-N2).
- BANKING_SECTOR exports never contain other institutions' data (DataScope guarantee).
**Acceptance:** bank user report = own-bank only; NC = system-wide; export without EXPORT capability → 403; exports audited.

## S-4 — Dashboard scope (D21-N2)

**Current:** `DashboardController`/`DashboardStatsService` (post-WP-R) — bank users scoped, null-bank system-wide.
**Required:**
- `DataScope` applied to every dashboard query; NATIONAL_COMMITTEE → system-wide (when screen granted); BANKING_SECTOR → own-org; OTHER → empty/default dashboard.
- Null-bank non-NC user → empty dashboard (no system-wide leak).
**Acceptance:** T-4 snapshots per role still pass for NC/system_admin; bank roles scoped; OTHER/unknown → empty.

## S-5 — Search scope (D21-N3)

**Current:** `SearchController` — requests via read-model (bank-scoped); users/banks admin-gated; customs bank-or-CBY.
**Required:**
- All groups apply `DataScope` semantics: BANKING_SECTOR → own-org data; NATIONAL_COMMITTEE → system-wide; OTHER → no results.
- fx_swift already unscoped by WP-0 BF-5; here it becomes **classification-driven** (SWIFT officers are CBY/NC-side → system-wide customs search) rather than role-list-driven.
- LIKE wildcard escaping (D21-N5) rides here: reuse `escapeLike`.
**Acceptance:** bank user search = own-org only; NC = system-wide; OTHER = empty.

## S-6 — Notification audience scope (D19-N2)

**Current:** `EngineNotificationDispatcher::resolveAuditViewers` = roles holding `audit VIEW`; cross-bank duplicate details in body unmasked.
**Required:**
- Oversight audiences require capability **and** classification (NC only for platform-wide oversight; BANKING_SECTOR receives only own-org-scoped notifications).
- Cross-bank duplicate notifications **masked** for non-NC recipients (ties to S-8).
- Audience resolution uses the R4 `StagePermissionAudience` matcher (post-WP-R) — no parallel SQL.
**Acceptance:** bank user gets own-org notifications only; NC gets oversight; duplicate body masked for banks.

## S-7 — Financing advisory scope + masking (D22-N3)

**Current:** `GET /financing/utilization` — any creator probes any (tax, invoice) aggregate.
**Required:**
- BANKING_SECTOR: query only for merchants/tax numbers within own-org scope (merchant must belong to the user's bank); aggregate-only response (used/remaining/blocked) — no other-bank names, references, users, internals.
- NATIONAL_COMMITTEE (with permission): system-wide; may receive fuller detail in a future oversight view.
- OTHER: no access by default.
**Acceptance:** bank user probing another bank's merchant tax → denied/scoped; own-bank merchant → aggregate only.

## S-8 — Duplicate-invoice masking (D17-N4)

**Current:** `DuplicateInvoiceChecker` returns `{id, reference}` of duplicates globally; dispatcher includes them in the body.
**Required:**
- For non-NC recipients (BANKING_SECTOR creators), the warning body is masked: generic "possible duplicate exists at another institution" — no institution name, reference, user, internal details.
- NC users with compliance permission see full cross-bank detail.
- Masking decision point: in `EngineRequestController` (response shaping) + dispatcher body, based on the actor's classification.
**Acceptance:** bank creator sees masked warning; NC sees full duplicates.

## S-9 — Invoice normalization upgrade + backfill (D22-N5 step 2)

**Current (post-WP-R):** `InvoiceKey::normalize()` = trim; wired to ledger only; checker/projection tagged TODO.
**Required (step 2):**
- Helper upgraded: trim + uppercase + collapse repeated internal spaces (D17-N4).
- Wired into: `DuplicateInvoiceChecker`, `RequestProjectionSync` (indexed `invoice_number`), financing ledger, advisory endpoint — one shared helper everywhere.
- **Backfill migration:** normalize existing projected `invoice_number` values in `engine_requests` (logged, PR-reviewed); originals preserved for display/audit (store original separately if not already).
**Acceptance:** `INV-1`, `inv-1`, `INV 1` collapse to one key across all consumers; existing data normalized.

## S-10 — Inactive team/role filtering (R4 step 4 / D8-N1)

**Current (post-WP-R):** `StagePermissionAudience` + `StagePermissionResolver::identityFor` include inactive teams/roles; T-3 pins this.
**Required:**
- `identityFor` filters `teams()->where('is_active', true)`, `roles()->where('is_active', true)`.
- `StagePermissionAudience` matches the same (the R4 comparative CI test enforces equivalence).
- **T-3 pins flip:** inactive-role and inactive-team cases now assert no-match.
**Acceptance:** deactivated team/role immediately stops granting stage access + derived capabilities; comparative test green.

---

## Business rules (consolidated — the two-layer model)

1. Screen/capability permission gates **feature access**; organization classification bounds **data scope**. They never substitute for each other.
2. `DataScope` is the single data-bounding primitive; every read surface consumes it.
3. Null `bank_id` never implies system-wide; system-wide comes only from NATIONAL_COMMITTEE classification + capability.
4. Cross-institution signals are masked for BANKING_SECTOR recipients; full detail is NC-only (compliance permission).
5. Inactive teams/roles grant nothing immediately on deactivation.
6. Exports require EXPORT capability (separate from VIEW) and are audited.

## Error cases

| Case | Response |
|------|----------|
| BANKING_SECTOR user accesses platform-wide audit | 403 (policy denies) |
| Export without EXPORT capability | 403 |
| BANKING_SECTOR user probes another bank's merchant utilization | 403 / scoped empty |
| Out-of-scope data in any scoped query | empty result (no existence leak where possible) |

## Acceptance criteria

1. `DataScope` exists and is consumed by audit, compliance, reports+exports, dashboard, search, notification audiences, financing advisory.
2. Per-surface characterization tests: NC system-wide, BANKING_SECTOR own-org, OTHER empty, null-bank-non-NC empty.
3. Exports EXPORT-capability-gated + audited; bank exports contain no other-institution data.
4. Duplicate-invoice warnings masked for banks; full for NC.
5. Invoice normalization unified + backfilled; `INV-1`/`inv-1`/`INV 1` collapse.
6. T-3 inactive-team/role pins flip green; comparative audience test green.
7. All WP-0 suites otherwise green; T-4 dashboard snapshots hold for NC/system_admin.

## Test cases

- **Unit (`DataScope`):** classification → scope matrix; null-bank handling.
- **Feature (per surface):** NC vs BANKING_SECTOR vs OTHER vs null-bank-non-NC data visibility; export capability + audit; masking; normalization collapse.
- **Audience (R4 comparative):** inactive team/role no-match; active matches; all-null-skip preserved.
- **Migration:** invoice-number backfill on seeded data.

## Manual verification steps

1. Grant a bank role `audit VIEW` via matrix → still no audit access (NC-only).
2. Bank user reports → own-bank only; NC → system-wide.
3. Export as bank user → own-bank rows only; without EXPORT capability → 403.
4. Bank creator submits duplicate invoice → masked warning; NC compliance user → full duplicates.
5. Deactivate a team holding EXECUTE → its members immediately lose stage access + queue.
6. `INV-1` vs `inv-1` → treated as one invoice in financing + duplicate detection.

## Rollback considerations

`DataScope` is additive; per-surface adoption reverts independently. S-9 backfill is non-destructive (additive normalized column / preserved original). S-10 flip is the one that changes live authorization — revert restores inactive-team/role grants (document). High-risk package → ship behind feature flags per surface if the team prefers staged rollout.

## Open questions

1. **S-1 bank-scoped audit:** WP-7 denies audit to BANKING_SECTOR entirely. If a future bank-admin audit need appears, it's a separate bank-scoped view of safe events — confirm deny-by-default is acceptable now.
2. **S-2 compliance capability:** WP-7 keeps compliance on `audit VIEW`. Confirm a dedicated `compliance` capability is a follow-up, not this package.
3. **S-9 original-value preservation:** confirm `engine_requests.invoice_number` holds the original (display/audit) and a separate normalized column is added for matching — or normalize in place and store original elsewhere. Recommend separate normalized column.

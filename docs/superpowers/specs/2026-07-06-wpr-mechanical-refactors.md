# WP-R — Mechanical Refactors

**Status:** Draft for review (Phase 6)
**Source of authority:** `2026-07-05-feature-review-notes.md` — Phase 5 register (R1, R2, R3s1, R5s1, R6, R4 steps 1–3)
**Traceability:** R1 (D21-N7), R2 (enables D9/D10/D11/D3-N1), R3 step 1 (D12-N4 prep), R5 step 1 (D22-N5 prep), R6 (D3-N5/D15-N1 prep), R4 steps 1–3 (D19-N3)
**Dependencies:** WP-0 complete and green — its suites are the equivalence oracles (T-4 for R1, T-3 for R4, T-1/T-2/T-5 as general regression floor).
**Overall risk:** low. Every item is behavior-preserving; the one judgment call (R5 wiring depth) is resolved conservatively below and flagged for review.

## Change classification

Every item in this package: **behavior-preserving refactor**. No functional change, no bug fix, no migration. R4 step 4 (inactive role/team filtering, D8-N1) is **explicitly excluded** → WP-7.

## Ground rules

- One refactor = one reviewable commit series; commit messages carry the R-number and the classification.
- Verification ladder per refactor: focused test file/filter for the touched behavior + touched-file Pint/ESLint; full suites only at package end.
- Do-not-touch list applies (`EngineTransitionService::execute` internals, `EngineFinancingLedger` locking, `AuditLog` guards, `TemplateRenderer` chain, publish auto-supersede). R2 moves controller-layer code only; it does not reach into those services.
- No frontend changes in this package (R10 rides later waves by decision).

---

## R1 — Extract `DashboardController` aggregates

**Current shape:** `backend/app/Http/Controllers/Api/DashboardController.php` (608 lines) — eight role-branch private methods with inline aggregate queries, bucket math, and month-series logic.
**Target shape:** `App\Services\Dashboard\DashboardStatsService` holding the per-role methods verbatim (same names, same payload arrays); controller reduces to role dispatch + `ApiResponse::success`. One service class, not eight — KISS; later WP-12/WP-2 changes land inside it.
**Method:** move method bodies unmodified (including the backward-compat fields and the documented timezone-safe month grouping); inject the service; delete nothing else.
**Equivalence oracle:** WP-0 T-4 snapshots must be byte-identical before/after.
**Risk:** low. Pure reads. **Rollback:** revert the extraction commits.
**Acceptance:** T-4 green untouched; controller ≤ ~60 lines; no query count change for any role (assert with Laravel query log in one sanity test if cheap).

## R2 — Split `EngineRequestController`

**Current shape:** `backend/app/Http/Controllers/Api/V1/EngineRequestController.php` (707 lines): lifecycle + documents + claims + FX downloads + filter/SLA SQL + pagination helpers.
**Target shape (same URIs, handlers re-pointed in `routes/api.php`):**
- `EngineRequestController` — `store`, `availableWorkflows`, `show`, `formSchema`, `index`, `myQueue`, `executeAction`, `draft`, `history`, `graph`.
- `EngineRequestDocumentController` — `listDocuments`, `uploadDocument`, `downloadDocument`, `deleteDocument` (+ `documentResource` helper).
- `EngineRequestClaimController` — `claim`, `heartbeatClaim`, `releaseClaim`.
- `EngineFxConfirmationController` — `uploadSignedFx`, `downloadCustomsDeclaration`, `downloadSignedFxDoc`.
- Shared query concerns (`applyFilters`, `applySlaStatusFilter`, `perPage`, `paginatedResponse`, `ALLOWED_STATUSES`) → `App\Support\EngineRequestListQuery` (single reusable object) consumed by list endpoints. SLA SQL expressions stay on the `EngineRequest` model where they already live.
**Method:** verbatim moves; identical middleware/throttles/authorize calls; no signature changes.
**Equivalence oracle:** `php artisan route:list` diff shows **zero** URI/method/middleware changes (only action class names); existing engine feature tests green; T-1 claim suite green.
**Risk:** low-medium (route wiring mistakes are loud, not silent). **Rollback:** revert; route file is the single integration point.
**Acceptance:** all four controllers < ~250 lines; no endpoint contract change; full engine feature-test file set green.

## R3 (step 1 only) — `PasswordPolicy` extraction

**Current shape:** identical complexity stack (`min:8` + upper/lower/digit regex + messages) inlined in five places: `AuthController::resetPassword`, `ChangePasswordRequest`, `ProfileController::changeTemporaryPassword`, `V1UserController::validateIdentity`, `V1UserController::resetPassword`.
**Target shape:** `App\Support\PasswordPolicy` exposing `rules(): array` (the exact current base rules: `'string','min:8','regex:/[A-Z]/','regex:/[a-z]/','regex:/[0-9]/'`) and `messages(): array` (current wording). Call sites compose their own modifiers (`required`/`nullable`/`confirmed`/current-password closures) around it — those differ legitimately per flow and stay in place.
**Explicit non-goals:** no rule changes, no config, no history/blacklist — that is D12-N4 step 2 (WP-6).
**Equivalence oracle:** existing auth/password/user feature tests; identical validation messages asserted.
**Risk:** minimal. **Rollback:** revert; call sites regain inline arrays.
**Acceptance:** five call sites consume the helper; grep finds no remaining inline copy of the regex trio in `app/`.

## R5 (step 1 only) — `InvoiceKey` normalization helper

**Current shape:** `EngineFinancingLedger::normalizeKey()` (trim) is the only normalization; `DuplicateInvoiceChecker` compares raw values; `RequestProjectionSync` stores raw values.
**Target shape:** `App\Support\InvoiceKey::normalize(string): string` (trim — current ledger semantics). `EngineFinancingLedger` delegates to it (keeping its static method as a thin alias or replacing call sites).
**Conservative deviation (flagged for reviewer):** Phase 5 wording said "wire ledger, duplicate checker, and projections." Wiring trim into the checker/projection **is a behavior change** (whitespace variants would start matching / stored values would change), so under the behavior-preserving rule this step wires **the ledger only** and tags `DuplicateInvoiceChecker` + `RequestProjectionSync` call sites with `// TODO(WP-7 / R5 step 2, D17-N4 + D22-N5)` markers. Step 2 in WP-7 wires them together with the uppercase/collapse upgrade and the projection backfill, as one approved functional change.
**Equivalence oracle:** financing ledger unit/feature tests; behavior byte-identical.
**Risk:** minimal. **Rollback:** trivial.
**Acceptance:** helper exists with tests; ledger delegates; the two tagged call sites unchanged in behavior.

## R6 — `RoleCodes` constants

**Current shape:** bare strings (`'system_admin'`, `'bank_admin'`, `'intake'`, `'internal_reviewer'`, `'support'`, `'fx_swift'`, `'committee_manager'`, `'committee_director'`, `'fx_confirm'`) across ~15 files: policies (`UserPolicy`, `CustomsDeclarationPolicy`, …), `PermissionService`, `DashboardController`/`DashboardStatsService`, `SearchController`, `V1UserController::legacyRoleFor`, `AuthController`-adjacent checks, seeders.
**Target shape:** `App\Support\RoleCodes` final class with string constants (not a backed enum — role rows are DB-driven; the class documents *system anchor* codes, including currently-suspect ones like `COMMITTEE_DIRECTOR`, each with a doc-comment noting its D-note status). Sweep replaces every literal in `app/` (seeders included where straightforward).
**Why now:** makes WP-10's `committee_director` resolution and D14-N2 migration a find-usages exercise instead of a grep hunt.
**Equivalence oracle:** identical strings — full existing suite green; no assertion changes.
**Risk:** minimal (typo risk is what the constants eliminate). **Rollback:** revert.
**Acceptance:** grep for quoted role-code literals in `app/` returns only the constants class (tests/seeders may keep literals where they intentionally assert raw values).

## R4 (steps 2–3) — Canonical audience matching

**Step 1 (T-3) is delivered by WP-0.**
**Current shape:** `EngineNotificationDispatcher::resolveExecuteHolders()` re-implements stage-permission row matching as an inverse SQL query (rows → users), parallel to `StagePermissionResolver` (user → rows). Documented divergences: all-null-row skip (dispatcher-only, deliberate anti-fanout guard) and shared inactive-role/team blind spot.
**Target shape (step 2):** extract the inverse query into `App\Services\Workflow\StagePermissionAudience` — single class owning "which users match these EXECUTE rows," used by the dispatcher. The all-null-skip guard moves with it and stays (it is current behavior). `StagePermissionResolver` remains the per-user gate; per D19-N3, an optimized inverse query is acceptable **only** while proven semantically equivalent.
**Step 3 (equivalence proof):** extend T-3 into a comparative property: for every fixture in the matrix, assert `StagePermissionAudience` results == the set produced by evaluating `StagePermissionResolver::identityMatchesAny` per candidate user — with the all-null-row case asserted as the one documented, deliberate divergence. This comparative test stays in CI permanently as drift protection.
**Step 4 is NOT here:** inactive role/team filtering (D8-N1) lands in WP-7 as a functional commit that flips the pinned T-3 expectations.
**Risk:** medium in theory, controlled by the matrix. **Rollback:** dispatcher regains its inline query.
**Acceptance:** dispatcher contains no matching logic of its own; comparative test green; pinned inactive-case expectations untouched.

---

## Flows, inputs, outputs

No endpoint, contract, schema, or payload changes anywhere in this package. `route:list` equality (R2) and snapshot equality (R1) are hard acceptance gates.

## Package acceptance criteria

1. All WP-0 suites green, unmodified (except R4's comparative extension of T-3, which only adds assertions).
2. `route:list` URI/method/middleware diff empty.
3. T-4 dashboard snapshots identical.
4. No new behavior anywhere — no D-note may be "accidentally implemented" here.
5. Each refactor merged as its own labeled commit series; full backend suite green at package end (this package qualifies as a broad refactor → full-suite run justified).

## Manual verification steps

1. Log in per role; open dashboard — numbers match pre-refactor values on the same data.
2. Run one full engine request flow (create → draft → claim → transition → documents → history/graph) — unchanged.
3. Change a password via profile — same validation messages.
4. Trigger a transition notification — same recipients as before (spot-check via notifications table).

## Rollback considerations

Each R-item reverts independently; R2 is the only one touching `routes/api.php`, making it the single file to watch in any partial rollback.

## Open questions

None. **R5 wiring depth RESOLVED (review 2026-07-06):** stricter behavior-preserving interpretation approved — ledger-only wiring in WP-R; duplicate-checker and projection wiring + normalization upgrade + backfill stay in WP-7/R5s2. No trimming of duplicate-checker input in WP-R.

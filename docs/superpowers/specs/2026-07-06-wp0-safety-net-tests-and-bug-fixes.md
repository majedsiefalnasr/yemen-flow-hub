# WP-0 — Safety Net: Tests + Confirmed Bug Fixes

**Status:** Draft for review (Phase 6)
**Source of authority:** `2026-07-05-feature-review-notes.md` (Phase 2–5 decisions)
**Traceability:** D23-N1, D7-N1, D13-N3, D16-N4, D21-N4, D19-N1 (partial); Phase-5 pre-refactor test prerequisites 1–5
**Dependencies:** none (first package). Everything later builds on this.
**Overall risk:** minimal — additive tests + narrow, individually revertible fixes.

## Change classification

| Item | Kind |
|------|------|
| T-1 … T-5 test suites | Tests (characterization + red-first regression) |
| BF-1 bank stale-relation 500 | Bug fix |
| BF-2 stage-permission partial-update bypass | Bug fix (D7-N1 approved) |
| BF-3 active_sessions_count scope leak | Bug fix |
| BF-4 immutable-key updates → 422 | Bug fix (D16-N4 approved) |
| BF-5 fx_swift customs-search scope | Approved functional change (D21-N4 preferred decision) |
| BF-6 notification action URLs | Bug fix + data migration (D19-N1) |

**Explicitly out of scope (later packages):** D15-N5 bank suspend-vs-delete semantics (WP-9); D8-N1 inactive team/role filtering (WP-7); D9-N1/N2 claim behavior changes (WP-5); classification scoping (WP-7); any refactor (WP-R).

---

## Part A — Test suites

General rules: tests pin **current** behavior; cases known to change later are annotated `@see WP-<n>` in a comment and grouped so the later package flips expectations deliberately, not accidentally. Backend tests are PHPUnit feature/unit tests run via focused filters (verification ladder). No shadcn-vue components are downgraded for testability (repo rule).

### T-1 — Claim lifecycle races (`tests/Feature/Workflow/EngineClaimLifecycleTest.php`, new)

Characterizes `EngineClaimService` + claim checks in `EngineTransitionService`:

1. Claim on unclaimed request succeeds; sets holder/`claimed_at`/`claim_expires_at` (TTL from config).
2. Claim while held by another (unexpired) → 409 `STAGE_CLAIMED`.
3. Holder re-claim extends `claim_expires_at`, preserves original `claimed_at`.
4. Claim on **expired** claim succeeds (expired = free).
5. Heartbeat by holder extends TTL; by non-holder → 403 `CLAIM_NOT_HELD`.
6. Release by holder clears fields; by non-holder → 403; by `system_admin` succeeds (override) and is audited.
7. Sweeper (`releaseExpired`) releases an expired claim with audit reason `ttl_expired`; **no-ops** when the claim was extended between scan and lock.
8. Transition on a `requires_claim` stage without holding the claim → 403 `CLAIM_NOT_HELD`; with claim held → succeeds.
9. **Pin, flips in WP-5 (D9-N1):** after a successful transition, claim fields currently persist (carry-over).
10. **Pin, flips in WP-5 (D9-N2):** draft save on a `requires_claim` stage by a non-holder executor currently succeeds.

### T-2 — Unused-bank guard regression (`tests/Feature/Admin/BankLifecycleGuardTest.php`, new; red-first for BF-1)

1. Bank with **no** users, merchants (incl. soft-deleted), or engine requests: `POST /v1/banks/{id}/deactivate` → 200; `DELETE /v1/banks/{id}` → 204. **Currently 500** (`BadMethodCallException`) — written red, green after BF-1.
2. Bank with a user → deactivate/delete blocked 422 `BANK_IN_USE`.
3. Bank with only a soft-deleted merchant → blocked 422 (withTrashed guard preserved).
4. Bank with an engine request (any status) → blocked 422 (current semantics; WP-9 later splits suspend vs delete per D15-N5).

### T-3 — Audience-resolution characterization matrix (`tests/Unit/Notifications/AudienceResolutionTest.php`, new; feeds R4)

Fixtures exercising `EngineNotificationDispatcher::resolveExecuteHolders` and, comparatively, `StagePermissionResolver::identityMatchesAny` on the same data:

1. Org-only EXECUTE row → all active users of the org match.
2. Org+team row → only team members of that org.
3. Org+role row → only role holders of that org.
4. Org+team+role row → intersection.
5. User-specific row → that user only.
6. All-null row → **skipped** by the dispatcher (no fan-out) while the resolver treats it as match-everyone — documented divergence, pinned.
7. Inactive **user** → excluded by dispatcher (pinned ✔).
8. **Pin, flips in WP-7 (D8-N1):** inactive **role** and inactive **team** currently still match in both implementations.
9. VIEW-level rows never selected by the EXECUTE audience query.

### T-4 — Dashboard response snapshots (`tests/Feature/Dashboard/DashboardStatsSnapshotTest.php`, new; feeds R1)

Seeded fixture set (one bank, requests across buckets, users per role code). For each role code — `intake`, `internal_reviewer`, `bank_admin`, `support`, `fx_swift`, `committee_manager`, `committee_director`, `system_admin`, plus a no-role default — assert the **full JSON structure and values** of `GET /dashboard/stats`. These snapshots are the equivalence oracle for the R1 extraction.

### T-5 — Stage-permission partial-update consistency (`tests/Feature/Designer/StagePermissionConsistencyTest.php`, new; red-first for BF-2)

1. Create permission row (org A, team A1). Update sending **only** `team_id` = team of org B → expect 422 `team_id` error. **Currently passes** (bug) — red, green after BF-2.
2. Same for `role_id`-only and `user_id`-only cross-org updates.
3. Legitimate partial updates (label-only, access-level-only, same-org team change) still succeed.
4. Full update with consistent org+team+role still succeeds.

---

## Part B — Bug fixes

### BF-1 — Remove stale `importRequests()` check (D23-N1)

**Current:** `backend/app/Http/Controllers/Api/V1/BankController.php` → `isUsed()` calls `$bank->importRequests()->exists()`. The `ImportRequest` model/relation/tables were removed in P5 (`2026_07_01_000001_p5_drop_legacy_import_request_tables.php`); `Bank` defines no such relation → `BadMethodCallException` → 500 whenever the earlier `||` conditions are all false (exactly the unused-bank case).
**Required:** delete the `importRequests()` term. Guard becomes `users()->exists() || merchants()->withTrashed()->exists() || engineRequests()->exists()`. No other semantic change (suspend/delete split is WP-9).
**Error cases:** blocked banks keep returning 422 `BANK_IN_USE`.
**Acceptance:** T-2 green; no `ImportRequest`/`importRequests` reference remains in `BankController`; existing bank tests unaffected.
**Rollback:** single-line revert.

### BF-2 — Effective-row validation for stage-permission updates (D7-N1)

**Current:** `UpdateStagePermissionRequest::after()` runs `StagePermissionConsistency::check($validator, $this->all())`; with a partial payload lacking `organization_id`, the check returns early → cross-org `team_id`/`role_id`/`user_id` attachable.
**Required:** in the update request, build the **effective row** — submitted fields merged over the existing `StagePermission` model attributes (`organization_id`, `team_id`, `role_id`, `user_id`) — and pass that to `StagePermissionConsistency::check`. Store request unchanged (org already required there).
**Validation rules:** unchanged messages; failures attach to the offending field; 422.
**Permission rules:** unchanged (designer capability).
**Edge cases:** clearing a field with explicit `null` participates in the merge as null (allowed); update that changes `organization_id` itself validates team/role/user against the **new** org.
**Acceptance:** T-5 green; no cross-org combination reachable via store or update.
**Impacted:** `backend/app/Http/Requests/UpdateStagePermissionRequest.php` (+possibly a small signature addition on `StagePermissionConsistency` to accept effective values).
**Rollback:** revert request class.

### BF-3 — `active_sessions_count` scope leak (D13-N3)

**Current:** `ProfileController::show()` — `$user->tokens()->whereNull('last_used_at')->orWhere('last_used_at', '>', now()->subHours(24))->count()`; the ungrouped `orWhere` escapes the tokenable constraint → counts other users' recent tokens.
**Required:** group the condition: `$user->tokens()->where(fn ($q) => $q->whereNull('last_used_at')->orWhere('last_used_at', '>', now()->subHours(24)))->count()`.
**Acceptance:** unit/feature test: two users with tokens; each profile counts only its own. Count semantics otherwise unchanged (unused-or-recently-used within 24h).
**Impacted:** `backend/app/Http/Controllers/Api/ProfileController.php`.
**Rollback:** single-expression revert.

### BF-4 — Immutable keys rejected at validation (D16-N4)

**Current:** `UpdateReferenceTableRequest` / `UpdateReferenceValueRequest` accept `key => sometimes|string`; a changed key then hits the model `LogicException` → 500.
**Required:** replace with the `prohibitedIf`-changed pattern already used for `reference_table_id`: `'key' => ['sometimes', Rule::prohibitedIf(fn () => $this->input('key') !== $model->key)]` (per request class, resolving the routed model). Clear 422 message: key is immutable after creation. Model guards remain as defense-in-depth.
**Edge cases:** sending the identical unchanged key remains valid (idempotent clients).
**Acceptance:** tests: changed key → 422 with field error; unchanged key → 200; model guard still throws if bypassed internally.
**Impacted:** both update request classes; small tests.
**Rollback:** revert rules.

### BF-5 — SWIFT officers not bank-scoped in customs search (D21-N4 — **approved functional bug fix**)

**Current:** `SearchController::searchCustoms()` includes `fx_swift` in the bank-scoped role list; SWIFT officers have `bank_id = null` → `where('bank_id', null)` matches nothing → always empty results.
**Required:** remove `fx_swift` from the bank-scoped list; SWIFT officers receive unscoped (CBY-wide) declaration results — the D21-N4 preferred decision. Bank-side roles (`intake`, `internal_reviewer`, `bank_admin`) stay bank-scoped.
**Scope boundary (explicit):** BF-5 fixes the confirmed fx_swift null-bank scoping bug **only**. Broader organization-classification scoping of search/dashboards remains out of scope for WP-0 and belongs to WP-7.
**Data-scope note:** widens fx_swift from "accidentally nothing" to CBY-wide — approved explicitly in D21-N4.
**Acceptance:** feature test: fx_swift user searching a declaration number gets results across banks; bank roles still see own-bank only.
**Impacted:** `backend/app/Http/Controllers/Api/SearchController.php`.
**Rollback:** re-add the role to the list.

### BF-6 — Notification action URLs (D19-N1)

**Current:** `EngineNotificationDispatcher` emits `actionUrl: "/requests/{id}"` in `afterTransition`, `afterDuplicateInvoice`, `afterSlaSignal`; those routes were deleted in the G11 cutover. `NotificationTemplateController::sampleVariables()` uses the same stale URL shape. No frontend mapping of old URLs exists.
**Required:**
1. Dispatcher emits `"/workflows/instances/{$requestId}"` at all three call sites.
2. Sample variables/preview updated to the engine route shape.
3. **Data migration (confident mapping):** rewrite stored `notifications` rows where `entity_type = 'engine_request'` and `action_url` LIKE `'/requests/%'` to `'/workflows/instances/' || entity_id`. Rows without a mappable entity are left untouched (historical). Migration is idempotent and logged (row count).
**Acceptance:** dispatcher unit tests assert the new URL; migration test on seeded stale rows; no new notification may carry a `/requests/` URL (assert in dispatcher tests).
**Impacted:** `backend/app/Services/Notifications/EngineNotificationDispatcher.php`, `backend/app/Http/Controllers/Api/Admin/NotificationTemplateController.php`, one new migration.
**Rollback:** code revert; migration keeps a `notifications` URL rewrite that remains valid either way (new URLs are the correct ones); no destructive data change.

---

## Flows, inputs, outputs

No new endpoints, no schema changes except the BF-6 data rewrite. All request/response contracts unchanged apart from: 500→422 (BF-4), 500→2xx on unused banks (BF-1), 422-on-cross-org (BF-2), corrected count value (BF-3), non-empty results for fx_swift (BF-5), corrected URLs (BF-6).

## Acceptance criteria (package)

1. All five test suites exist, run green via focused filters, and are wired into the normal test run.
2. T-2 and T-5 were demonstrably red before their fixes (test-first evidence in commit order).
3. All six fixes merged with their classification stated in commit messages.
4. No behavior outside the six fixes changed — T-1/T-3/T-4 characterization pins prove it.
5. Pinned-to-flip cases carry `@see WP-5` / `@see WP-7` annotations.

## Manual verification steps

1. As admin: create a bank with no users/merchants → deactivate → delete. No 500.
2. Designer: edit a stage permission changing only the team to another org's team → clear validation error.
3. Profile page: session count plausible for own account (compare against `personal_access_tokens` rows).
4. Reference data: attempt key edit via API → 422, not 500.
5. As a SWIFT officer: global search for a known declaration number → result appears; click-through works.
6. Trigger any transition → open the notification → lands on `/workflows/instances/{id}`.

## Rollback considerations

Each fix is an independent commit; any can be reverted alone. The BF-6 migration is non-destructive (URL rewrite toward the valid route). Test suites never need rollback.

## Open questions

None — all behavior decided in the recorded D-notes.

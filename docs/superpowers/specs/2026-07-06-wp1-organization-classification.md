# WP-1 — Organization Classification Foundation

**Status:** Draft for review (Phase 6)
**Source of authority:** `2026-07-05-feature-review-notes.md`
**Traceability:** D14-N4 (classification model), D7-N6 (creation gating — supersedes D1-N3/D1-N5 phrasing, intent unchanged), D7-N8 (null-org exclusion), D15-N2 (hardcode replacement), D1-N3 (frontend relies on backend capability)
**Dependencies:** WP-0 green. WP-R helpful (R6 constants) but not required.
**Enables:** WP-7 (two-layer visibility), parts of WP-3 (validator) and WP-8.
**Overall risk:** medium — touches user/bank validation and the request-creation gate.

## Change classification

| Item | Kind |
|------|------|
| C-1 schema + enum + backfill migration | Migration + approved functional (D14-N4) |
| C-2 org CRUD/API classification field | Approved functional (D14-N4) |
| C-3 hardcode replacement (`commercial_banks`) | Approved functional (D15-N2) |
| C-4 runtime creation gating | Approved functional (D7-N6, D1-N5) |
| C-5 designer + publish gating for initial-stage grants | Approved functional (D7-N6) |
| C-6 null-org exclusion from stage matching | Approved functional (D7-N8) |
| C-7 frontend: capability-driven create affordance | Approved functional (D1-N3) |

**Explicitly out of scope:** all read-surface scoping (audit/reports/search/dashboards/notifications/advisory — WP-7); the broader validator pack (WP-3); bank→financial-institution model rename (future note in D15-N2); role-model migration (WP-10).

---

## C-1 — Schema, enum, migration

**Current:** `organizations` has `code, name, is_system, is_active, version` — no classification. Business meaning is inferred from the hardcoded `commercial_banks` org code.
**Required:**
- `App\Enums\OrganizationClassification` backed string enum: `BANKING_SECTOR`, `NATIONAL_COMMITTEE`, `OTHER`. Controlled application enum by decision (never reference data).
- `organizations.classification` column (string, indexed), **NOT NULL** after backfill.
- **Backfill migration:** `commercial_banks` → `BANKING_SECTOR`; the CBY / National Committee organization code(s) (confirm exact seed codes at implementation) → `NATIONAL_COMMITTEE`; every other row → `OTHER`. Migration logs the resulting mapping (org id, code, classification). The mapping list must be reviewed in the PR.
- Model: fillable + enum cast; `OrganizationResource` exposes `classification`.
**Edge cases:** fresh installs — seeder sets classification explicitly; no default that hides a decision (`OTHER` only via explicit mapping/backfill, not column default).
**Rollback:** drop column + enum; all gating code sits behind it in separate commits.

## C-2 — Organization CRUD carries classification

**Current:** create = code+name; update = rename-only.
**Required:**
- `StoreOrganizationRequest`: `classification` required, `Rule::enum(OrganizationClassification::class)`.
- `UpdateOrganizationRequest`: `classification` optional (`sometimes`) — editable by the same designer/admin authority as other org fields; audited before/after (existing audit pattern covers it once fillable).
- **Consistency guard (APPROVED with clarification, review 2026-07-06):** classification is **guarded-mutable, not immutable** — legitimate correction/migration cases allowed. Changing classification **away from** `BANKING_SECTOR` is rejected (422 `ORGANIZATION_CLASSIFICATION_IN_USE`) while the org is referenced by any EXECUTE grant on an **initial stage of a PUBLISHED workflow version**. Reclassification is **audited with old/new values**, and the UI warns clearly that classification affects request creation and future data scope. (Broader impact previews arrive with WP-9 lifecycle guards.)
- Frontend `admin/orgs.vue`: required classification select (Arabic labels: القطاع المصرفي / اللجنة الوطنية / أخرى), shown in the table.
**Errors:** standard validation 422; guard error uses the governance error envelope with the code above.

## C-3 — Replace `commercial_banks` hardcodes

**Current:**
- `V1UserController::validateIdentity()` — `if ($organization->code === 'commercial_banks')` → bank required; else bank nulled.
- `V1BankController::store()` — auto-assigns `organization_id` from the `commercial_banks` code lookup.
**Required:**
- User validation keys on `classification === BANKING_SECTOR`: such users require a valid `bank_id` belonging to their organization; non-BANKING_SECTOR users get `bank_id` nulled. Error code/message unchanged in spirit (`BANK_REQUIRED`).
- Bank creation: `organization_id` becomes an explicit validated input restricted to BANKING_SECTOR organizations (`Rule::exists` + classification check) — no silent code lookup. Frontend `admin/banks.vue` gains the org select (defaulting to the single existing banking org).
**Edge cases:** multiple BANKING_SECTOR orgs (exchange companies later) now work without code changes — the point of D15-N2.
**Acceptance:** grep finds no `'commercial_banks'` literal in `app/` outside migrations/seeders.

## C-4 — Runtime creation gating

**Current:** `EngineRequestService::create()` allows any actor with EXECUTE on the initial stage; `bank_id` resolvable from the request payload for bank-less actors; `availableWorkflows` filters only by initial-stage EXECUTE.
**Required:**
1. `create()` gate, before the permission check: actor's organization must be `BANKING_SECTOR` **and** actor `bank_id` non-null → else 403 `CREATION_NOT_ALLOWED_FOR_ORGANIZATION` (new `EngineException`).
2. `bank_id` **removed** from `StoreEngineRequestRequest` (D1-N5): resolved bank = actor's bank, always. Merchant scope check now always applies (bank never null for creators).
3. `availableWorkflows` applies the same gate → non-BANKING_SECTOR users receive an empty list.
4. Derived `requests CREATE` capability (`PermissionService::derivedRequestsCapabilities*`): `add` is granted only when the role's/user's organization is BANKING_SECTOR — chrome matches enforcement. (VIEW/UPDATE derivation untouched — visibility is WP-7.)
**Inputs/outputs:** `POST /v1/engine-requests` loses the optional `bank_id` field — contract change, announce in release notes; no known consumer sends it (UI never did).
**Edge cases:** BANKING_SECTOR user without a bank (should be prevented by C-3 validation, but legacy rows may exist) → gate fails with the same 403; unclassified orgs cannot exist post-migration (NOT NULL).
**Pinned tests to flip:** none — WP-0 didn't pin creation-by-CBY (it was never exercised in tests as an approved behavior).

## C-5 — Designer + publish gating for initial-stage grants

**Current:** stage permissions accept any organization; nothing constrains initial-stage EXECUTE audiences.
**Required:**
1. **Authoring-time:** `Store/UpdateStagePermissionRequest` (effective-row semantics from BF-2) reject EXECUTE rows on a stage with `is_initial = true` whose organization is not BANKING_SECTOR → 422, field `organization_id`, message stating the classification rule. (VIEW rows unrestricted — internal oversight may view.)
2. **Stage-flag edge:** marking a stage `is_initial = true` (create/update stage) when it already carries non-BANKING_SECTOR EXECUTE rows → 422 on the stage request (same invariant from the other direction).
3. **Publish-time (validator rule, lands here not WP-3 because it depends on classification):** `INITIAL_STAGE_NON_BANKING_EXECUTOR` — any EXECUTE permission row on the initial stage whose org classification ≠ BANKING_SECTOR blocks publish with a field-tagged error. Covers pre-existing drafts authored before this rule.
**Permission rules:** unchanged authorship authority (workflow_designer capability).
**Coordination note:** WP-3 adds the wider validator pack; this rule ships first and WP-3 must not duplicate it.

## C-6 — Null-org users excluded from stage matching

**Current:** `StagePermissionResolver::identityFor()` yields `organization_id: null`; org-anchored rows already fail to match, but an all-null legacy row would match everyone including org-less users; `derivedRequestsCapabilitiesForUser` mirrors this.
**Required:** identities with `organization_id === null` match **no** stage permission rows: early no-match in the resolver evaluation and in `derivedRequestsCapabilitiesForUser`. Bootstrap/system accounts keep working through policy-level bypasses (`system_admin` view widening), never through stage routing.
**Tests:** extend the T-3 matrix: null-org user vs org row / all-null row → no match (new pinned-forever expectations).
**Edge cases:** if any real operational user has a null org, this *removes* their workflow access — migration must verify no active user with workflow duties has `organization_id IS NULL` (pre-flight check query in the PR; failures resolved by data fix, not code).

## C-7 — Frontend: capability-driven create affordance

**Current:** `workflows/new.vue` gates on `auth.currentRole === UserRole.DATA_ENTRY` (hardcoded, contradicts the metadata model — D1-N3).
**Required:** gate on the derived screen capability (`can('requests','CREATE')` via `useScreenPermissions`) which now embeds the classification rule (C-4.4). The role enum check is removed. Non-creators keep the existing "غير مصرح" empty state. Backend remains the authority (C-4.1).
**Behavior note:** for existing data this is equivalence (only DATA_ENTRY bank users hold initial EXECUTE today); the mechanism, not the outcome, changes.

---

## Business rules (consolidated)

1. Every organization has exactly one classification from the closed enum; unclassified state unrepresentable post-migration.
2. Only BANKING_SECTOR organizations' users, holding a bank link, create/submit requests — enforced at service, capability, designer, and publish layers.
3. NATIONAL_COMMITTEE / OTHER cannot create requests unless a future rule explicitly allows it.
4. Null-org users participate in no stage routing.
5. Classification is security-relevant configuration: enum in code, audited edits, guarded transitions — never free-form reference data.

## Error cases

| Case | Response |
|------|----------|
| Create/update org without/with invalid classification | 422 validation |
| Classification change violating published initial grants | 422 `ORGANIZATION_CLASSIFICATION_IN_USE` |
| Non-BANKING_SECTOR user creates request | 403 `CREATION_NOT_ALLOWED_FOR_ORGANIZATION` |
| BANKING_SECTOR creator without bank | 403 same code |
| EXECUTE grant for non-banking org on initial stage | 422 field error |
| Publish with violating grant | 422 `WORKFLOW_VALIDATION_FAILED` + `INITIAL_STAGE_NON_BANKING_EXECUTOR` |

## Acceptance criteria

1. Migration classifies every existing org; mapping logged and PR-reviewed; column NOT NULL.
2. Creation gate matrix green: BANKING_SECTOR+bank → allowed; BANKING_SECTOR w/o bank, NATIONAL_COMMITTEE, OTHER, null-org → 403; `availableWorkflows` empty for non-creators; `bank_id` payload field rejected/ignored per removed contract.
3. Designer + publish gates block the invariant from all three directions (grant row, stage flag, publish).
4. `requests CREATE` chrome matches enforcement for every classification.
5. No `'commercial_banks'` literal outside migrations/seeders.
6. All WP-0 suites remain green (no pinned expectations flip in this package).

## Test cases

- **Feature:** creation-gate matrix (above); org CRUD with classification incl. guard; bank creation org validation; user validation per classification; publish validator rule; stage-permission authoring gates.
- **Unit:** resolver null-org no-match (T-3 extension); capability derivation per classification (role-cache and per-user paths).
- **Migration test:** seeded legacy orgs → expected mapping.
- **Regression:** full engine creation flow for a normal bank user unchanged end-to-end.

## Manual verification steps

1. Admin creates an org without classification → validation error; with each classification → saved and displayed.
2. Data Entry (bank user): `/workflows/new` works unchanged.
3. National Committee user: no create affordance; direct `POST /v1/engine-requests` → 403.
4. Designer: try granting initial-stage EXECUTE to a NATIONAL_COMMITTEE org → inline validation error; force the state in a draft via fixture → publish blocked with the named error.
5. Attempt reclassifying the banking org while published grants exist → blocked with clear message.

## Rollback considerations

Gating commits (C-4..C-7) revert independently of the schema commit (C-1..C-3). Column rollback = drop column (no data loss elsewhere). The removed `bank_id` request field would need re-adding only if some unknown client used it — none known; release notes flag it.

## Open questions

None — classification mutability guard (C-2) is derived from the D7-N6 invariant rather than newly decided; flag in review if you want classification changes locked harder (admin-only or immutable).

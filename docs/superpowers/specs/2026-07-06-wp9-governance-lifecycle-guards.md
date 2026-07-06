# WP-9 — Governance Lifecycle Guards

**Status:** Draft for review (Phase 6)
**Source of authority:** `2026-07-05-feature-review-notes.md`
**Traceability:** D14-N1 (workflow-aware delete/deactivate guards + impact preview), D14-N3 + D15-N6 (delete audits), D14-N5 (isProtected verification), D14-N6 (impact preview UI), D15-N4 (user deactivation rework), D15-N5 (bank suspension semantics), D16-N1/N2/N3 (reference-data guards + table-active semantics). Pairs with WP-7 (executor computation).
**Dependencies:** WP-0 (T-2 bank guard); pairs well with WP-7 (effective-executor computation reuse). WP-1 (classification) helpful for bank/org semantics. Independent of WP-2..WP-5.
**Enables:** WP-10 (role-model migration respects these guards); WP-14 (cleanup wave).
**Overall risk:** medium — admin CRUD becomes stricter; the shared guard service is the keystone (R8 note from Phase 5: extract one shared reference-guard service).

## Change classification

All items: **approved functional changes** (D-notes). The shared guard service is new architecture (extracted per R8).

**Explicitly out of scope:** role-model pivot migration (WP-10); placebo settings (WP-11); retention (WP-13); legacy dead-code purge (WP-14).

---

## G-0 — Shared published-workflow reference guard (keystone)

**Why one service:** org/team/role/reference-table/reference-value/bank all need the same check — "is this entity referenced by a published workflow's stage permissions / field definitions?" Extract once (R8), consume everywhere.
**Required:**
- `App\Services\Workflow\PublishedWorkflowReferenceGuard` with:
  - `isReferencedByPublishedPermissions($entityType, $entityId): bool` — checks `stage_permissions` of PUBLISHED versions.
  - `wouldLeaveStageWithoutExecutor($entityType, $entityId): bool` — reuses WP-3 V-3 / R4 effective-executor computation: simulates the entity's removal and checks each non-final published stage still has ≥1 active executor.
  - `referencedByDraft($entityType, $entityId): bool` — for warn-only paths.
  - `impact($entityType, $entityId): array` — affected workflows/versions/stages for the preview UI (G-6).
- Pure reads; transactional callers lock the entity before calling.

## G-1 — Organization/team/role delete + deactivate guards (D14-N1, D7-N4)

**Current:** structural-only guards (org has children; team/role assigned to users); published-workflow references not checked → dangling grants, silent executor loss.
**Required (uniform across org/team/role):**
- **Delete:** blocked if referenced by stage permissions of any PUBLISHED version → `*_REFERENCED_BY_PUBLISHED_WORKFLOW`.
- **Deactivate:** beyond structural checks, compute affected published stages; if any non-final stage would lose all effective EXECUTE users → block `*_WOULD_BREAK_EXECUTOR`. Valid executors remaining through other rows → proceed. DRAFT-only references → warn (not block) with impact list.
- Safe deactivation then takes effect immediately (WP-7 S-10 filters inactive teams/roles).
**Acceptance:** deleting/deactivating a referenced entity blocked; safe deactivation immediate.

## G-2 — Delete audits (D14-N3, D15-N6)

**Current:** org/team/role/bank/merchant deletes return 204 without audit (bank destroy specifically unaudited; reference-data deletes ARE audited).
**Required:** every governance-entity delete (incl. soft-deletes) audited with entity type/id/code/name, actor, timestamp, optional reason, record snapshot. Failed guarded deletes auditable where useful (`AUTHORIZATION_FAILURE`-style entries, matching the reference-data pattern).
**Acceptance:** grep: no governance destroy path lacks an audit call.

## G-3 — `isProtected()` verification + documentation (D14-N5)

**Required:** audit every `isProtected()` implementation (Role, Team, Organization, ReferenceTable, ReferenceValue) — confirm it correctly protects system rows (`is_system` / system anchor codes) from deletion and permission-breaking modification. Display-name edits allowed if safe; technical codes/names immutable. Document the protection contract per model.
**Acceptance:** system roles/orgs/tables undeletable; display-name rename works; code immutable.

## G-4 — User deactivation rework (D15-N4)

**Current:** `V1UserController::hasActiveWork` blocks deactivation when the user **created** any active request (authorship) — offboarding impossible for productive users.
**Required:**
- Block/require resolution only for **current operational responsibility:** current claim holder on an active request; direct assigned executor (if such assignment exists); sole effective executor of a currently active stage.
- Authorship never blocks; authored requests stay historically linked.
- Claim held → admin releases/reassigns first (reuse WP-5 release path or an admin force-release).
- Deactivation kills sessions/tokens, audited.
**Acceptance:** user with only authored (non-claimed) active requests deactivates cleanly; claim-holder must release first.

## G-5 — Bank suspension vs delete (D15-N5)

**Current:** `isUsed` (post-WP-0 BF-1) blocks suspend AND delete when any engine request (incl. CLOSED) exists → suspension impossible after first request.
**Required:**
- **Delete:** strict historical-reference guard (current behavior — users, merchants incl. soft-deleted, engine requests of any status) → `BANK_IN_USE`.
- **Suspend/deactivate:** allowed even with historical closed requests; blocks **new** request creation + new merchant/request activity; never hides history. Optional block/confirm only for active in-flight requests per business choice (MVP: warn on in-flight, allow suspend).
- Suspension/reactivation audited.
**Acceptance:** bank with closed-history requests suspendable; new creation blocked while suspended; delete still strict.

## G-6 — Impact preview UI (D14-N6)

**Required:** before deactivate/delete of org/team/role/bank, frontend shows affected users, published workflows, stages, permissions, possible runtime impact (sourced from `PublishedWorkflowReferenceGuard::impact`). Blocked actions explained clearly; warnings shown before confirmation.
**Acceptance:** admin sees the blast radius before confirming any governance delete/deactivate.

## G-7 — Reference-data guards (D16-N1, D16-N2, D16-N3)

**Current:** reference-value delete checks merchant-company usage only (documented 18.4/18.5 seam unclosed); table delete/deactivate ignore field-definition references; table `is_active` has no runtime meaning.
**Required:**
- **Value delete (D16-N1):** blocked when the value's table is referenced by any field definition of a PUBLISHED version, or may exist in engine request data → `REFERENCE_VALUE_PROTECTED`. Deactivate is the retirement path (D6-N3 grandfathering). Delete only for safe cleanup (no field reference, no runtime data). Structural guard (no JSON scan per delete).
- **Table delete/deactivate (D16-N2):** delete blocked when referenced by any field definition of a PUBLISHED version; deactivate blocked when referenced by a PUBLISHED version still serving runtime forms; DRAFT-only → warn; ARCHIVED-only → cleanup allowed only if no historical break. UI shows affected workflows/versions/fields.
- **Table `is_active` semantics (D16-N3):** designer-time availability — inactive table not bindable in new fields/drafts; publish blocked when a version introduces a new dependency on an inactive table; existing published versions keep resolving options (no sudden runtime break). Value-level `is_active` remains the runtime retirement mechanism.
**Acceptance:** reference value/table referenced by a live workflow can't be deleted/deactivated-breakingly; table-active is designer-time.

---

## Business rules (consolidated)

1. No governance entity (org/team/role/bank/reference-table/value) is deleted or deactivation-broken while a published workflow depends on it.
2. Deactivation is blocked only when it would leave a published non-final stage without an effective executor; otherwise immediate.
3. Deletes are audited; failed deletes auditable; impact is previewable.
4. User offboarding keys on current responsibility, not authorship.
5. Bank suspension blocks new business, not history; delete stays strict.
6. Reference data retires by deactivation; table-active is designer-time only.

## Error cases

| Case | Response |
|------|----------|
| Delete referenced by published workflow | 422 `*_REFERENCED_BY_PUBLISHED_WORKFLOW` |
| Deactivate breaking executor | 422 `*_WOULD_BREAK_EXECUTOR` |
| Bank delete with history | 422 `BANK_IN_USE` |
| Reference value/table delete guarded | 422 `REFERENCE_*_PROTECTED` |
| Deactivate user with held claim | 422 `USER_HAS_ACTIVE_WORK` (until released) |

## Acceptance criteria

1. `PublishedWorkflowReferenceGuard` exists; consumed by org/team/role/bank/reference controllers.
2. Delete/deactivate matrix per entity: referenced → block; safe → proceed; draft-only → warn.
3. Every governance delete audited; failed deletes auditable; impact preview renders.
4. User deactivation: authorship-only users deactivate; claim-holders must release.
5. Bank suspendable with history; delete strict.
6. Reference value/table guards + table-active semantics behave per spec.
7. All WP-0 suites green.

## Test cases

- **Unit (guard service):** referenced-by-published, would-break-executor, draft-only, impact shape — across entity types.
- **Feature (per entity):** delete/deactivate matrix; audit on delete; impact preview payload; user deactivation rework; bank suspend-vs-delete; reference value/table guards + table-active.
- **Regression:** legitimate deactivate/delete of unreferenced entities still works.

## Manual verification steps

1. Try deleting a role referenced by a published workflow → blocked with impact list; unreferenced → succeeds (audited).
2. Deactivate a team that's the sole executor of a published stage → blocked; team with co-executors → succeeds.
3. Deactivate a user who only authored requests → succeeds; claim-holder → blocked until release.
4. Suspend a bank with closed requests → succeeds, new creation blocked; delete → still blocked.
5. Delete a reference value used by a published workflow's field → blocked; deactivate → works (grandfathered).

## Rollback considerations

Guard service is additive; per-entity adoption reverts independently. G-4 (user deactivation) and G-5 (bank suspend) are the behavior-changing ones — revert restores stricter (authorship-based / history-based) blocking. G-7 reference guards tighten deletes — revert restores looser behavior. All reverts are safe (no data migration).

## Open questions

1. **G-5 in-flight suspension:** MVP warns on active in-flight requests and allows suspend — confirm, or block suspend while in-flight requests exist?
2. **G-4 admin force-release of claims:** confirm an admin force-release path (distinct from the holder's self-release) is in scope here vs WP-5 — recommend here (governance operation), reusing the release primitive.

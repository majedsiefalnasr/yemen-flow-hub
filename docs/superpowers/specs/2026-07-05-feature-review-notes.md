# Feature Review Notes — Running List

> Phase 3 collection file for the feature-by-feature review (started 2026-07-05).
> Notes only — no implementation until specs and plans are approved.
> Discussion IDs (D1–D23) refer to the agreed review plan (Engine-first order).

---

## D1 — Request Creation + Draft Editing (APPROVED WITH NOTES)

### D1-N1 (W1) — Deterministic submit transition from initial stage

- **Current behavior:** Wizard submits via the first graph edge leaving the initial stage (`edges.find(...)` in `EngineRequestWizard.vue`). Arbitrary when multiple outgoing transitions exist.
- **Required change:** Submit transition must be deterministic. Either the initial stage has exactly one valid submit transition, or one transition is explicitly marked as the default/submit transition. Publishing a workflow version must fail validation if the initial stage has multiple outgoing submit-capable transitions without an explicit default.
- **Reason:** Nondeterministic submit path is a correctness risk in the core lifecycle.
- **Priority:** High
- **Type:** Business Logic / Workflow Validation
- **Impacted areas:** `WorkflowVersionValidator`, publish flow (`WorkflowVersionController::publish`), `EngineRequestWizard.vue`, possibly `workflow_transitions` schema (default-submit flag).
- **Cross-ref:** also a D4/D5 designer validation rule.

### D1-N2 (W2) — Orphan drafts / auto-create on mount

- **Current behavior:** `/workflows/new` auto-creates an empty ACTIVE draft on page mount when a single published workflow exists. No delete/abandon path exists (no DELETE route for engine-requests). Cancel leaves an orphan draft.
- **Required change:**
  - Do not create the engine request on page open. Create the draft only when the user intentionally starts/saves the first step.
  - Provide an explicit "Abandon draft" action when a draft exists and the user cancels.
  - Empty drafts must not remain forever as active requests. If hard delete is not allowed for audit reasons, use an abandoned/cancelled draft status or transition instead.
- **Priority:** Medium
- **Type:** UX / Business Logic / Data Cleanup
- **Impacted areas:** `frontend/app/pages/workflows/new.vue`, `EngineRequestController::store`, engine status model (possible `ABANDONED` handling), queue filters, reports.

### D1-N3 (W3) — Request creation restricted to bank-side users

- **Current behavior:** Backend allows creation by anyone with EXECUTE on the initial stage (metadata-driven, could be granted to CBY actors). Frontend hardcodes `canCreate = DATA_ENTRY`.
- **Required change:**
  - Only users who belong to a bank may create/start a request.
  - CBY/internal users must not create bank requests directly.
  - Initial submission stage restricted to the bank organization side for now.
  - Workflow designer / permission engine must prevent granting initial-stage EXECUTE (creation) permission to non-bank actors.
  - Frontend relies on backend-provided capabilities; backend is the final enforcement layer.
- **Reason:** Lifecycle starts from bank submission; every request needs clear bank ownership from creation; avoids bank-less requests and unclear visibility/queues/reports.
- **Priority:** High
- **Type:** Permission / Business Logic / Data Scope
- **Impacted areas:** `EngineRequestService::create`, `StagePermissionResolver`, `StagePermissionController` (designer-side guard), `workflows/new.vue`, capability derivation.

### D1-N4 (W4) — Reject unknown data keys

- **Current behavior:** `StageFieldRuleValidator` iterates defined fields only; unknown keys in `data` are accepted and stored silently (draft save and submit).
- **Required change:** Backend validation rejects any submitted `data` key not matching a defined field of the workflow version. Applies to draft save and submit. Clear validation error for unknown fields.
- **Priority:** Medium
- **Type:** Validation / Data Integrity
- **Impacted areas:** `StageFieldRuleValidator` (or callers in `EngineRequestService::create`, `EngineTransitionService::saveDraft`/`execute`).

### D1-N5 (W5) — Bank ownership mandatory; no CBY-created requests

- **Current behavior:** `bank_id` resolves as `actor->bank_id ?? payload bank_id`; CBY user can create bank-less requests (bank_id null), skipping merchant scope check.
- **Required change:**
  - Every request must be created by a bank-affiliated user; ownership always resolved from the creator's bank.
  - CBY/internal users cannot create requests on behalf of a bank; remove manual `bank_id` selection from creation.
  - Merchant validation always tied to the request's bank ownership.
- **Priority:** High
- **Type:** Business Logic / Permission / Data Scope
- **Impacted areas:** `EngineRequestService::create`, `StoreEngineRequestRequest` (drop `bank_id` input), merchant scope check, `EngineRequestPolicy::inScope` semantics.
- **Cross-ref:** depends on D1-N3.

### D1-N6 (W6) — Align create vs draft PATCH validation (Low)

- Draft PATCH uses `data => required|array` (rejects `[]`); create uses `present|array`. Align both (reasonable: `present`).
- **Type:** Validation consistency.

### D1-N7 (W7) — Single published version per definition (High — see D1-N11)

- Availability list must not show duplicate published versions for one definition; N+1 in `availableWorkflows` noted as perf improvement.
- **Type:** Performance / Consistency.

### D1-N8 (W8) — Throttle request creation (Low)

- `POST /v1/engine-requests` has no rate limit (uploads do). Add throttling consideration.
- **Type:** Security / Abuse prevention.

### D1-N9 (W9) — Stale draft keys after schema changes (Low)

- Draft save merges data (`array_merge`); keys never removed. Clean or validate draft data against current schema to avoid stale keys.
- **Type:** Data Integrity.
- **Note:** partially superseded by D1-N4 (unknown-key rejection) — cleanup of pre-existing stale keys still needed.

### D1-N10 (W10) — Leave guard / unsaved-changes warning (Medium)

- No leave guard in wizard; back button does not save. Include in UX improvements. Existing draft plan doc: `docs/superpowers/plans/2026-07-05-draft-editing-concurrency-and-leave-guard.md`.
- **Type:** UX.

### D1-N11 — Published workflow version lifecycle rule (also D4)

- **Required behavior:**
  - A workflow definition can have only one currently published version.
  - Publishing a new version archives/supersedes the previous published version (no longer current).
  - Available workflows never show duplicate published versions per definition.
- **Runtime rule (critical):**
  - Existing requests continue on the exact workflow version they were created with, until completion.
  - Archiving/superseding only blocks *new* request creation from that version.
  - It must not break, migrate, or change behavior of already-created requests unless an explicit approved migration process exists.
- **Priority:** High
- **Type:** Workflow Versioning / Runtime Stability / Business Logic
- **Impacted areas:** `WorkflowVersionController::publish`, `WorkflowDesignerService`, `availableWorkflows`, runtime engine (version pinning already per-request via `workflow_version_id`).
- **Cross-ref:** D4 (definitions + version lifecycle) must review enforcement.

---

## D2 — Transitions + Action Execution (APPROVED WITH NOTES)

### D2-N1 (W1) — Explicit final outcome; REJECTED runtime status

- **Current behavior:** `EngineTransitionService::execute()` sets only `CLOSED` (final stage) or `ACTIVE`. `REJECTED` is queried by reports/read-model buckets but never set — reject counts always zero.
- **Required change:**
  - Final stages get an explicit outcome field (`final_kind` / `outcome`): `completed`, `rejected` (optionally `cancelled`/`abandoned` later).
  - On reaching a final stage: `completed` → `CLOSED`/`COMPLETED`; `rejected` → `REJECTED`.
  - Reports, dashboards, filters, buckets must use the final outcome. Never derive rejection from stage name/code.
  - **Designer validation:** publishing fails if any final stage lacks an explicit final outcome.
- **Priority:** High
- **Type:** Business Logic / Reporting / Workflow Runtime
- **Impacted areas:** `workflow_stages` schema, `EngineTransitionService::execute`, `WorkflowVersionValidator`, `EngineRequestReadModel`, reports (D18), designer stage editor (D5).
- **Cross-ref:** D1-N2 may reuse `cancelled/abandoned` outcome for orphan drafts.

### D2-N2 (W2) — Notifications after commit — **CLOSED (verified 2026-07-06)**

- `EngineNotificationDispatcher::dispatchAfterCommit` already wraps every dispatch in `DB::afterCommit` + a queued `DispatchNotification` job. Keep this behavior: any future notification/event dispatch from workflow transitions must continue to happen after commit; never dispatch user-visible notifications from inside an uncommitted transaction.
- **Status:** Verified / Closed
- **Type:** Transaction Safety / Notifications

### D2-N3 (W3) — Explicit side-effect attachment in workflow metadata

- **Current behavior:** critical effects (financing ledger, FX PDF) bound by global stage-code strings in `config/engine_hooks.php` + `AppServiceProvider`. Stage-code rename silently detaches financial/regulatory behavior.
- **Required change:**
  - Critical side effects attached explicitly through workflow metadata (designer-level stage/transition effect configuration).
  - Designer prevents accidental rename/removal of stages carrying critical side effects.
  - Publish validation confirms required side effects configured correctly.
  - Stage codes may remain but are not the sole source of truth for financial/regulatory effects.
- **Priority:** High
- **Type:** Workflow Designer / Runtime Safety / Business Logic
- **Impacted areas:** `StageHookRegistry`, `AppServiceProvider`, stage schema/designer (D5), `WorkflowVersionValidator`, `Effects/*`.

### D2-N4 (W4) — Runtime confirmation dialogs for transitions

- **Current behavior:** `confirmation_message` editable in designer, stored, typed in frontend — never shown at runtime. No confirm dialog before destructive transitions.
- **Required change:**
  - Transition with `confirmation_message` → frontend shows confirmation dialog (AlertDialog) with the configured message; explicit confirm before submit.
  - Confirmation required for: reject transitions, final/completion transitions, lifecycle-significant return-to-bank transitions, any transition the designer marks as requiring confirmation.
  - **Designer validation:** transition marked destructive/irreversible must have a confirmation message.
- **Priority:** Medium
- **Type:** UX / Safety / Workflow Runtime
- **Impacted areas:** `EngineActionsRail.vue`, `[id].vue` runAction, transition editor (D5), `WorkflowVersionValidator`.

### D2-N5 (W5) — Field-level audit diff on transitions

- **Current behavior:** transition audit logs stage ids + action code only; data patch changes not diffed.
- **Required change:** when a transition includes a data patch, audit captures changed fields with at minimum: field key, old value, new value, actor, request id, transition id, from/to stage, correlation id, timestamp. Sensitive fields maskable, but change traceability preserved.
- **Priority:** Medium
- **Type:** Audit / Compliance / Data Integrity
- **Impacted areas:** `EngineTransitionService::execute` (and `saveDraft` for parity — confirm in specs), `AuditService`.

### D2-N6 (W6) — Permission resolver performance (note)

- Acceptable at current scale. Later: caching, scoped queries, or precomputed accessible stage ids for queue/list lookups if performance degrades.
- **Priority:** Low — **Type:** Performance / Scalability

### D2-N7 (W7) — Required-comment UI feedback

- UI must ask for the comment before submission; no silent no-op button; inline validation when missing.
- **Priority:** Low — **Type:** UX / Validation
- **Impacted areas:** `EngineActionsRail.vue`, `[id].vue`.

### D2-N8 (W8/W10) — Designer validation against self-transitions

- Designer warns/blocks `from_stage_id === to_stage_id` unless explicitly allowed and marked intentional. Avoids history noise + hook re-firing.
- **Priority:** Low — **Type:** Workflow Validation / Data Quality
- **Impacted areas:** transition editor (D5), `WorkflowVersionValidator`.

### D2-N9 — Per-transition permissions (future enhancement, not spec'd)

- Stage-level EXECUTE stays the runtime permission model. Transition-level permissions only if a confirmed business case appears (return-not-approve, approve-not-reject, junior-recommend/senior-finalize).
- **Priority:** Future enhancement — **Type:** Permission / Workflow Granularity

### D2 carried assumptions

- D1-N4 (unknown-key rejection) explicitly applies to the `execute()` data patch path as well as draft save.
- D1-N8 throttling consideration extends to the actions endpoint.

---

## D3 — Request Visibility, History, Graph, Queues (APPROVED WITH NOTES)

### D3-N1 (W1) — Server-side lists, filters, and KPIs

- **Current behavior:** `/workflows` list loads page 1 only (25 rows); search, facet filters, KPIs, export, pagination all client-side on that page. Supervisor KPIs computed from newest 25.
- **Required change:**
  - Pagination via backend pagination; search/filters sent to backend (server filter set already exists in `applyFilters`).
  - Supervisor KPIs from the full authorized dataset. If needed, dedicated stats/aggregates endpoint: total visible, breached SLA, nearing SLA, unclaimed, by status, by stage/workflow.
- **Priority:** High
- **Type:** Reporting / UX / Data Accuracy / Performance
- **Impacted areas:** `frontend/app/pages/workflows/index.vue`, `useEngineRequests`, `EngineRequestController` (possible stats endpoint), DataTable wiring.

### D3-N2 (W2) — Field-visibility enforcement on API output

- **Current behavior:** stage `is_visible` field rules enforced on input only; `show`/list return full `data` JSON to any VIEW-holder.
- **Required change:** API output respects field visibility per workflow/stage metadata for the viewer. Hidden fields omitted or masked in: request detail, request list, exports. Sensitive/internal-only fields never leak to unauthorized viewers.
- **Reason:** VIEW-on-stage = may view the request at that stage, not all fields. Data visibility remains designer-controlled.
- **Priority:** High
- **Type:** Security / Data Visibility / Permissions
- **Impacted areas:** `EngineRequestResource`, `formSchema`, `EngineRequestController::index/show`, export paths, `StageFieldRuleValidator` rule source reuse.

### D3-N3 (W3) — Creator visibility stays metadata-driven (product decision recorded)

- **Decision:** no hardcoded "creator can always view" bypass. Stage permissions + workflow metadata are the sole source of visibility. If banks should keep visibility during specific stages, configure it in the designer.
- **Priority:** Product decision recorded
- **Type:** Permission / Workflow Metadata

### D3-N4 (W3b) — Stage/status presentation is metadata-driven

- **Decision:** no hardcoded simplified bank statuses in engine UI. Bank-facing labels, if needed, come from workflow/stage metadata (e.g. per-audience visible label), not frontend constants. Raw internal stage names hidden only when workflow config defines alternative labels or restricts visibility.
- **Priority:** Medium
- **Type:** Workflow Presentation / Metadata-driven UX
- **Impacted areas:** stage schema (possible bank-facing label field — D5), list/detail presentation, `constants/workflow.ts` legacy mapping (cleanup candidate).

### D3-N5 (W4) — Protect `system_admin` role code from drift

- **Decision:** `system_admin` stays the authoritative system-administration role code. Do not replace with `CBY_ADMIN`.
- **Required change:**
  - System-level role codes locked/protected in roles CRUD (no rename/delete/code change that breaks policies).
  - Backend and frontend reference the same system role code consistently (frontend currently keys on `CBY_ADMIN` enum, backend policies on `system_admin` code — reconcile).
  - Display names editable; technical codes stable.
- **Priority:** High
- **Type:** Security / Permissions / Role Consistency
- **Impacted areas:** `RoleController` (D14 guard), policies, frontend `auth.store` role checks, seeders.

### D3-N6 — Queue claim distinction

- **Required change:** "My Queue" must distinguish actionable requests from requests claimed by others: hide them, or badge "claimed by another user" + disabled action state. No need to open detail to discover the claim. Supervisor/admin views keep claimed requests for oversight.
- **Priority:** Medium
- **Type:** UX / Queue Accuracy / Claims
- **Impacted areas:** `myQueue` endpoint (claim filter/flag), `workflows/index.vue` queue columns.

### D3-N7 — Graph/history visibility follows metadata

- Internal users: full graph/history when permitted. Bank-side/restricted users: graph/history only per workflow/stage visibility rules. Simplified presentation, if needed, metadata-driven.
- **Priority:** Medium
- **Type:** UX / Visibility / Information Disclosure
- **Impacted areas:** `EngineRequestController::history/graph` authorization granularity.

### D3-N8 (W5) — `can_execute` per-row N+1 (perf note)

- Per-row resolver calls on lists (~3×N queries/page); resource comment claims lists omit it — false. Fold into D2-N6 permission-resolution optimization work.
- **Priority:** Low — **Type:** Performance

### D3-N9 (W9) — Legacy `/requests` test files → D23

- Legacy tests for removed `/requests` pages (`frontend/app/tests/unit/pages/requests/*`, DataEntryDashboard simplified-status tests) reviewed/removed/migrated in cleanup.
- **Priority:** Low — **Type:** Cleanup / Test Maintenance

---

## D4 — Workflow Definitions + Version Lifecycle (APPROVED WITH NOTES)

> Verified during review: single-published-per-definition + auto-archive on publish + runtime version pinning (D1-N11) are **already implemented** in `WorkflowDesignerService::publishVersion` / `EngineTransitionService` (no state check at runtime). Remaining D1-N11 work is designer-UI clarity + tests, not backend enforcement.

### D4-N1 (W1) — Graph reachability validation before publish

- **Required change:** publish validation must enforce: every stage reachable from initial; ≥1 final stage reachable from initial; unreachable stages block publish; unreachable finals block publish; non-final dead-end stages block publish unless explicitly allowed; self-loops alone do not count as progression.
- **Priority:** High
- **Type:** Workflow Validation / Runtime Safety
- **Impacted areas:** `WorkflowVersionValidator`, `WorkflowPublishPanel` error display.

### D4-N2 (W2) — Effective-executor validation depth

- **Required change:** for every non-final stage, validator confirms ≥1 **active user** can effectively execute it, evaluating actual permission rows (user, role, team, organization grants and combined constraints). EXECUTE config matching no active user → blocking validation error.
- **Priority:** Medium
- **Type:** Permission Validation / Runtime Safety
- **Impacted areas:** `WorkflowVersionValidator` (`STAGE_NO_EXECUTOR` deepening), `StagePermissionResolver` reuse.

### D4-N3 (W3) — Delete policy for definitions/versions

- **Required change:**
  - DRAFT versions: hard-delete allowed when no runtime requests.
  - PUBLISHED versions: never hard-deleted (archive-only), even request-free.
  - ARCHIVED versions: preferably retained for audit/history; deletion restricted, audited, only when no requests reference them.
  - Definitions: not hard-deletable if any published/archived/request-linked version exists; prefer soft delete for config records if deletion required.
- **Priority:** Medium
- **Type:** Governance / Audit / Data Retention
- **Impacted areas:** `WorkflowDesignerService::deleteVersion/deleteDefinition`, `WorkflowVersionPolicy/WorkflowDefinitionPolicy`, designer UI delete affordances.

### D4-N4 (W4) — Limited definition editing

- **Required change:** `code` immutable after creation; `name` editable; add `description` (if missing) and make it editable; updates audited; edits never affect existing requests/versions.
- **Priority:** Medium
- **Type:** Admin UX / Governance
- **Impacted areas:** new PUT definitions endpoint, `WorkflowDefinitionController`, schema (description column), designer UI.

### D4-N5 (W5) — Consolidated validator rules from D1/D2

- **Required validation rules (publish-blocking unless noted):**
  1. Initial-stage submit transition deterministic — exactly one submit transition or one explicitly marked default submit (D1-N1).
  2. Every final stage defines explicit final outcome/kind: completed / rejected / cancelled / abandoned (D2-N1).
  3. Destructive/irreversible transitions must have a confirmation message (D2-N4).
  4. Critical side effects explicitly attached via workflow metadata, not only global stage-code strings (D2-N3).
  5. Self-transitions blocked or warned unless explicitly marked intentional (D2-N8).
- **Priority:** High
- **Type:** Workflow Validation / Business Logic / Runtime Safety
- **Impacted areas:** `WorkflowVersionValidator`, transition/stage schemas, designer editors, publish panel.

### D4-N6 (W6) — `stageIsBound()` wrong-table bug (cleanup)

- Checks dropped legacy `requests` table; must check engine runtime table (`engine_requests`) if the check is still needed. Mitigated today by DRAFT-only edits; clean anyway.
- **Priority:** Low — **Type:** Bug / Legacy Cleanup

### D4-N7 (W7) — Dead PUT workflow-versions endpoint (cleanup)

- No editable payload; remove or give real purpose. Avoid audit noise from meaningless version bumps.
- **Priority:** Low — **Type:** API Cleanup / Audit Quality

### D4-N8 (W8) — Clone from ARCHIVED

- Allow ARCHIVED → new DRAFT clone (new version number, normal validation/publish path); archived source stays archived.
- **Priority:** Low/Medium — **Type:** Workflow Versioning / Admin UX
- **Impacted areas:** `WorkflowDesignerService::cloneVersion` state guard, designer UI.

### D4-N9 — Archiving the only published version

- Allowed as intentional kill switch. UI must warn explicitly that new request creation stops for the definition; existing requests continue on pinned versions; action audited, with reason/comment where possible.
- **Priority:** Medium — **Type:** Governance / Workflow Availability
- **Impacted areas:** archive endpoint (optional reason), `admin/workflows.vue` confirm dialog copy.

### D4-N10 — Config export/import (future enhancement)

- Environment portability (dev→staging→prod) for definitions, versions, stages, transitions, fields, permissions, rules, side-effect metadata. Not in immediate scope.
- **Priority:** Future enhancement — **Type:** DevOps / Configuration Portability

### D4-N11 (W9) — Publish notifications to `system_admin` (accepted)

- Current behavior accepted; depends on D3-N5 role-code protection.
- **Priority:** Info — **Type:** Notification / Role Governance

---

## D5 — Stages, Transitions, Actions, Graph (APPROVED WITH NOTES)

### D5-N1 (W1) — Stage `status` becomes meaningful

- **Current behavior:** `workflow_stages.status` (ACTIVE/INACTIVE) stored, validated, cloned — never read by runtime or validator.
- **Required change:** ACTIVE usable normally; INACTIVE stages excluded from publishable runtime paths. Publish blocked if: initial stage inactive; any final stage inactive; any transition from/to an inactive stage; an inactive stage reachable from initial. Runtime need not re-check status on published immutable versions (unless post-publish status change is ever allowed).
- **Priority:** Medium
- **Type:** Workflow Validation / Designer Semantics
- **Impacted areas:** `WorkflowVersionValidator`, stage editor UI.

### D5-N2 (W2) — Action `kind` becomes load-bearing

- **Current behavior:** `workflow_actions.kind` (DRAFT/APPROVE/REJECT/RETURN/CLOSE/INFO/CUSTOM) never read by engine.
- **Required change:** kind classifies transition behavior for validation + presentation; transition metadata remains source of truth for explicit configuration. Concrete validator rules:
  - REJECT/CLOSE-kind transitions require `confirmation_message`.
  - REJECT transitions normally lead to a final stage with outcome `rejected`; CLOSE/completion transitions to outcome `completed`; kind↔target-outcome mismatch blocks publish (or clear validation error).
  - Destructive/irreversible CUSTOM actions must explicitly require confirmation.
- **Priority:** High
- **Type:** Workflow Validation / Business Logic / Runtime Safety
- **Cross-ref:** D2-N1 (final outcome), D2-N4 (confirmation), D4-N5.

### D5-N3 (W3) — Final stages must have no outgoing transitions

- Publish blocked if any final stage has outgoing transitions. Final = lifecycle ended. Reopen/reactivation, if ever needed, is a separate explicit feature.
- **Priority:** Medium
- **Type:** Workflow Validation / Graph Integrity
- **Impacted areas:** `WorkflowVersionValidator`, transition editor guard.

### D5-N4 (W4) — Explicit return-transition metadata

- Add transition `transition_type`/`direction` metadata: forward / return / reject / close / custom. Sort-order heuristic (`WorkflowGraphService::isReturnEdge`) stays as display fallback only, never business semantics. Designer can mark return transitions.
- **Priority:** Low/Medium
- **Type:** Workflow Metadata / UX / Graph Semantics
- **Impacted areas:** `workflow_transitions` schema, `WorkflowGraphService`, transition editor, canvas styling.

### D5-N5 (W5) — Deterministic display-label resolution (note)

- Multiple labeled permission rows → resolve deterministically; prefer explicit audience-based labels later; avoid arbitrary first-match.
- **Priority:** Low — **Type:** UX / Metadata Consistency
- **Cross-ref:** D3-N4 (audience-facing stage labels).

### D5-N6 (W6) — Action deactivation is designer-time only (accepted + document)

- Deactivation blocks use in new draft transitions; published versions and runtime requests unaffected; existing published transitions keep working. Behavior documented clearly in admin UI.
- **Priority:** Low — **Type:** Documentation / Workflow Versioning / Runtime Stability

### D5-N7 (W7) — Self-loop tracking (dup of D2-N8/D4-N5.5)

- Self-loops blocked/warned at validation unless explicitly marked intentional; accidental self-loops not publishable.
- **Priority:** Low — **Type:** Workflow Validation / Graph Quality

---

## D6 — Fields, Field Groups, Stage Field Rules (APPROVED WITH NOTES)

### D6-N1 (W1) — Bank-scoped merchant dynamic options

- **Current behavior:** `DynamicFieldOptionsResolver` returns all merchants/companies globally (no user/request context).
- **Required change:** MERCHANTS / MERCHANT_COMPANIES options scoped: bank users → own bank; CBY/internal users on an existing request → the request's bank; creation context → creator's bank (only bank users create — D1-N3/N5). Global merchant visibility only in explicit admin screens, never in request dynamic forms.
- **Priority:** High
- **Type:** Security / Data Scope / Dynamic Options
- **Impacted areas:** `DynamicFieldOptionsResolver` (needs context param), `formSchema`, wizard.

### D6-N2 (W2) — Option membership validation for SELECT / DYNAMIC_SELECT

- **Required change:** backend rejects submitted select values not in the allowed set. Applies to draft save, transition execution, and any endpoint accepting dynamic field data. Static SELECT → configured options; DYNAMIC_SELECT → resolved set in correct context (request bank, actor, reference table, merchant scope). Backend-enforced.
- **Priority:** High
- **Type:** Validation / Data Integrity / Security
- **Impacted areas:** `StageFieldRuleValidator::checkConstraints`, resolver reuse.
- **Cross-ref:** D1-N4.

### D6-N3 (W2b) — Grandfathering policy for stale option values

- Stored values valid at selection time stay readable; deactivated options unavailable for new selection; editing the field later forces an active choice; existing requests never break from later deactivation; UI shows old stored label marked inactive/deprecated where possible.
- **Priority:** Medium
- **Type:** Data Lifecycle / Reference Data / UX

### D6-N4 (W3) — Publish-time field-rule consistency validation

- Block publish when a field is: required+hidden in the same stage; required+non-editable with no default and no guaranteed previous value. Required+non-editable allowed only when the value is guaranteed before entering the stage. Clear messages naming field + stage.
- **Priority:** Medium/High
- **Type:** Workflow Validation / Field Rules / Runtime Safety
- **Impacted areas:** `WorkflowVersionValidator`, `StageFieldRuleMatrix` UI hints.

### D6-N5 (W4) — Design-time regex validation

- Validate regex syntactic validity on field save; reject invalid patterns before publish/use; reasonable length/complexity limits; runtime must never 500 on a designer typo (ReDoS risk reduction).
- **Priority:** Medium
- **Type:** Validation / Runtime Safety / Security
- **Impacted areas:** `StoreFieldDefinitionRequest`/`UpdateFieldDefinitionRequest`, validator.

### D6-N6 (W5) — Constraint consistency validation

- `min_value ≤ max_value`; `min_length ≤ max_length`; positive file size limits; options arrays valid shape + unique values. Rejected at field save or workflow validation.
- **Priority:** Medium
- **Type:** Field Validation / Designer UX

### D6-N7 (W6) — FILE fields reference server-validated documents

- FILE field values must not trust client-submitted mime/size metadata. Binary validation only via documents endpoint (or equivalent server-validated flow). Field value stores a reference to an uploaded document; validator verifies referenced document exists, belongs to the same request, and matches allowed type/size rules. Behavior reflected clearly in field UI.
- **Priority:** Medium
- **Type:** Security / File Validation / Data Integrity
- **Impacted areas:** `StageFieldRuleValidator` FILE branch, `DynamicFormField`, documents linkage (D10 cross-check).

### D6-N8 (W7) — Typed value validation for DATE / CHECKBOX / defaults

- DATE = ISO-8601 (`YYYY-MM-DD`), validated at draft save + transition; CHECKBOX stores booleans only; `default_value` validated per field type.
- **Priority:** Low/Medium
- **Type:** Data Integrity / Field Types

### D6-N9 (W8) — `fieldIsUsed()` wrong-table bug → D23

- Same family as D4-N6 (`stageIsBound`). Checks dropped `requests` table; point usage checks at engine runtime source or remove.
- **Priority:** Low — **Type:** Bug / Legacy Cleanup

### D6-N10 (W9) — Static SELECT options shape validation

- Options need stable unique value + display label; duplicates rejected; empty/invalid entries rejected.
- **Priority:** Low/Medium — **Type:** Field Validation / Data Quality

---

## D7 — Stage Permissions, Designer Side (APPROVED WITH NOTES)

### D7-N1 (W1) — BUG: partial-update consistency bypass

- **Current behavior:** `StagePermissionConsistency::check` runs on the raw partial payload; update sending only `team_id` (no `organization_id`) skips the whole check → cross-org team/role/user attachable to an existing row.
- **Required change:** on update, merge submitted fields with the existing row first; validate org/team/role/user consistency against the **effective final row**. Cross-org attachment must be impossible.
- **Priority:** High
- **Type:** Bug / Permission Integrity / Validation
- **Impacted areas:** `UpdateStagePermissionRequest` / `StagePermissionConsistency`.

### D7-N2 (W2) — Effective-audience preview + publish enforcement

- Designer shows active-user match count per permission row ("matches N active users"); zero-match rows get a warning. Publish-time: every non-final stage needs ≥1 active user who can effectively EXECUTE, evaluating the real org+team+role(+user)+active-status combination.
- **Priority:** High
- **Type:** Workflow Validation / Permission Safety / Runtime Safety
- **Cross-ref:** implements/extends D4-N2.
- **Impacted areas:** `StagePermissionEditor.vue`, new preview endpoint or resolver reuse, `WorkflowVersionValidator`.

### D7-N3 (W3) — Drop user-specific grants (for now)

- Stage permissions = organization + team + role only. Remove/disable `user_id` from designer/API surface unless a confirmed business need appears; if kept in DB for compatibility, not exposed as an active feature. Avoid person-bound routing (fragile on transfer/deactivation/replacement).
- **Priority:** Medium
- **Type:** Permission Design / Governance / Maintainability

### D7-N4 (W4) — Lifecycle guard for org/team/role referenced by published permissions

- Admin CRUD (orgs/teams/roles) must check references from PUBLISHED workflow version stage permissions: deletion blocked if referenced; deactivation blocked if it would leave any active/published stage without an effective executor. Replacement path = new draft version → adjust permissions → publish. Runtime requests must never sit in stages with no possible executor.
- **Priority:** High
- **Type:** Runtime Safety / Permission Governance / Admin CRUD Guard
- **Cross-ref:** duplicated into D14 notes (guard lives in Admin CRUD).

### D7-N5 (W5) — Duplicate permission rows prevented

- Reject or merge duplicates of (stage, org, team, role, user-if-kept, access_level).
- **Priority:** Low — **Type:** Data Quality / Designer UX

### D7-N6 (W6) — Request creation gated by organization classification

- **Supersedes the "bank-only" phrasing of D1-N3/D1-N5** (intent unchanged, model generalized).
- Organizations get a required classification: `BANKING_SECTOR` (commercial banks + exchange companies), `NATIONAL_COMMITTEE` (entities under the National Committee for Import Financing), `OTHER`.
- **Required behavior:**
  - Creating/submitting a request allowed only for users whose organization is `BANKING_SECTOR`.
  - Initial creation/submission stage may grant EXECUTE only to `BANKING_SECTOR` organizations/roles.
  - Designer/validator prevents granting initial-stage creation permission outside `BANKING_SECTOR`.
  - Not hardcoded to commercial banks — new `BANKING_SECTOR` org types (e.g. exchange companies) create requests automatically.
  - CBY/internal, National Committee, and Other entities cannot create requests unless their classification is explicitly allowed later.
- **Priority:** High
- **Type:** Business Rule / Organization Classification / Permission Validation / Workflow Designer
- **Cross-ref:** D14 organization-classification note (below); D1-N3, D1-N5.

### D7-N7 (W7) — Deterministic display labels (note)

- Graph label resolution deterministic; no arbitrary first-match; future: audience-specific labels. (Extends D5-N5.)
- **Priority:** Low — **Type:** UX / Metadata Consistency

### D7-N8 — Null-organization users ineligible for stage matching

- Stage permissions are org-anchored; null-org users must not silently match or carry workflow execution responsibility (bootstrap/system accounts excluded from routing). Designer warns/blocks rows that can never match due to null-org relationships.
- **Priority:** Medium
- **Type:** Permission Consistency / Runtime Safety

---

## D14 (pre-note from D7) — Organization classification

- **Decision:** organizations get a required `classification` field: `BANKING_SECTOR` / `NATIONAL_COMMITTEE` / `OTHER`.
- **Required behavior:**
  - Create/edit forms include classification (required).
  - Workflow designer/validator consumes it to control initial-stage creation grants (only `BANKING_SECTOR` for the current process).
  - Distinct from roles/teams (which remain refinements inside the organization).
  - Existing organizations migrated/classified during setup; unclassified organizations cannot create requests.
- **Priority:** High
- **Type:** Admin CRUD / Organization Model / Permission Validation / Workflow Designer
- **Cross-ref:** D7-N6 (designer validation depends on this field).

---

## D8 — Screen Permissions Matrix + Derived Requests Capability (APPROVED WITH NOTES)

### D8-N1 (W1) — Deactivated teams/roles must not grant permissions

- **Current behavior:** identity building (`StagePermissionResolver::identityFor`, `derivedRequestsCapabilitiesForUser`) plucks user teams/roles without `is_active` filtering.
- **Required change:** permission identity includes only active teams and active roles; stage matching and derived request capabilities ignore inactive ones; safe deactivation takes effect immediately.
- **Cross-link:** works with D7-N4/D14 lifecycle guards — deactivation blocked when it would leave a published stage without an effective executor; otherwise immediate.
- **Priority:** High
- **Type:** Permission Revocation / Security / Runtime Safety
- **Impacted areas:** `StagePermissionResolver::identityFor`, `PermissionService::derivedRequestsCapabilitiesForUser`, tests.

### D8-N2 (W2) — Single-role user model (replaces earlier multi-role question)

- **Decision:** multi-role users are NOT supported. Each user has exactly one active role.
- **Required behavior:** manual screen permissions and derived request capabilities both resolve from the single assigned active role; no union-of-roles resolution anywhere; user assignment enforces one role; users without an active role get no manual screen permissions and no derived capabilities; inactive roles grant nothing.
- Legacy/multi-role code paths (`user->roles()` plural identity sets, `hasAnyRoleCode`, `user_roles` pivot semantics) reviewed and aligned/removed/marked legacy.
- **Priority:** High
- **Type:** Permission Consistency / Role Model / User Management
- **Cross-link:** duplicated to D15 (user CRUD enforces single-role rule).

### D8-N3 (W3) — Derivation ↔ runtime consistency for stage status

- After D5-N1, published versions contain no executable inactive-stage paths, so derivation (filters `status='ACTIVE'`) and runtime resolver (no status check) must not disagree. If `stage.status` stays meaningful, apply the same rule consistently in validation, derivation, and runtime. Never allow chrome/enforcement mismatch in either direction.
- **Priority:** Medium
- **Type:** Permission Consistency / Workflow Validation

### D8-N4 (W4) — Matrix concurrency (note)

- Full-replace acceptable now; later add optimistic concurrency/version token against silent last-write-wins; UI stale-edit warning. Audit already captures changes.
- **Priority:** Low — **Type:** Admin UX / Concurrency

### D8-N5 (W5) — Screen metadata stays code-defined (note)

- Universal/admin-only/capability-column constants remain in code for now; move to `screens` table only if dynamic screen management becomes a requirement.
- **Priority:** Low — **Type:** Future Enhancement / Configuration Model

### D8-N6 (W6) — Frontend permission refresh

- Backend enforcement stays immediate source of truth. Frontend refetches `/auth/me`(/permissions) on permission-change notification; fallback: refresh on route change or short cache invalidation. No logout/login needed to see updated access.
- **Priority:** Low/Medium — **Type:** UX / Permission Sync

### D8-N7 (W7) — system_admin special cases accepted

- Oversight-only requests view and merchants-MANAGE denial acceptable. Requirement remains protecting the `system_admin` role code from rename/delete/drift (D3-N5, D14).
- **Priority:** Info — **Type:** Role Governance / Security

---

## D15 (pre-note from D8) — Single active role per user

- User management enforces exactly one active role per user; permission resolution never unions multiple roles; existing multi-role relationships/code paths reviewed and removed, disabled, or explicitly marked legacy cleanup.
- **Priority:** High — **Type:** User Management / Permission Consistency
- **Cross-ref:** D8-N2, refined by D14-N2 (pivot is canonical storage).

---

## D14 — Organizations, Teams, Roles (APPROVED WITH NOTES)

### D14-N1 (W1 + W4) — Workflow-aware delete/deactivate guards

- **Delete:** blocked for any org/team/role referenced by stage permissions of any PUBLISHED workflow version.
- **Deactivate:** beyond structural assignment checks, compute affected published stages; if any non-final stage would lose all effective EXECUTE users → block. Valid executors remaining through other rows → proceed.
- DRAFT-only references → warning, not block. UI shows affected workflows/stages before blocking/warning.
- **Priority:** High
- **Type:** Workflow Safety / Permission Governance / Admin CRUD Guard
- **Cross-ref:** implements D7-N4; pairs with D8-N1 (safe deactivation then takes effect immediately).
- **Impacted areas:** `OrganizationController/TeamController/RoleController` deactivate/destroy, shared guard service, admin UI dialogs.

### D14-N2 (W2) — Canonical role storage: `user_roles` pivot only

- Single active role per user; pivot = sole source of truth. Authorization, stage resolution, screen permissions, policies, resources, frontend auth data all read the pivot. `users.role` column: never used for authorization or presentation; treated as legacy debt; removed via migration once dependencies are migrated.
- **Migration/cleanup:** inventory all `users.role` readers (`UserResource`, `AuthMeResource`, `GovernanceUserResource`, `DemoUserResource`, `StageHistoryResource`, `CustomsDeclarationResource`, `AuditLogResource`, frontend `auth.store`, demo endpoints, policies) → replace with pivot relationship; DB constraint or application guard preventing multiple active roles per user; drop column at the end.
- **Priority:** High
- **Type:** Role Model / Permission Consistency / Legacy Cleanup
- **Cross-ref:** D8-N2, D15 pre-note.

### D14-N3 (W3) — Audit governance deletes

- Org/team/role deletion audited: entity type/id/code/name, actor, timestamp, optional reason, record snapshot where appropriate. Failed deletes due to protected/in-use/workflow-reference guards auditable where useful.
- **Priority:** Medium
- **Type:** Audit / Governance / Admin CRUD

### D14-N4 (W5) — Organization classification (consolidates D7-N6 pre-note)

- Required field, controlled application/DB or config-backed enum (NOT free-form reference data — security/business-rule relevant): `BANKING_SECTOR` (commercial banks + exchange companies), `NATIONAL_COMMITTEE`, `OTHER`.
- Create/edit forms include it (required); existing orgs classified via migration/setup; unclassified orgs cannot create requests; only `BANKING_SECTOR` grantable initial-stage EXECUTE (designer/validator enforced); NATIONAL_COMMITTEE/OTHER excluded unless future rule.
- **Financial institution link:** BANKING_SECTOR orgs allowed to create requests should link to a financial-institution/bank record. Category covers banks + exchange companies — do not hardcode to commercial banks. **Note:** current schema has `banks` only — review later whether to rename/extend the model for exchange companies (D15 cross-check).
- **Priority:** High
- **Type:** Organization Model / Business Rule / Workflow Validation

### D14-N5 (W6) — Verify/document `isProtected()` semantics

- Confirm system rows (`system_admin` etc.) protected from deletion and permission-breaking modification; display-name edits allowed if safe; technical codes stable.
- **Priority:** Low — **Type:** Role Governance / System Protection

### D14-N6 — Impact preview UI

- Before deactivate/delete of org/team/role: show affected users, published workflows, stages, permissions, runtime impact; blocked actions explained clearly; warnings shown before confirmation.
- **Priority:** Medium — **Type:** Admin UX / Governance Safety

---

## D9 — Claim / Locking (APPROVED WITH NOTES)

> **Conceptual decision:** claims are scoped to the request's **current stage**, reset on every stage entry, and never carry across workflow handoffs.

### D9-N1 (W1) — Auto-release claim on transition

- **Current behavior:** `execute()` never clears claim fields; holder's heartbeat keeps extending the claim after the request moved stages → next-stage executor lockout (indefinite while the holder's page stays open).
- **Required change:** successful transition releases the claim **inside the same transaction**; next stage starts unclaimed; target-stage executor claims fresh when required; previous actor's heartbeat must not extend a post-transition claim; release audited with reason `stage_changed`.
- **Priority:** High
- **Type:** Runtime Safety / Concurrency / Workflow Handoff
- **Impacted areas:** `EngineTransitionService::execute`, `EngineClaimService`, `useEngineClaim` (stop heartbeat on stage change), audit.

### D9-N2 (W2) — Server-side claim enforcement on draft save

- **Current behavior:** `saveDraft()` has no `requires_claim` check — wizard claim gating is frontend-only (commit e30dc286 touched `[id].vue` only); direct API/second tab bypasses the soft lock.
- **Required change:** `PATCH /engine-requests/{id}/draft` checks claim ownership when current stage `requires_claim`; non-holders get 403 `CLAIM_NOT_HELD`; mirrors `execute()` enforcement. Frontend gating stays as UX only.
- **Priority:** High
- **Type:** Backend Enforcement / Concurrency / Data Integrity
- **Impacted areas:** `EngineTransitionService::saveDraft`.

### D9-N3 (W3) — Claim-loss UX

- On heartbeat/draft-save/transition 403 `CLAIM_NOT_HELD`: stop heartbeat, clear warning/toast (claim lost or expired), switch page read-only, disable save/actions, offer return-to-queue or refresh/reclaim when allowed. No silent continuation of editing.
- **Priority:** Medium
- **Type:** UX / Claim Handling / Error Recovery
- **Impacted areas:** `useEngineClaim`, `[id].vue`, `EngineRequestWizard`, `useEngineRequestActions`.

### D9-N4 (W4) — Claim cache mirror cleanup

- `engine_claim:{id}` cache has zero readers (write-only). Remove it or give it a documented reader; DB row stays the source of truth. Update AGENTS/backend docs that currently describe the mirror as load-bearing.
- **Priority:** Low
- **Type:** Cleanup / Documentation / Architecture Clarity

### D9-N5 (W5) — Heartbeat safety

- Heartbeat verifies: request still active, user still current holder, claim not expired/released, request hasn't moved stages (per stage-scoped claim model). Use row locking or equivalent against sweeper races. Invalid heartbeat → clear `CLAIM_NOT_HELD`.
- **Priority:** Low/Medium
- **Type:** Concurrency / Claim Integrity
- **Impacted areas:** `EngineClaimService::heartbeat`.

### D9-N6 (W6) — Live claim-state updates (note)

- Other viewers eventually see claim changes without manual reload (polling/refresh/real-time events). Backend enforcement remains source of truth.
- **Priority:** Low — **Type:** UX / Real-time State

> Verified during review: wizard leave guards (route-leave dialog + browser beforeunload) landed in commits 274148fa/c16cceba — D1-N10 substantially implemented; keep spec item for verification/coverage only.

---

## D10 — Documents (APPROVED WITH NOTES)

### D10-N1 (W1) — Claim enforcement on document upload/delete

- On `requires_claim` stages, only the valid claim holder may upload or delete documents; others get 403 `CLAIM_NOT_HELD`. Mirrors D9-N1/N2 enforcement; backend mandatory, frontend gating UX-only.
- **Priority:** High
- **Type:** Backend Enforcement / Claim / Document Integrity
- **Impacted areas:** `EngineRequestController::uploadDocument/deleteDocument`.

### D10-N2 (W2) — Required FILE fields need real uploaded documents

- Leaving a stage with a required FILE field requires ≥1 non-deleted document linked to that field, belonging to the same request, matching the field's file constraints. Client metadata never counts as proof. Enforced in draft/transition validation where relevant.
- **Priority:** High
- **Type:** Validation / Evidence / Document Integrity / Field Rules
- **Cross-ref:** implements D6-N7.

### D10-N3 (W3) — Document visibility follows field visibility

- Field-linked documents (via `field_id`) respect the owning field's visibility rules for list, download, and future exports. Hidden field ⇒ linked document not listed/downloadable for that viewer. Unlinked documents keep the general stage/request visibility policy. Stage VIEW alone never exposes hidden-field documents.
- **Priority:** High
- **Type:** Security / Field Visibility / Document Access
- **Cross-ref:** extends D3-N2.

### D10-N4 (W4) — Malware scanning (subject to infrastructure)

- Uploads scanned before treated as available; infected/suspicious rejected or quarantined; async scanning gets a scan status (`pending/clean/infected/failed`); downloads only for clean files. If environment can't support yet → recorded as high-priority security hardening.
- **Priority:** Medium/High
- **Type:** Security / Infrastructure / File Upload Hardening

### D10-N5 (W5) — Checksum-based integrity verification

- Verify stored sha256 on download or via scheduled integrity job; failure blocks download + raises audit/security event.
- **Priority:** Low/Medium
- **Type:** File Integrity / Security Hardening

### D10-N6 (W6) — Orphan-file cleanup (note)

- Cleanup on row-create failure (immediate or scheduled); never delete files referenced by document records.
- **Priority:** Low — **Type:** Storage Cleanup / Reliability

### D10-N7 (W7) — Return-loop: controlled replacement, not re-deletion

- Prior-visit documents never freely deletable on stage re-entry. Replacement flow instead: old document marked replaced/superseded (metadata + audit kept), replacement stored as new record/version, old↔new linked, replacement audited (who/when/why). Active document = latest non-superseded, non-deleted. Physical cleanup only via documented retention/archive policy — never user delete. No silent removal of previous evidence.
- **Priority:** Medium
- **Type:** Audit Integrity / Document Lifecycle / Storage Management

### D10-N8 (W8) — Document-types module → legacy cleanup

- Engine uses FILE fields as document-requirement source → move document-types module to D23 cleanup (controller, routes, model, `useDocumentTypes` composable + tests). If needed later, define explicit integration (workflow metadata / required-document checklists / FILE fields). No dead module implying unenforced requirements.
- **Priority:** Low
- **Type:** Cleanup / Legacy Module / Product Clarity

### D10-N9 (W9) — Per-field document count limits (note)

- Single-vs-multiple documents per field should come from field constraints (`multiple` flag); throttle is not a business limit.
- **Priority:** Low — **Type:** UX / Field Constraints / Storage Control

---

## D11 — FX Confirmation / Completion (APPROVED WITH UPDATED NOTES)

### D11-N1 (W1) — FX UI regression check (PERFORMED 2026-07-06) + restoration/migration

- **Git evidence:**
  - `frontend/app/components/requests/FxConfirmationCard.vue` (+ test) created **2026-06-04** in `4ae627ef` "feat(documents): add confirmation document workflow"; flow aligned 2026-06-05 in `5c0a6cfc` "align external fx confirmation flow".
  - Deleted **2026-07-01** in `0e9f1eae` "refactor(workflow): delete legacy ImportRequest frontend (G11-P6)" — intentional removal during the G11 legacy cutover, not accidental.
  - The card was bound to the **legacy** stack: `ImportRequest` prop, `requests.store` (`uploadSignedFxDoc`, `issueCustomsDeclaration`, `downloadFxConfirmationTemplate`), legacy `RequestStatus.FX_CONFIRMATION_PENDING`, legacy `/requests` endpoints.
- **Classification:** removed with the legacy frontend; **engine replacement was never built**. Backend V1 endpoints (`POST fx-confirmation-signed`, `GET customs-declaration/download`, `GET customs-declaration/signed-fx-download`) exist with zero frontend consumers.
- **Recommended approach (migration, not restoration):** rebuild as an engine component (e.g. `components/workflow/EngineFxConfirmationPanel.vue`) mounted on `/workflows/instances/[id]` when a declaration exists / the request is at-or-past the FX stage; wire to the V1 endpoints; gate per D11-N3 (stage permissions + request scope). Old component retrievable as a UX reference: `git show 0e9f1eae^:frontend/app/components/requests/FxConfirmationCard.vue`.
- **Priority:** High
- **Type:** Regression Check / UI Restoration / FX Confirmation

### D11-N2 (W2) — Signed-doc replacement: storage-friendly but auditable

- Replacement of the active signed FX file allowed; old physical file may be deleted/archived/compressed per retention policy. Must preserve: previous metadata, checksum, uploader, upload timestamp, replacement timestamp + actor, audit entry, optional reason. UI shows latest active document; audit history always shows a previous document existed and was replaced. Silent replacement forbidden.
- **Priority:** High
- **Type:** Document Replacement / Storage Management / Audit
- **Cross-ref:** specializes D10-N7 (physical retention relaxed for signed FX docs; audit trail not).

### D11-N3 (W3) — FX authorization via stage permissions

- Signed-FX upload = EXECUTE on the relevant FX confirmation stage; artifact download/view = VIEW + request scope (bank scoping still enforced). No hardcoded role-code gates where stage permissions can express responsibility; `system_admin` may stay as oversight exception. Backend remains final enforcement.
- **Priority:** High
- **Type:** Stage Permissions / Authorization / Workflow Metadata
- **Impacted areas:** `FxConfirmationUploadRequest`, `EngineCustomsService`, `CustomsDeclarationPolicy`.

### D11-N4 (W4) — Dynamic PDF field mapping mechanism

- No hardcoded field keys (`supplier_name`, `goods_description`, `port_of_entry`). Propose a resolution mechanism; evaluate: (1) semantic field tags in designer, (2) field purpose/type mapping, (3) configurable PDF template mapping, (4) standardized aliases, (5) metadata/label/alias fallback search. Preferred approach = safe, explicit, validation-friendly. Publish of an FX-effect workflow validates required PDF fields resolve; failure = clear validation errors. Approval dates derived from `workflow_history`, not null.
- **Priority:** High
- **Type:** Dynamic Fields / PDF Generation / Workflow Metadata / Validation
- **Cross-ref:** D2-N3 (explicit effect attachment), D4-N5.

### D11-N5 (W5) — Issuance semantics split

- `generated_by` = technical trigger actor; `issued_by` = official business issuer; `signed_uploaded_by` = signed-doc uploader. Transition actor ≠ official issuer unless business confirms.
- **Priority:** Medium
- **Type:** Audit / Business Semantics / Official Document Metadata

### D11-N6 (W6) — Terminology: "External FX Confirmation"

- User-facing labels renamed from customs declaration; legacy terminology tracked as cleanup/migration; existing records/files unbroken; backward compatibility kept; `CD-` number-prefix migration reviewed separately before any change.
- **Priority:** Medium
- **Type:** Terminology / Migration / Product Consistency

### D11-N7 (W7) — `/customs` placement re-check (checked)

- Current `/customs/index.vue` is **engine-era** (uses `useEngineRequests` queue/list) — live UI with a legacy route name, gated by frontend `ROUTE_ROLE_MAP` role middleware. FX upload/download actions absent from it (they lived on the legacy request detail page).
- Decide: keep as Director operational queue (renamed per D11-N6) vs merge FX actions into request detail (D11-N1 recommends detail-page panel; queue page can link there). Access must be backend-enforced via stage permissions, not frontend route-role maps.
- **Priority:** Medium
- **Type:** UX / Routing / Permission Consistency

### D11-N8 (W8) — Storage convention alignment (note)

- Align FX artifact storage with engine documents (one private disk/path convention); changes preserve existing file access or ship a safe migration.
- **Priority:** Low — **Type:** Storage Consistency / Cleanup

### D11-N9 (W9) — Fix immutability model, remove broad bypass

- Declaration stays immutable for official issued fields; signed-doc fields explicitly whitelisted as mutable; no broad `DB::table` bypasses; signed-doc metadata mutations always audited; physical old-file removal per retention policy keeps replacement metadata + audit.
- **Priority:** Medium
- **Type:** Data Integrity / Immutability / Audit
- **Impacted areas:** `CustomsDeclaration::booted()`, `EngineCustomsService`.

---

## D12 — Authentication Chain (APPROVED WITH NOTES)

### D12-N1 (W1) — PIN + MFA + remembered MFA sessions

- **Current behavior:** PIN login (`loginWithPin`) issues a session with **no MFA gate**, even for TOTP-enrolled users or `mfa.enabled` systems.
- **Required change:**
  - PIN stays as an alternative **first factor**; password and PIN logins follow the same MFA policy after the first-factor check.
  - MFA required unless a valid **remembered MFA session / trusted-device token** exists. Duration 8–24h same device/browser, configurable via system settings.
  - Re-login shortly after logout on the same trusted device → no MFA re-prompt while the remembered session is valid; expiry / new device / new browser / suspicious IP / changed environment → MFA required again.
  - Sensitive actions still require step-up (D12-N2) regardless of remembered login MFA.
- **Security requirements:** remembered token stored securely; bound to user + device/browser + expiry; not a frontend flag; backend decides skip-vs-require. Invalidate on: password change, MFA disable/reset, recovery-code regeneration, sign-out-all, account deactivation, admin session invalidation.
- **Priority:** High
- **Type:** Security / MFA / Authentication UX
- **Impacted areas:** `AuthController::login/loginWithPin/verifyOtp`, `MfaService`, new trusted-device storage, system settings.

### D12-N2 (W2) — Step-up MFA for sensitive actions

- Re-verification required before: in-session password change, PIN set/change/disable, MFA/TOTP disable, recovery-code regeneration, admin MFA reset (where applicable). Non-TOTP users under system MFA → configured method (email OTP). Step-up validity window short (5–15 min since last verification), even with a valid remembered login-MFA session. Backend-enforced; frontend prompts UX only.
- **Password reset note:** external reset keeps email OTP; sessions/tokens stay invalidated post-reset; user re-authenticates normally incl. MFA.
- **Priority:** High
- **Type:** Security / Account Protection / Step-up Authentication

### D12-N3 (W3) — Current password required for voluntary change — **CLOSED (verified 2026-07-06)**

- `ChangePasswordRequest` already requires the current password (closure `Hash::check`) and rejects reusing the current password. Remaining sub-item only: audit failed change attempts (roll into D13-N4 audit work).
- **Priority:** Closed
- **Type:** Security / Password Change

### D12-N4 (W4) — Centralized password policy

- One shared policy/rule object replacing inline duplicates (reset / temp change / voluntary change / admin reset). Minimum now: ≥8 chars, upper, lower, digit, confirmation where applicable. Future: history, common-password blacklist, configurable min length, max age/rotation per CBY policy, admin-configurable via system settings.
- **Priority:** Medium/High
- **Type:** Security / Maintainability / Password Policy
- **Impacted areas:** `AuthController::resetPassword`, `ProfileController::changePassword/changeTemporaryPassword`, admin reset paths, a `PasswordPolicy` rule class.

### D12-N5 (W5) — Tightened lockout + per-account limiting

- Lockout threshold 10 → 5 failures (unless usability testing objects); lockout duration configurable; per-account rate limiting independent of IP; IP throttling stays as an additional layer; identical behavior for password and PIN; lockout events audited.
- **Priority:** Medium
- **Type:** Security / Rate Limiting / Brute-force Protection

### D12-N6 (W6) — Session management + recovery-code UX

- "Sign out all sessions" for users; admin session invalidation where appropriate; recovery-code count / low-code warning for TOTP users; regeneration after step-up MFA (old unused codes invalidated); all audited.
- **Priority:** Medium
- **Type:** Account Security / UX / Session Management

### D12-N7 (W7) — Concurrent MFA challenge behavior

- No silent overwrite of a live challenge: return the existing challenge, or explicitly invalidate the previous one with a clear UI message. Consistent across email-OTP login and password recovery. Last-wins acceptable only with explicit invalidation + clear messaging.
- **Priority:** Low/Medium
- **Type:** UX / MFA Consistency

### D12-N8 (W8) — Demo endpoints → D23

- Demo role/user switching verified disabled or safely guarded in production (config `demo.allow_role_switch`); not part of the auth feature surface.
- **Priority:** Tracked in D23 — **Type:** Security Cleanup / Production Exposure

---

## D13 — Profile + User Preferences (APPROVED WITH NOTES)

### D13-N1 (W1) — Disable self-service email change

- Users cannot change their own email from the profile (login identifier + recovery/OTP channel = high-risk action). Email changes go through admin user management, audited.
- **Future option (if self-service ever needed):** current password + fresh step-up MFA + verification link/OTP to the **new** address; email inactive until verified; full audit of request/verification/completion.
- **Priority:** High
- **Type:** Security / Account Recovery / User Profile
- **Impacted areas:** `ProfileController::update` (drop email from validation/fillable path), profile UI.

### D13-N2 (W2) — Remove password-only TOTP disable

- Disabling MFA/TOTP requires a valid TOTP code or fresh step-up MFA — never password alone. Lost authenticator → recovery codes or admin MFA reset (audited). Aligns with D12-N2.
- **Priority:** High
- **Type:** MFA / Account Security / Step-up Authentication
- **Impacted areas:** `ProfileController::disableTotpWithPassword` (remove/disable), frontend fallback flow.

### D13-N3 (W3) — BUG: `active_sessions_count` scope leak

- `$user->tokens()->whereNull('last_used_at')->orWhere('last_used_at', '>', ...)` — ungrouped `orWhere` escapes the tokenable scope, counting other users' recent tokens. Fix: group conditions inside the user scope. Part of D12-N6 session-management improvements.
- **Priority:** Medium
- **Type:** Bug / Session Management / Profile Security

### D13-N4 (W4) — Audit PIN lifecycle

- Audit PIN set / change / disable + failed change/disable attempts where appropriate; never log PIN values; step-up MFA per D12-N2 applies. (Also fold in: audit failed password-change attempts — residual from closed D12-N3.)
- **Priority:** Medium
- **Type:** Audit / Account Security / PIN Management

### D13-N5 (W5) — `mfa_required` display key (note)

- No silent defaults for unregistered display keys; profile security section shows MFA requirement status clearly/consistently.
- **Priority:** Low — **Type:** UX / Display Consistency

### D13-N6 (W6) — Settings save error handling (note)

- Generic 500 acceptable for now; later add typed errors where practical; keep server-side detail logging.
- **Priority:** Low — **Type:** Error Handling / Settings UX

### D13-N7 (W7) — Base64 logo → D20

- Re-check large base64 branding storage in D20 (system settings); prefer proper file upload/storage for large/frequently-changed branding assets.
- **Priority:** Low — **Type:** System Settings / Storage / Branding

### D13-N8 — Phone number policy

- Informational only; keep E.164 validation; not a verified auth/recovery factor. Future SMS OTP/recovery requires phone verification first.
- **Priority:** Low — **Type:** Profile Data / Future Authentication

---

## D15 — Users/Staff, Banks, Merchants (APPROVED WITH NOTES)

### D15-N1 (W1) — Retire stale `committee_director` role-code gates

- Verify seed data / current role codes. If `committee_director` is not an assignable/active role, all checks using it are stale. FX authorization migrates to stage permissions (D11-N3); the FX signer is whoever holds EXECUTE on the FX confirmation stage — not a hardcoded role code. If a Director business role is still needed, it must exist as a real assignable role in the registry and be protected (D3-N5 family) if system-critical.
- **Priority:** High
- **Type:** Authorization / Role Consistency / FX Workflow
- **Impacted areas:** role seeds, `legacyRoleFor` mapper, `CustomsDeclarationPolicy`, `FxConfirmationUploadRequest`, `EngineCustomsService`, `UserPolicy::resetPassword`.

### D15-N2 (W2) — Replace hardcoded `commercial_banks` with classification

- User creation/update + bank/financial-institution logic use D14-N4 classification; submitting entities = `BANKING_SECTOR` orgs; supports exchange companies later. **Model review note:** if `banks` is too narrow, evaluate renaming/extending into a general financial-institution registry.
- **Priority:** High
- **Type:** Organization Classification / User Management / Future Extensibility
- **Impacted areas:** `V1UserController::validateIdentity`, `V1BankController::store`, seeds/migration.

### D15-N3 (W3) — Full admin password-reset coverage

- `system_admin`: reset any non-self user (safety checks apply); `bank_admin`: own bank/org scope only; self-reset via admin endpoint stays blocked. All resets: `must_change_password`, session/token invalidation, audit, centralized D12-N4 policy.
- **Priority:** Medium/High
- **Type:** User Management / Account Recovery / Admin Operations
- **Impacted areas:** `UserPolicy::resetPassword` role list.

### D15-N4 (W4) — Deactivation gated on current responsibility, not authorship

- Authorship never blocks deactivation. Block/require resolution only for: current claim holder on an active request, direct assigned executor (if such assignment exists), sole effective executor of an active stage. Claim held → admin releases/reassigns first. Deactivation kills sessions, audited; authored requests stay historically linked.
- **Priority:** Medium/High
- **Type:** User Lifecycle / Offboarding / Workflow Safety
- **Impacted areas:** `V1UserController::hasActiveWork`, claim admin-release flow (D9), D14-N1 executor check reuse.

### D15-N5 (W5) — Bank suspension blocks new business, not history

- Suspension allowed with historical closed requests; blocks new request creation and new merchant/request activity; never hides history. Optional block/confirm only for active in-flight requests per business choice. **Delete stays strict** (historical-reference guard). Suspension/reactivation audited.
- **Priority:** Medium/High
- **Type:** Bank Lifecycle / Business Operations / Governance
- **Impacted areas:** `V1BankController::isUsed` split into delete-guard vs suspend-guard; `availableWorkflows`/creation checks for suspended banks.

### D15-N6 (W6) — Audit counterparty deletes

- Bank delete, merchant delete (incl. soft deletes), and governance deletes audited; failed guarded deletes auditable where useful. (Extends D14-N3.)
- **Priority:** Medium — **Type:** Audit / Governance / Counterparty Management

### D15-N7 (W7) — V1 admin reset-PIN

- Add V1 reset-PIN (if PIN stays in the auth model): clears PIN, user sets new one, session invalidation as appropriate, audited, follows D12 decisions. Legacy reset-pin migrated/removed in D23.
- **Priority:** Low/Medium — **Type:** V1 Migration / Account Security / Legacy Cleanup

### D15-N8 (W8) — Password duplication → tracked in D12-N4

- User create/admin reset flows adopt the centralized policy; inline duplicates removed.

### D15-N9 (W9) — `users.role` dual-write → tracked in D14-N2

- Dual-write treated as a temporary migration shim only; pivot canonical; column removed post-migration.

### D15-N10 — Merchant purpose CONFIRMED (closes Phase-1 open item)

- Merchants = the bank's importer clients on whose behalf financing/import requests are filed. Bank-scoped management; CBY may create/edit via `merchants MANAGE` capability (registry stewardship ≠ request creation — CBY still cannot create bank requests). Ownership tied to the bank/financial institution; **ownership immutable once used in requests** unless a controlled transfer process is designed later.
- **Priority:** Medium — **Type:** Product Definition / Merchant Management / Bank Scope
- **Impacted areas:** PRODUCT.md/docs update at spec time.

---

## D16 — Reference Data (APPROVED WITH NOTES)

### D16-N1 (W1) — Values retired by deactivation, not deletion

- Delete blocked when the value's table is referenced by any field definition in a PUBLISHED workflow version, or when the value may exist in engine request data. Deactivate = normal retirement (hidden from new selections; stored values stay readable/displayable; old label resolvable after deactivation). Delete only for safe cleanup (no workflow field reference, no runtime data).
- **Preferred guard:** structural — block delete when the value's table is referenced by a field definition of a published/non-archived version; no JSON scanning per delete.
- **Priority:** High
- **Type:** Reference Data / Data Integrity / Historical Readability
- **Cross-ref:** enables D6-N3 grandfathering.
- **Impacted areas:** `ReferenceValue::isInUse()` (close the documented 18.4/18.5 seam), `ReferenceDataService::deleteValue`.

### D16-N2 (W2) — Table delete/deactivate guards

- Delete blocked when referenced by any field definition in a PUBLISHED version. Deactivate blocked when referenced by a PUBLISHED version still serving runtime forms. DRAFT-only references → warn. ARCHIVED-only references → cleanup allowed only if it doesn't break historical display/reporting. UI shows affected workflows/versions/fields before blocking/warning.
- **Priority:** High
- **Type:** Workflow Safety / Reference Data / Dynamic Fields
- **Cross-ref:** consistent with D14-N1 guard semantics.

### D16-N3 (W3) — Table `is_active` = designer-time availability

- Inactive table: not bindable in new fields/draft config; publish blocked when a version introduces a new dependency on an inactive table. Existing published versions keep resolving options (stability; no sudden runtime breakage) unless removed via controlled migration. Value-level `is_active` remains the runtime retirement mechanism.
- **Priority:** Medium
- **Type:** Workflow Versioning / Reference Data Semantics
- **Impacted areas:** field designer/pickers, `WorkflowVersionValidator`, docs.

### D16-N4 (W4) — Immutable-key updates rejected at validation

- `key` create-only for tables and values; update requests reject key changes with 422 (e.g. `prohibitedIf` pattern already used for `reference_table_id`); never let it reach the model's LogicException → 500.
- **Priority:** Medium
- **Type:** API Validation / Error Handling / Data Integrity
- **Impacted areas:** `UpdateReferenceTableRequest`, `UpdateReferenceValueRequest`.

### D16-N5 (W5) — Value-key uniqueness per table

- Unique within its table (same key allowed across tables); enforce at DB level + validation layer where practical.
- **Priority:** Low/Medium
- **Type:** Data Integrity / Reference Data

### D16-N6 — Ownership confirmed: global, CBY-owned

- Central management by authorized CBY/admin users; no per-bank catalogs at this stage; bank-specific data lives in bank-scoped modules (merchants), not global reference data.
- **Priority:** Confirmed
- **Type:** Product Scope / Reference Data Governance

---

## D17 — Audit + Compliance (APPROVED WITH NOTES)

> **ARCHITECTURAL DECISION — Two-layer visibility model for audit / compliance / reports / analytics** (applies to D17, D18, D21; carried into all future specs):
>
> 1. **Screen permissions** answer "can this user access this feature/screen?" — existing matrix unchanged (`reports:VIEW`, `audit:VIEW`, `compliance:VIEW`, `*:EXPORT`, …). No permission → no screen.
> 2. **Organization classification (D14-N4)** answers "what data does the user see inside it?" — the maximum data scope:
>    - **BANKING_SECTOR:** own-organization data only — own requests, own users' relevant actions, own merchants, own documents, own SLA/status/reporting, own compliance warnings, own exports. Never: other banks' data, platform-wide audit, internal National Committee operational actions (except safe business statuses), cross-bank operational details.
>    - **NATIONAL_COMMITTEE:** system-wide scope *when the screen/capability is granted* — all institutions, all requests, cross-bank duplicates, system-wide SLA/audit/compliance/reports/exports. Grants still required per capability; classification alone grants nothing.
>    - **OTHER:** no broad audit/compliance/analytics/reporting access by default; any later access explicitly scoped and justified.
> 3. Both layers backend-enforced; frontend checks UX only. The layers never mix responsibilities.

### D17-N1 — Audit visibility follows the two-layer model

- NATIONAL_COMMITTEE + `audit:VIEW` → platform-wide logs. BANKING_SECTOR: no platform-wide access; if bank-side audit is ever needed → bank-scoped only (safe events for own requests/users/actions; no cross-bank data, no NC operational logs, no unrelated IPs/emails/metadata).
- **Priority:** High — **Type:** Security / Audit Visibility / Data Scope
- **Impacted areas:** `AuditLogPolicy`, `AuditLogController` (scope injection), screen matrix defaults.

### D17-N2 — Compliance visibility follows classification

- NC users with compliance permission → full monitoring. BANKING_SECTOR → own data only; cross-bank intelligence masked. Full cross-bank detail = authorized NC users only. If compliance grows into a module → dedicated compliance screen/capability.
- **Priority:** High — **Type:** Compliance / Data Scope / Security

### D17-N3 — Exports require explicit EXPORT capability + same scope

- `audit:EXPORT` for audit CSV (currently rides on VIEW — fix), `reports:EXPORT` for report exports, `compliance:EXPORT` if added. Exported rows scoped by classification; BANKING_SECTOR exports never contain other institutions' data. Export actions audited (filters, row count, actor, timestamp — audit already does this ✔; extend to all export paths).
- **Priority:** High — **Type:** Authorization / Export Control / Data Scope / Audit

### D17-N4 — Duplicate invoice detection: normalize + mask

- Normalize before detection (trim, uppercase, collapse repeated internal spaces where appropriate); original submitted value preserved for display/audit; detection uses normalized value.
- BANKING_SECTOR users get a **masked** warning ("possible duplicate at another institution") — no institution name, reference, user, or internal details. NC users with compliance permission see full cross-bank detail.
- **Priority:** Medium — **Type:** Compliance / Duplicate Detection / Information Disclosure
- **Impacted areas:** `DuplicateInvoiceChecker`, `RequestProjectionSync` (normalized column), `EngineRequestController` warnings, `ComplianceController::duplicateInvoices`.

### D17-N5 — Explicit export truncation

- 10k cap must be visible: total matching rows (if available), exported count, applied filters, truncation note in file/response. Consider requiring narrower filters for very large exports.
- **Priority:** Low/Medium — **Type:** Export UX / Reporting Accuracy

### D17-N6 — Audit retention + immutability governance

- App-level append-only stays (model events ✔). Define retention: hot-DB duration, archive schedule, archived-log search/restore, archive access control. Consider DB-level immutability (no UPDATE/DELETE grants for the app DB user on audit tables, triggers, append-only patterns). Archive/export operations themselves audited.
- **Priority:** Medium/High — **Type:** Audit Retention / Compliance / Data Governance

### D17-N7 — Expired-document coverage (note)

- Tax-card monitor stays as-is. Future expansion (commercial register, import license, other regulatory documents) only when the merchant/document model clearly supports it. No speculative generic multi-document expiry now.
- **Priority:** Low — **Type:** Compliance / Merchant Documents / Future Enhancement

---

## D18 — Reports + Exports (APPROVED WITH NOTES)

> Follows the D17 two-layer model: screen permissions gate feature access; organization classification bounds data scope. Screen permissions alone never grant system-wide reporting.

### D18-N1 (W1) — `reports:EXPORT` capability enforced

- `reports:VIEW` = view only; `reports:EXPORT` required for export creation AND download of generated files. Matches D17-N3.
- **Priority:** High — **Type:** Authorization / Export Control / Reports
- **Impacted areas:** `ReportExportController::store/download`.

### D18-N2 (W2) — Report export auditing

- Audit export creation, download, and (where useful) failed/denied attempts. Fields: actor, organization, classification, report type, filters, format, row count (job writes it on completion), status, timestamp. Same standard as audit-log export.
- **Priority:** High — **Type:** Audit / Reports / Export Governance
- **Impacted areas:** `ReportExportController`, `GenerateReportExport` (row-count metadata).

### D18-N3 (W3) — Classification-based scope replaces bank_id-null heuristic

- BANKING_SECTOR: own-org data only. NATIONAL_COMMITTEE: system-wide when granted. OTHER: no broad access by default. **Null `bank_id` must never imply system-wide.** All report endpoints + async export jobs enforce this; jobs keep re-deriving scope from the stored requester at execution time (already implemented for bank scope — extend to classification).
- **Priority:** High — **Type:** Data Scope / Organization Classification / Reports Security
- **Impacted areas:** `applyScope` in `V1\ReportController`, `GenerateReportExport`, `ComplianceController::applyScope` (same pattern), D14-N4 dependency.

### D18-N4 (W4) — Export file retention

- Configurable retention (default 30 days); after expiry: physical file deleted/archived, `ReportExport` row kept as history with `EXPIRED` status; expired download → clear error; scheduled cleanup job; cleanup auditable/logged.
- **Priority:** Medium — **Type:** Storage / Export Security / Data Retention

### D18-N5 (W5) — REJECTED counts → tracked in D2-N1

- Reports adopt corrected outcome semantics (Active / Completed / Rejected / Cancelled-Abandoned if added) once D2-N1 lands; until then rejected counts inaccurate/zero.

### D18-N6 (W6) — Status filter whitelist

- Validate report status filters against allowed statuses/outcomes; unknown → clear 422; align with engine list behavior.
- **Priority:** Low — **Type:** API Consistency / Validation

### D18-N7 (W7) — Presets promoted to V1

- Migrate saved report filters to V1 (user-scoped by default; optional shared presets for authorized NC users later). Presets never bypass data scope — a banking-sector preset applies only to own scoped data. Legacy presets controller → D23 after migration.
- **Priority:** Low/Medium — **Type:** Reports UX / V1 Migration

### D18-N8 (W8) — Export failure UX

- Clear FAILED status + UI message; internals logged server-side only; retry where appropriate; failed jobs never leave broken downloadable files.
- **Priority:** Low — **Type:** UX / Async Jobs / Error Handling

### D18-N9 — NC oversight analytics (future enhancement)

- Current 10 reports frozen for immediate scope. Future candidates: per-institution SLA compliance ranking; per-bank volumes + approval/rejection rates; average processing time by stage/institution; cross-workflow bottleneck stages; duplicate-invoice trends across institutions; FX confirmation completion statistics; returned-for-correction rate per institution.
- **Priority:** Future enhancement — **Type:** Analytics / National Committee Oversight

---

## D19 — Notifications + Templates (APPROVED WITH NOTES)

### D19-N1 (W1) — Fix notification action URLs

- Replace stale `/requests/{id}` with the engine route (`/workflows/instances/{id}`) in dispatcher-generated URLs + template sample variables/previews. Verify whether the frontend maps old URLs — if not, old URLs are broken. Existing stored notifications: safe one-time rewrite when the request id maps confidently; otherwise leave as historical rows. New notifications never point at deleted legacy routes.
- **Priority:** High
- **Type:** UX / Workflow Navigation / Legacy Cleanup
- **Impacted areas:** `EngineNotificationDispatcher` (all `actionUrl`s), `NotificationTemplateController::sampleVariables`, optional data migration.

### D19-N2 (W2) — Oversight audience follows the D17 two-layer model

- Oversight notifications require capability/screen permission **and** classification scope. Platform-wide oversight → authorized NATIONAL_COMMITTEE users only; BANKING_SECTOR receives only own-org-scoped notifications; OTHER receives none by default. Cross-bank duplicate notifications masked for any non-NC recipient (generic "possible duplicate at another institution"; no institution name, reference, users, NC internals, or cross-bank metadata).
- **Priority:** High
- **Type:** Notifications / Data Scope / Compliance / Information Disclosure
- **Impacted areas:** `resolveAuditViewers` → classification-aware audience, `afterDuplicateInvoice` body masking.

### D19-N3 (W3) — Audience resolution reuses canonical resolver semantics

- No parallel SQL drifting from `StagePermissionResolver`. Audience resolution respects: active users, active roles, active teams, org scope, stage permissions, single-role model, classification where relevant. Inactive teams/roles grant no eligibility (D8-N1 family). Optimized queries allowed only if semantically equivalent + test-covered.
- **Priority:** Medium
- **Type:** Permission Consistency / Notification Audience / Authorization
- **Impacted areas:** `resolveExecuteHolders`.

### D19-N4 (W4) — Notification preferences: effective but bounded

- Keep preferences with explicit limited scope. Never suppress mandatory security / workflow-critical / compliance-critical notifications (assigned/available workflow action, actionable SLA breach, permission/security changes, account events — always delivered in-app). Users may control non-critical categories (informational, digests, optional email if added). If the dispatcher doesn't consume preferences, wire them into supported categories or hide/disable the UI until implemented.
- **Priority:** Medium
- **Type:** Preferences / Notifications UX / Operational Safety

### D19-N5 (W5) — Email channel: in-app only now, future enhancement

- Workflow events stay in-app; no per-event email. Future: email for critical SLA breaches, pending-action digests, high-severity compliance alerts — respecting preferences, classification scope, masking rules, template whitelist, delivery audit/logging.
- **Priority:** Low/Medium — **Type:** Notification Channels / Future Enhancement

### D19-N6 (W6) — Notification retention policy

- Retention defined per state: unread kept until read/archive or long max; read/archived kept a configurable period (6–12 months suggested); security/compliance-relevant kept longer if policy requires. Scheduled purge/archive job. Notifications are never the audit trail — `audit_logs` remains the compliance record.
- **Priority:** Low/Medium — **Type:** Storage / Retention / Notifications

### D19-N7 (W7) — `readAll` semantics (nit)

- Affects only non-archived unread rows; archived-unread untouched unless explicitly requested.
- **Priority:** Low — **Type:** Inbox Semantics / UX

### D19-N8 — Email template system CONFIRMED as-is

- Preserve: per-type variable whitelisting, HTML stripping at save, escaped rendering, version history + authorship, audit on updates, production SMTP lockdown, no Blade/eval/code execution.
- **Priority:** Confirmed — **Type:** Email Templates / Security / Audit

---

## D20 — System Settings + SMTP (APPROVED WITH NOTES)

> **CORE PRINCIPLE (all future specs): "No active setting without an active runtime consumer."** Every setting gets one disposition: (1) wire as single runtime source, (2) remove from UI/backend, (3) keep only as clearly documented display metadata. Runtime consumers identified per setting; tests verify that changing the setting changes behavior.

### D20-N1 (W1) — Settings to WIRE as runtime source of truth

- **`support_claim_ttl`** → `EngineClaimService` reads it (config fallback only as bootstrap). *High — Claims / Runtime Config.*
- **Login lockout threshold** (prefer rename to `login_lockout_attempts`) → replaces hardcoded 10; aligns D12-N5. *High — Auth / Security Settings.*
- **`login_lockout_duration`** → replaces hardcoded 15 min; aligns D12-N5. *High.*
- **`mfa_required`** → see D20-N2. *High.*
- **`pdf_upload_size_limit`** → document upload validation reads it (replaces hardcoded 10MB); applied consistently to upload endpoints + file field constraints. *Medium — Documents / Upload Policy.*
- **`duplicate_invoice_policy`** (`warn`/`block`) → checker respects it; `block` blocks per approved duplicate rules; D17-N4 masking respected (bank users no cross-bank details; NC full detail). *Medium — Compliance / Runtime Policy.*

### D20-N2 (W2) — `mfa_required` = single MFA switch

- Login MFA gate, profile toggle restrictions, and display all read the DB setting. `config('mfa.enabled')` becomes bootstrap/default only; config and DB must not silently disagree after initialization. Changes audited.
- **Priority:** High — **Type:** MFA / Authentication Policy / Runtime Settings

### D20-N3 (W1) — Settings to REMOVE

- **Voting/committee settings** (committee sizes, quorum, secret_voting, director_tiebreak, voting_session_timeout) — voting out of scope; remove/archive. *Medium — Legacy Cleanup.*
- **Legacy feature flags** (`notifications_phase_1_enabled`, `search_phase_1_enabled`, `customs_print_preview_enabled`, any doubtful-consumer flags) → remove / D23. *Medium.*
- **Unenforced security booleans** (`encrypt_uploads_aes256`, `allow_external_access`, `log_all_audit`, `password_expiry_90_days`) — hidden/removed until actually enforced. Password expiry, if needed, lands inside D12-N4 centralized policy; upload encryption requires real storage behavior first; `log_all_audit` must not exist as a fake switch for mandatory logging. *Medium — False Assurance Cleanup.*

### D20-N4 (W3) — SMTP: real or gone

- **Preferred:** DB-managed SMTP kept only if the runtime mailer actually reads it; approved-CBY-server boot guard stays; production rejects unauthorized hosts; password encrypted+masked; test email uses the same runtime mail path as real sends; changes/tests audited. **Alternative:** if production SMTP stays env-only → remove editable panel, keep read-only status/diagnostics. No fake SMTP panel.
- **Priority:** High — **Type:** SMTP / Runtime Configuration / Admin UX

### D20-N5 (W4) — One email-template system

- Versioned `NotificationTemplate` registry (D19-N8) becomes the only active system. Migrate approved/rejected/returned from the settings blob if still needed; remove blob copies. Rendering keeps whitelist/escaping/no-eval/versioning/audit.
- **Priority:** Medium/High — **Type:** Email Templates / Settings Cleanup

### D20-N6 (W5) — No unvalidated section blobs

- workflow/security sections: define explicit fields + validation + runtime consumer + audit + admin-facing description — or remove from active UI. No arbitrary operational-looking JSON.
- **Priority:** Medium — **Type:** Settings Validation / Configuration Governance

### D20-N7 (W6) — Branding logo as file (absorbs D13-N7)

- Upload as file via storage system; settings hold reference/path/URL; validate type+size; serve via safe public URL/asset endpoint; public settings stay metadata-only; cache-busting version stamp on change.
- **Priority:** Low/Medium — **Type:** Branding / Storage / Public Settings

### D20-N8 (W7) — Fixed-length password mask

- Mask as fixed placeholder (`********`); never reflect real length; never return the secret.
- **Priority:** Low — **Type:** Secret Handling

### D20-N9 (W8) — Consistent settings cache

- Either runtime reads go through the cache that update invalidates, or remove the fake invalidation. No cache-theater.
- **Priority:** Low — **Type:** Maintainability

### D20-N10 (W9) — Maintenance mode: real or removed

- If kept: admin toggle, system banner, defined access during maintenance, sensitive-action blocking as required, audited enable/disable, safe admin fallback access. Otherwise remove page from navigation → D23.
- **Priority:** Low/Medium — **Type:** Operations / Admin UX / Legacy Cleanup

### D20-N11 (W10) — Public settings endpoint CONFIRMED

- Safe general/branding fields only; never operational/security/SMTP config; keep version stamp for cache-busting.
- **Priority:** Confirmed — **Type:** Public Configuration / Branding

---

## D21 — Dashboard + Global Search (APPROVED WITH NOTES)

> Core principles: screen permission gates access; classification bounds data scope; workflow metadata drives workflow-specific grouping/labels/KPIs; backend scoping mandatory, frontend UX-only.

### D21-N1 (W1) — Metadata-driven dashboard buckets

- Long-term: stage classification for dashboards/reporting comes from explicit stage metadata (`dashboard_bucket` / `semantic_tag` / `reporting_category`), not hardcoded codes (INTERNAL/SUPPORT/FX/FX_CONFIRM/FINAL). Publish validation checks required tags; missing metadata → warn or block per bucket importance. Canonical codes may remain as migration-era compatibility hints only.
- **Priority:** High
- **Type:** Dashboard / Workflow Metadata / Reporting Accuracy
- **Cross-ref:** same mechanism family as D11-N4 semantic tags; feeds `EngineRequestReadModel` buckets.

### D21-N2 (W2) — Dashboard selection: capability/classification-driven

- Dashboard access requires screen permission; data scope follows classification (BANKING_SECTOR own-org; NATIONAL_COMMITTEE system-wide when granted; OTHER none by default). Role may still choose layout/widgets (single-role model), but never as sole authorization/scope source. `committee_director` branch resolved with D15-N1 (remove/migrate if not an active role; otherwise make it a real protected assignable role).
- **Priority:** High
- **Type:** Dashboard Authorization / Role Model / Organization Classification

### D21-N3 (W3) — Classification scope for dashboards + search

- Same D17/D18 model; null `bank_id` never implies system-wide dashboard/search access; system-wide comes from classification + permission only.
- **Priority:** High — **Type:** Data Scope / Dashboard / Search / Security

### D21-N4 (W4) — SWIFT/FX officers are CBY-side in search

- Never scope them by `user->bank_id` (null-bank → accidental empty results today). Visibility from screen/capability + classification + stage visibility. **Preferred:** with proper permission they see NC/CBY-wide FX/customs search results; if business excludes them, hide the group entirely.
- **Priority:** Medium — **Type:** Search Scope / FX Workflow / Organization Classification
- **Impacted areas:** `SearchController::searchCustoms` role list.

### D21-N5 (W5) — Escape LIKE wildcards in search

- Escape `%`/`_` (reuse `escapeLike`); literal-input semantics unless wildcard search is an explicit feature; keep caps + min length.
- **Priority:** Low — **Type:** Search Safety / Query Correctness

### D21-N6 (W6) — Rejected bucket → tracked in D2-N1

- Dashboards/reports align on final outcome semantics (Active / Completed / Rejected / Cancelled-Abandoned) post-D2-N1.

### D21-N7 (W7) — Dashboard controller refactor (later phase)

- Move aggregate logic from the 608-line controller into services/query classes during the refactor phase (Phase 5), behavior-stable.
- **Priority:** Low — **Type:** Refactor / Maintainability

### D21-N8 (W8) — Recent-search storage via preferences service

- Use the preferences abstraction; no direct column writes; search must still never fail on preference-write failure.
- **Priority:** Low — **Type:** Preferences / Maintainability

---

## D22 — Financing Utilization / Global Financing Cap (APPROVED WITH NOTES)

> **Core decision:** live product feature, not legacy. The global cap (≤100% total requested percentage per merchant tax number + invoice across non-rejected eligible requests, cross-bank) is a regulatory/business rule — backend-enforced, with advisory UI in the engine wizard.

### D22-N1 (W1) — Rebuild the utilization advisory in the engine wizard

- Indicator near invoice/merchant/percentage fields: used %, remaining %, blocked/likely-to-exceed, low-capacity warning. Old `FinancingUtilizationBar.vue` (recoverable via `git show 0e9f1eae^:frontend/app/components/request/FinancingUtilizationBar.vue`) is reference only — rebuild for the engine wizard. Advisory is informational; backend enforcement authoritative; no frontend reliance if the endpoint is unavailable.
- **Priority:** High — **Type:** UX / Financing Cap / Engine Wizard

### D22-N2 (W2) — Publish-time validation for financing-effect workflows + no silent skip

- Workflows with the financing reserve effect must have resolvable mappings for merchant/tax number, invoice number, requested percentage — via the D11-N4 mechanism direction (semantic tags / purpose metadata / explicit effect configuration), never fragile field names alone. Publish blocks silent-skip configurations.
- **Runtime:** silent skip removed/reduced. Enforcing workflows fail safely when required data can't resolve; if skip is deliberately allowed for non-enforcing workflows, log/audit a high-severity configuration warning. Regulatory workflows never bypass the cap silently.
- **Priority:** High — **Type:** Workflow Validation / Regulatory Control / Financing Enforcement

### D22-N3 (W3) — Advisory endpoint classification scoping + masking

- BANKING_SECTOR: query utilization only for merchants/tax numbers within own org scope; no free cross-system probing. NATIONAL_COMMITTEE (with permission): system-wide. OTHER: none by default. Backend-mandatory scoping.
- **Masking:** bank users get aggregates only (used/remaining/blocked) — never other bank names, references, users, internals. NC may get fuller details via a future oversight view if permissions allow.
- **Priority:** High — **Type:** Data Scope / Financing Advisory / Information Disclosure

### D22-N4 (W4) — Capacity-freeing statuses + draft concern

- Freeing: REJECTED + (once added) CANCELLED, ABANDONED. Consuming: active/in-flight + completed/closed (unless the business rule changes). Reports, advisory endpoint, and enforcement ledger share one eligibility rule set.
- **Drafts:** ACTIVE drafts holding capacity indefinitely is risky — linked to D1-N2. Either drafts don't reserve until a deliberate submit/reserve stage, or they are abandoned/cancelled via a clear flow (auto or manual). Interim behavior documented until the abandon flow ships.
- **Priority:** High — **Type:** Financing Ledger / Workflow Outcomes / Draft Lifecycle

### D22-N5 (W5) — One shared invoice/key normalization helper

- Financing ledger, duplicate detection, advisory endpoint, and indexed invoice projections all use the same normalization (trim, uppercase, collapse internal spaces per D17-N4); originals preserved for display/audit; backfill/migration for existing projected keys as needed.
- **Priority:** Medium — **Type:** Data Quality / Compliance / Financing Ledger

### D22-N6 (W6) — Effect binding → tracked in D2-N3

- Financing effect attached via workflow metadata/designer configuration; publish validation confirms the reserve stage + field mappings; stage codes compatibility hints only.

### D22-N7 — Backend enforcement model CONFIRMED

- Enforcement stays backend-side, atomic with the transition (rollback on breach); named-lock + row-lock + sum-after-lock concurrency strategy retained unless a later implementation review finds safer; advisory/frontend never enforcement.
- **Priority:** Confirmed — **Type:** Transaction Safety / Financing Enforcement

---

## D23 — Legacy Cleanup + Dev-Only Endpoints (APPROVED WITH NOTES — cleanup/risk track)

> **Core sequencing rule:** migrate active consumers → verify no dependency remains → deprecate → remove in one controlled wave → full regression. Never delete live legacy endpoints before consumer migration.

### D23-N1 (W1) — BUG: `V1BankController::isUsed()` dropped relation

- `$bank->importRequests()` references a removed model/relation (tables dropped in P5) → fatal 500 on deactivate/delete of a bank with no users/merchants. Remove the check; rebuild suspend/delete guards on current references (engine requests, users, merchants) per D15-N5 semantics (delete strict on history; suspension blocks new business only). Regression test: unused bank deactivates/deletes without 500.
- **Priority:** High — **Type:** Bug / Legacy Cleanup / Bank Lifecycle — **Cross-ref:** D15-N5.

### D23-N2 (W2) — Demo impersonation routes absent from production

- Routes registered only when `APP_ENV` explicitly allowed (local/staging); flag remains but never the sole barrier; production has **no route**, not a disabled one. `auth/demo-users` never unauthenticated in production-like envs; no passwordless impersonation in production ever. Every demo switch in allowed envs audited (actor, target, timestamp, IP, environment). `.env.example` stays disabled.
- **Priority:** High — **Type:** Security / Dev-only Endpoints / Impersonation Risk

### D23-N3 (W3) — Live legacy consumer migration

- `/api/users` → `/v1/users` (`staff.vue`, `AccountRecoveryDialog.vue`); `/api/banks` → `/v1/banks` (`admin/banks.vue`, `merchants.vue`); `/api/audit*` → `/v1/audit-logs` + compliance endpoints (`audit.vue`); `/api/report-presets` → V1 presets (D18-N7); legacy reset-pin → V1 (D15-N7). No removal before migration + verification.
- **Priority:** Medium/High — **Type:** Migration Sequencing / V1 Cleanup

### D23-N4 (W4) — Audit stats / risk indicators: no silent feature loss

- Review usefulness on `audit.vue`; useful widgets rebuilt as V1 under audit/compliance namespace with D17 two-layer visibility; stale/unclear panels removed and documented. No UI panels left on legacy-only endpoints.
- **Priority:** Medium — **Type:** Audit / Compliance / V1 Migration

### D23-N5 (W5) — Dead endpoint/module removal (after final sweep)

- Legacy `NotificationController` routes; legacy `ReportController` (+ dead voting reports); legacy `MerchantController` file; `document-types` module; legacy `/requests` tests + stale frontend references; dead simplified-status constants + route-role maps; stale customs route-role maps post-D11-N6; stale `/requests/{id}` URL generation post-D19-N1. Remove routes/controllers/composables/tests/UI links together — no half-removed modules.
- **Priority:** Low/Medium — **Type:** Legacy Cleanup / Dead Code Removal

### D23-N6 (W6) — Dropped-table reference purge

- Remove/rewrite: `stageIsBound` + `fieldIsUsed` wrong-table checks, all `ImportRequest` references, any guard checking dropped structures — replaced with engine request / workflow-version references. Regression tests on previously failing cleanup paths.
- **Priority:** High — **Type:** Legacy Cleanup / Runtime Safety — **Cross-ref:** D4-N6, D6-N9, D23-N1.

### D23-N7 (W7) — `users.role` column removal → D14-N2 execution here

- Inventory all readers/writers; migrate resources, auth payloads, policies, demo endpoints, frontend auth state to pivot; drop the column when compatibility ends; never authorization/presentation source meanwhile.
- **Priority:** High — **Type:** Role Model / Legacy Cleanup

### D23-N8 (W8) — Stale `committee_director` gates → D15-N1/D11-N3 execution here

- Verify assignability; stale → remove hardcoded gates (FX auth = stage permissions); needed → real protected assignable role.
- **Priority:** High — **Type:** Role Cleanup / FX Authorization

### D23-N9 (W9) — Placebo settings/flags cleanup → D20 execution here

- Remove voting/committee settings, consumer-less flags, unenforced security booleans (unless implemented), duplicate email-template blobs post-consolidation; maintenance page implemented or removed.
- **Priority:** Medium — **Type:** Settings Cleanup / False Assurance

### D23-N10 (W10) — Namespace policy: freeze non-duplicates

- Migrate true duplicates only. Current non-versioned single implementations (`auth/*`, `profile/*`, `settings/*`, `search`, `dashboard/stats`, `financing/utilization`) stay for now; `/api/v1` consolidation is a later documentation-consistency effort with compat/deprecation handling — never a cleanup blocker.
- **Priority:** Low/Medium — **Type:** API Namespace / Migration Strategy

### D23-N11 (W11) — Report presets → D18-N7. D23-N12 (W12) — Reset PIN → D15-N7.

### D23-N13 (W13) — Cleanup wave sequence (authoritative)

1. Build missing V1 replacements → 2. update frontend consumers → 3. update composables → 4. update tests → 5. verify zero legacy traffic/consumers → 6. remove routes/controllers/files → 7. full regression → 8. release notes list removed endpoints/modules.
- **Priority:** High — **Type:** Cleanup Plan / Regression Safety

---

# CONSOLIDATED INDEX — Phase 2 COMPLETE (2026-07-06)

## Discussion status (all 23 approved)

| D# | Topic | Status | Note count |
|----|-------|--------|-----------|
| D1 | Request creation + drafts | Approved | N1–N11 |
| D2 | Transitions + actions | Approved | N1–N9 (**N2 CLOSED-verified**) |
| D3 | Visibility, queues, graph | Approved | N1–N9 |
| D4 | Definitions + version lifecycle | Approved | N1–N11 (D1-N11 verified implemented) |
| D5 | Stages, transitions, actions | Approved | N1–N7 |
| D6 | Fields + rules | Approved | N1–N10 |
| D7 | Stage permissions (designer) | Approved | N1–N8 (+D14 pre-note) |
| D8 | Screen permissions | Approved | N1–N7 (+D15 pre-note) |
| D9 | Claims / locking | Approved | N1–N6 (D1-N10 leave-guard verified implemented) |
| D10 | Documents | Approved | N1–N9 |
| D11 | FX confirmation | Approved | N1–N9 (git archaeology done) |
| D12 | Auth chain | Approved | N1–N8 (**N3 CLOSED-verified**) |
| D13 | Profile + preferences | Approved | N1–N8 |
| D14 | Orgs/teams/roles + classification | Approved | N1–N6 |
| D15 | Users, banks, merchants | Approved | N1–N10 (merchant purpose CONFIRMED) |
| D16 | Reference data | Approved | N1–N6 |
| D17 | Audit + compliance | Approved | N1–N7 (**two-layer model decision**) |
| D18 | Reports + exports | Approved | N1–N9 |
| D19 | Notifications + templates | Approved | N1–N8 (template system confirmed) |
| D20 | System settings | Approved | N1–N11 (**no-placebo principle**) |
| D21 | Dashboard + search | Approved | N1–N8 |
| D22 | Financing cap | Approved | N1–N7 (live feature confirmed) |
| D23 | Legacy cleanup | Approved | N1–N13 |

## Architectural decisions (govern all specs)

1. **Two-layer visibility model** (D17): screen permissions gate features; organization classification (D14-N4: BANKING_SECTOR / NATIONAL_COMMITTEE / OTHER) bounds data scope. Applies to audit, compliance, reports, exports, dashboards, search, notifications, financing advisory. Null `bank_id` never implies system-wide.
2. **No placebo settings** (D20): every active setting has a runtime consumer, or it's removed.
3. **Metadata over hardcoded anchors**: side effects (D2-N3), PDF fields (D11-N4), dashboard buckets (D21-N1), financing mappings (D22-N2) move to explicit workflow metadata with publish validation. Stage codes/field names = compatibility hints only.
4. **Stage-scoped claims** (D9): released on transition, backend-enforced everywhere (drafts, documents, transitions).
5. **Single role source** (D8-N2/D14-N2): one active role per user, `user_roles` pivot canonical, `users.role` removed.
6. **Evidence preservation** (D10-N7/D11-N2): replacement over deletion; physical cleanup only via retention policy; audit always.
7. **Backend final enforcement** — frontend gating is UX only (recurring; D9-N2, D10-N1, D17, D21).

## Verified/closed during review

- D2-N2 notifications-after-commit — already implemented (`DB::afterCommit`).
- D12-N3 current-password-on-change — already implemented (`ChangePasswordRequest`).
- D1-N11 single-published-version + runtime pinning — already implemented (publish auto-archive).
- D1-N10 leave guard — substantially implemented (commits 274148fa/c16cceba); verify coverage.
- FX UI (D11-N1) + financing bar (D22-N1): existed pre-cutover, deleted in `0e9f1eae` (2026-07-01), engine replacements never built — rebuild, don't restore.
- Merchant purpose confirmed (D15-N10); reference data global/CBY-owned (D16-N6).

## Confirmed bugs (fix in implementation phase)

1. `V1BankController::isUsed()` dropped `importRequests()` relation → 500 (D23-N1). High.
2. Stage-permission partial-update consistency bypass (D7-N1). High.
3. `active_sessions_count` orWhere scope leak (D13-N3). Medium.
4. Claim carry-over across transitions + heartbeat extension (D9-N1). High.
5. `REJECTED` status never set — reports/buckets/capacity wrong (D2-N1). High.
6. Notification `action_url` → deleted routes (D19-N1). High.
7. fx_swift customs-search null-bank filter → empty results (D21-N4). Medium.
8. Immutable-key updates → 500 instead of 422 (D16-N4). Medium.
9. `resolveExecuteHolders`/identity building includes inactive teams/roles (D8-N1/D19-N3). High.

## Key dependencies (spec sequencing)

- **D14-N4 classification** → prerequisite for D7-N6, D17, D18-N3, D21-N2/N3, D22-N3, D19-N2.
- **D2-N1 final outcomes** → prerequisite for reports/dashboards rejected counts (D18-N5, D21-N6), capacity freeing (D22-N4), abandon status (D1-N2).
- **D11-N4 semantic field mapping** → shared mechanism for FX PDF, financing validation (D22-N2), dashboard buckets (D21-N1).
- **D12-N1 remembered-MFA + D12-N2 step-up** → prerequisite for D13-N1/N2 flows.
- **D23-N13 cleanup wave** → after all consumer migrations.

## Open Questions Carried Forward

- (none — all D1–D23 questions resolved by recorded decisions)

---

# PHASE 4 — SYSTEM WEAKNESS REVIEW (APPROVED 2026-07-06)

Cross-cutting synthesis; each item traces to D-note evidence. SW-1..SW-8, SW-10, SW-12..SW-14 approved as covered by recorded decisions.

| SW | Theme | Severity | Covered by |
|----|-------|----------|-----------|
| SW-1 | Four parallel authorization vocabularies (+dual role storage) | High | D3-N5, D14-N2, D15-N1, D17, D21-N2 — Phase 6 states one authorization decision tree: classification bounds scope, capabilities gate screens, stage permissions gate workflow actions, role selects UX layout only |
| SW-2 | Metadata engine with hardcoded semantic anchors (hooks, PDF fields, financing keys, dashboard buckets) | High | D2-N3, D11-N4, D21-N1, D22-N2 — one semantic-mapping mechanism + publish validation; highest-leverage architectural investment |
| SW-3 | Outcome semantics hole (REJECTED never set; no cancelled/abandoned) | High | D2-N1, D22-N4, D1-N2 |
| SW-4 | Data-scope layer missing (bank_id-null ⇒ system-wide) | High | D14-N4 + D17 two-layer model, wired in one wave across audit/compliance/reports/exports/dashboards/search/notifications/financing |
| SW-5 | Frontend-only enforcement gates (claims on drafts/docs, submit edge, option membership, FILE evidence) | High | D9-N2, D10-N1/N2, D1-N1, D6-N2 — one "server-side parity" work package |
| SW-6 | Missing referential lifecycle guards (org/team/role/reference-data/bank vs published workflows) | High | D7-N4, D14-N1, D16-N1/N2 — one shared published-workflow reference-guard service + impact-preview UI (D14-N6) |
| SW-7 | Auth hardening gaps (PIN/MFA bypass, no step-up, email change, TOTP disable, lockout) | High | D12-N1/N2/N4/N5, D13-N1/N2 — one auth-hardening package |
| SW-8 | Placebo configuration syndrome (settings, prefs, cache theater, meaningless flags) | Medium-High | D20 principle + D5-N1, D16-N3, D19-N4, D9-N4 |
| SW-9 | **API envelope standardization — APPROVED with migration caution.** Standardize on rich envelope (`error.code`, `error.message`, `error.fields`, `request_id`); keep stable business codes incl. engine codes; standardize pagination meta where practical; frontend converges on one extraction. Controlled refactor planned in Phase 6 — transition period/adapter if compatibility needed; never mixed with critical functional fixes. | Medium | New (approved this phase) |
| SW-10 | Retention/lifecycle program absent (audit, notifications, exports, documents, orphan files) | Medium (**may become High under strict CBY retention rules or fast data growth — business policy input required**) | D17-N6, D18-N4, D19-N6, D10-N6/N7 consolidated into one policy |
| SW-11 | **Operational monitoring baseline — APPROVED as real system requirement.** Failed queue-job visibility; scheduler heartbeat/missed-schedule detection; dispatch/export/SLA-scan/claim-sweep/mail/stage-hook failure visibility; admin health surface or external monitoring integration; minimal runbook. Failures never log-only silent; user-facing failed states clear; internals stay in logs. Minimal baseline first — no observability over-engineering. | Medium | New (approved this phase) |
| SW-12 | Performance debts (resolver N+1, page-1 lists, dashboard query fan-out, 3MB public payload) | Low-Medium | D3-N1, D2-N6, D3-N8, D20-N7 |
| SW-13 | Duplication debt (password rules ×5, audience SQL, invoice normalization, 2 settings systems, 2 template systems, 2 audit stacks, legacy pairs, escapeLike) | Medium | D12-N4, D19-N3, D22-N5, D20-N5, D23 — Phase 5 verifies consolidations land as shared code |
| SW-14 | Error-handling sharp edges (LogicException→500s, silent skips, catch-alls, silent truncation) | Medium | D16-N4, D11-N9, D22-N2, D17-N5 |
| SW-15 | **STRENGTHS TO PRESERVE** — append-only audit model; transition gate discipline; financing concurrency protocol; publish auto-supersede + version pinning; injection-safe template pipeline; after-commit notifications; derived requests capability; query-level bank scoping; evidence-preserving documents. **Future refactors must not weaken these.** | — | Preservation constraint on all phases |

---

# PHASE 5 — CODE REFACTOR REVIEW (APPROVED 2026-07-06)

**Rule:** behavior preserved unless tied to an approved D-note/SW item; characterization/snapshot tests before moving code; no opportunistic cleanup in core services; mechanical refactors never mixed with functional changes.

## Refactor register (numbering corrected — R1–R11)

| R | Area | Decision | Behavior |
|---|------|----------|----------|
| R1 | `DashboardController` → per-audience query classes (snapshot tests first) | Approved | Preserving |
| R2 | `EngineRequestController` → lifecycle / documents / claims / FX controllers + filter object (same routes) | Approved | Preserving |
| R3 | `PasswordPolicy` rule object — **step 1 only** (extract current rules); policy upgrade = D12-N4 functional | Approved (step 1) | Preserving |
| R4 | Audience-resolution consolidation — **sequence mandatory:** (1) characterization tests (org-only / org+team / org+role / org+team+role / inactive cases), (2) extract canonical matcher, (3) prove equivalence, (4) only then D8-N1 inactive filtering as separate functional commit | Approved with caution | Step 2 preserving; step 4 functional |
| R5 | Shared invoice/key normalization helper — **step 1** trim-only wiring (ledger, duplicate checker, projections); **step 2** D17-N4/D22-N5 upgrade + backfill = functional | Approved (two-step) | Step 1 preserving; step 2 functional |
| R6 | `RoleCodes` constants + sweep | Approved | Preserving |
| R7 | Settings-systems consolidation | **Deferred** to D20 functional wave | — |
| R8 | `WorkflowDesignerService` split | **Skipped by design** (KISS); only shared reference-guard extraction with D14-N1 | — |
| R9 | API envelope standardization | **Deferred** — planned migration only: scope defined in Phase 6, frontend adapter strategy, stable business codes preserved, never big-bang | Contract change (spec'd) |
| R10 | Frontend: claim-session composable extraction from `[id].vue` (rides D9-N3); legacy composables **replaced not refactored** (D23-N3/N5); store thinning rides D3-N1 | Approved as wave-riding | Rides functional waves |
| R11 | `EngineRequestReadModel` buckets | **No preemptive refactor** — replaced by D21-N1 metadata buckets | — |

## Pre-refactor test prerequisites (approved; write BEFORE refactoring)

1. Claim lifecycle races: transition, heartbeat, sweep, stage change.
2. Bank deactivate/delete unused-bank regression (covers stale `importRequests()` 500 — D23-N1).
3. Audience-resolution characterization matrix: org-only, org+team, org+role, org+team+role, inactive role/team captured pre-change.
4. Dashboard response snapshots per role.
5. Stage-permission partial-update consistency (D7-N1: merge-with-existing-row semantics).

## Approved ordering

1. Pre-refactor tests → 2. mechanical refactors (R1, R2, R3s1, R5s1, R6) → 3. R4 consolidation after equivalence proof → 4. R7 with D20 wave; R9 per migration plan; R10 rides D9-N3/D3-N1/D23 → 5. R8, R11 skipped by design.

## Do-not-touch list (approved)

`EngineTransitionService::execute` internals; `EngineFinancingLedger` locking protocol; `AuditLog` append-only guards; `TemplateRenderer` sanitization chain; publish auto-supersede/version-pinning logic. Changes only via explicit approved functional specs.

---

# PHASE 6 — FINAL WORK-PACKAGE REGISTER (APPROVED STRUCTURE, 2026-07-06)

One spec file per package under `docs/superpowers/specs/`, written one at a time in dependency order, reviewed between each. Every change inside a spec is marked: behavior-preserving refactor / approved functional change / bug fix / migration-cleanup / new operational requirement.

| WP | Title | Scope (traceability) | Depends on | Risk |
|----|-------|----------------------|------------|------|
| **WP-0** | Safety net: tests + confirmed bug fixes | 5 pre-refactor test suites (claim races, unused-bank guard, audience matrix, dashboard snapshots, permission partial-update) + test-first fixes: D23-N1 bank 500, D7-N1 partial-update bypass, D13-N3 session count, D16-N4 immutable-key 422s, D21-N4 fx_swift search scope, D19-N1 action-URL fix + confident-mapping data rewrite | — | Minimal |
| **WP-R** | Mechanical refactors | R1 dashboard extraction, R2 engine-controller split, R3s1 PasswordPolicy extraction, R5s1 normalization helper (trim-only), R6 RoleCodes constants, R4 steps 1–3 (matcher consolidation after characterization) | WP-0 | Low |
| **WP-1** | Organization classification foundation | D14-N4 schema/enum/migration, D15-N2 `commercial_banks` hardcode replacement, D7-N6 creation gating, D7-N8 null-org exclusion | WP-0 | Medium |
| **WP-2** | Outcome semantics | D2-N1 final outcomes + status derivation, D1-N2 deliberate draft creation + abandon flow, D22-N4 capacity-status alignment, D18-N5/D21-N6 report+dashboard outcome alignment | WP-0 | Medium |
| **WP-3** | Designer validation pack | Validator rules: D1-N1, D4-N1, D4-N2+D7-N2 (+audience preview), D5-N1, D5-N2, D5-N3, D2-N8, D6-N4, D6-N5, D6-N6, D6-N10. Designer lifecycle: D4-N3, D4-N4, D4-N8, D4-N9, D7-N3, D7-N5, D5-N4, D5-N5 | WP-2 (outcome rule) | Low-Medium |
| **WP-4** | Semantic metadata mechanism | D11-N4 mapping design (mechanism selection study), D2-N3 explicit effect attachment, D21-N1 metadata dashboard buckets, D22-N2 financing mapping validation + no-silent-skip | WP-3 | High (architecture; highest leverage) |
| **WP-5** | Claims: stage-scoped + server parity | D9-N1 release-on-transition, D9-N2 draft claim enforcement, D9-N5 heartbeat safety, D10-N1 document claim gating, D9-N3 claim-loss UX (+R10 composable), D9-N4 cache-mirror removal, D3-N6 queue claim badges, D9-N6 note | WP-0, WP-R | Medium |
| **WP-6** | Auth hardening | D12-N1 remembered MFA, D12-N2 step-up, D12-N4s2 centralized policy upgrade, D12-N5 lockout (wired via D20-N1 settings), D12-N6 sessions + recovery codes, D12-N7 challenge behavior, D13-N1 email-change removal, D13-N2 TOTP-disable fix, D13-N4 PIN audits, D13-N5 | WP-0 (R3s1 helpful) | Medium-High |
| **WP-7** | Two-layer visibility wave | D17-N1/N2 audit+compliance scope, D17-N4 masking + R5s2/D22-N5 normalization upgrade + backfill, D18-N1/N2/N3 export capability+audit+classification scope, D21-N2/N3 dashboard/search scope, D19-N2 notification audiences, D22-N3 advisory scope, D8-N1 + R4s4 inactive-identity filtering | WP-1, WP-R | High (broad, one repeated pattern) |
| **WP-8** | Documents, fields, FX completion | D6-N1 scoped options, D6-N2/N3 membership+grandfathering, D6-N7/N8 FILE+typed values, D10-N2 required-file evidence, D10-N3+D3-N2 field-visibility on outputs/documents, D10-N4 scanning, D10-N5 checksum verification, D10-N7 replacement flow, D11-N1 FX panel rebuild, D11-N2/N3/N5/N9 FX replacement/auth/semantics/immutability, D11-N6/N7 terminology+placement, D22-N1 financing advisory UI | WP-4 (mapping), WP-7 (visibility) | Medium-High |
| **WP-9** | Governance lifecycle guards | D14-N1 workflow-aware guards (+shared reference-guard service), D14-N3+D15-N6 delete audits, D14-N5 isProtected verification, D14-N6 impact preview, D15-N4 deactivation rework, D15-N5 bank suspension semantics, D16-N1/N2/N3 reference-data guards + table-active semantics, D8-N6 permission refresh | WP-0 (pairs well with WP-7) | Medium |
| **WP-10** | Role model migration | D14-N2/D23-N7 pivot-canonical + `users.role` removal, D15-N1/D23-N8 `committee_director` resolution, D15-N3 reset-password coverage, D15-N7/D23-N12 reset-PIN V1, D3-N5 protected role codes | WP-0, WP-R (R6) | Medium-High (authorization core) |
| **WP-11** | Settings truth wave | D20-N1 wire list, D20-N2 MFA switch, D20-N3 remove list, D20-N4 SMTP decision, D20-N5 template consolidation (+R7 settings-service consolidation), D20-N6 section validation, D20-N7 logo storage, D20-N8 mask, D20-N9 cache, D20-N10 maintenance decision | WP-0 (D20-N1 lockout keys feed WP-6) | Medium |
| **WP-12** | Runtime UX pack | D3-N1 server-side lists+KPIs (+R10 store rework), D2-N4 confirmation dialogs, D2-N5 transition audit diffs, D2-N7 comment feedback, D17-N5 export truncation, D18-N8 export failure UX, D19-N4 preference wiring, D19-N7 readAll semantics | WP-0, WP-R (R2) | Low-Medium |
| **WP-13** | Retention + operations | SW-10 consolidated retention policy (D17-N6 audit, D19-N6 notifications, D18-N4 export files, D10-N6/N7 document storage) — needs CBY policy input; SW-11 monitoring baseline + runbook | — (parallel-capable) | Low |
| **WP-14** | Legacy cleanup wave (terminal) | D23-N13 sequence: V1 replacements (D23-N4 audit widgets, D18-N7 presets), consumer migrations (D23-N3), demo-route production removal (D23-N2), dead-code purge (D23-N5/N6), R9 envelope standardization staged during migrations, full regression + release notes | WP-10 + all consumer migrations | Medium (sequencing-managed) |

**Dependency spine:** WP-0 → WP-R → {WP-5, WP-12}; WP-0 → WP-1 → WP-7 → WP-8; WP-2 → WP-3 → WP-4 → WP-8; WP-6, WP-9, WP-11, WP-13 parallel-capable; WP-10 → WP-14 (terminal).

**Writing order:** WP-0 → WP-R → WP-1 → WP-2 → then dependency order; one spec at a time with review between each.

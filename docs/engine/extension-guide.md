# Extension Guide

How to safely extend the workflow engine without breaking its invariants.
Read [`dynamic-vs-fixed.md`](dynamic-vs-fixed.md) first — it explains which
of the things below are genuinely designer-configurable at runtime versus
which require a code deploy, so you don't reach for the Workflow Designer
to do something only a migration can do.

For the entity chain referenced throughout (`WorkflowDefinition →
WorkflowVersion → WorkflowStage/WorkflowTransition/StagePermission/
FieldDefinition/StageFieldRule`), see `architecture/02-workflow-engine.md`
(**planned, not yet written** — Step 4 of the consolidation plan; today's
authority is
[`../01-workflow-and-business-rules.md`](../01-workflow-and-business-rules.md)
together with
[`../decisions/semantic-mapping.md`](../decisions/semantic-mapping.md)).
For permission mechanics, see
[`../architecture/03-permission-model.md`](../architecture/03-permission-model.md).

---

## Add a new workflow / stage / transition (no deploy required)

Entirely data-driven through the Workflow Designer API, gated to
`WorkflowVersion` rows in `DRAFT` state
(`App\Enums\WorkflowVersionState::isEditable()` is true only for `DRAFT`).

1. Create or clone a `WorkflowVersion` under the target `WorkflowDefinition`.
2. Add `WorkflowStage` rows (`code`, `sort_order`, `is_initial`, `is_final`,
   `final_outcome`, `sla_duration_minutes`, `requires_claim`, and —
   critically — `semantic_role`; see below).
3. Add `WorkflowTransition` rows connecting stages (`from_stage_id`,
   `action_id`, `to_stage_id`).
4. Add `StagePermission` rows granting VIEW/EXECUTE per role/org/team/user
   as needed (see the permission model doc for how these resolve).
5. Run `POST /api/v1/workflow-versions/{workflowVersion}/validate` before
   publish.
6. Publish via `POST /api/v1/workflow-versions/{workflowVersion}/publish`.

**Always set `semantic_role`.** Every occupiable stage should carry one of
`StageSemanticRole`'s 8 fixed cases (`INITIAL_ENTRY`, `BANK_REVIEW`,
`SUPPORT_REVIEW`, `SWIFT`, `EXECUTIVE_REVIEW`, `FINANCE_RESERVE`,
`FX_CONFIRMATION`, `FINAL`). Leaving it null works today only because
`SemanticResolver` falls back to a hardcoded stage-`code` alias table
(`SemanticRegistry::stageCodeAliases()`) — a compatibility path with its
own exit criteria (see the dynamic-vs-fixed doc), not something to rely on
for new work.

### Validation before publish

Two cooperating read-only services in `backend/app/Services/Workflow/`:

- **`WorkflowVersionValidator`** — orchestrator: exactly one initial
  stage, at least one final stage, final-outcome consistency, no
  duplicate stage codes/keys, transition integrity, every non-final stage
  has an outgoing transition, `DYNAMIC_SELECT` field sources are valid.
- **`WorkflowPublishRulePack`** (rules V-1..V-9) — `validateReachability()`
  does a BFS from the initial stage and flags unreachable or dead-end
  stages; `validateEffectiveExecutors()` checks every non-final stage has
  at least one active EXECUTE holder via
  `StagePermissionAudience::executeHolderIds()` (a stage nobody can act on
  will not publish); plus `validateFinalStageOutgoing()`,
  `validateActionOutcomeConsistency()`, `validateSelfLoops()`,
  `validateFieldRules()`, `validateFieldConstraints()`.

A stage that's unreachable, has no executor, or breaks final-outcome
consistency will fail publish, not fail silently at runtime — fix the
validator's specific complaint rather than working around it.

---

## Add a new field (no deploy required)

`FieldDefinition` (`key`, `semantic_tag`, `label`, `type`, `options`,
`reference_table_id`, `dynamic_source`, `is_required`, `is_system`) +
`StageFieldRule` (`stage_id`, `field_id`, `is_visible`, `is_editable`,
`is_required`) let a designer attach a field to a stage and control its
per-stage visibility/editability/requiredness without a deploy — as long
as the field's `type` is one of the existing `FieldType` cases.

The frontend renders these through
`frontend/app/components/workflow/DynamicFormField.vue` (parent:
`DynamicForm.vue`), which dispatches on `field.type` to the matching
shadcn-vue input. **Adding a brand-new `FieldType` case is a deploy**, not
a designer action: `App\Enums\FieldType` is fixed (`TEXT`, `NUMBER`,
`DATE`, `SELECT`, `DYNAMIC_SELECT`, `TEXTAREA`, `FILE`, `CURRENCY`,
`CHECKBOX`), and each case needs a matching branch added to
`DynamicFormField.vue` — the frontend renderer is not purely data-driven
off the enum.

---

## Add a new semantic role, capability, screen, or effect code (deploy required)

These are fixed enums/catalogs with no runtime CRUD. Do not look for a
Workflow Designer UI for these — there isn't one.

- **New `StageSemanticRole` case** — edit the PHP enum
  (`App\Enums\StageSemanticRole`), update `SemanticRegistry`/
  `SemanticResolver` consumers as needed, deploy. Adding a 9th role is a
  code change with migration/backfill implications for existing stages.
- **New screen or `ScreenCapability`** — `ScreenController` exposes only
  `index()`; there is no store/update/destroy. Add the `Screen` row via
  migration + `ScreenPermissionSeeder` update, then grant it to roles via
  `PUT /api/v1/roles/{role}/screen-permissions` (existing screens only — creating
  a new one is still a deploy).
- **New effect code** — `App\Enums\WorkflowEffectCode` is a fixed enum
  (currently `financing.reserve`, `fx.confirmation_pdf`). A `WorkflowStage`
  can be data-driven **attached** to an existing effect code
  (`attached_effects`, a JSON array on the stage), but inventing a new
  effect code means: add the enum case, write the effect class
  (`__invoke(EngineRequest, WorkflowTransition, User)`, see
  `App\Services\Workflow\Effects\FinancingLedgerEffect` for the shape),
  register it in `App\Services\Workflow\StageHookRegistry` (populated in
  `AppServiceProvider::boot()`), deploy. Effect firing happens inside
  `EngineTransitionService::execute()`'s transaction, and **every** effect
  failure rolls back the transition — but the error envelope the caller
  sees depends on what was thrown. A **domain exception** your effect
  raises on purpose (`EngineException`, `FinancingLimitExceededException`,
  `FinancingLockTimeoutException`, `CustomsException`) propagates as-is,
  preserving its own `error_code` and status — write one of these when the
  effect needs to reject the transition with a specific, client-facing
  reason. Any **other, unexpected** `\Throwable` gets wrapped as a generic
  `EngineException('STAGE_HOOK_FAILED', 422)` so the client never sees a
  bare 500; this path also logs via `OperationalAlertLogger::failure()`.
  Prefer throwing a domain exception over letting an unexpected one get
  wrapped, when the failure is meaningful to the caller.

---

## Add a dashboard metric

Current dashboards are **Level 1**: fixed Vue template, dynamic data —
there is no metadata-driven widget catalog today (a `Level 2` catalog is a
documented future enhancement, not present in code). Which pipeline a new
metric belongs to depends on which dashboard family it's for; the two are
separate contracts, not one shared one.

**Operational metrics (`MyWorkDashboard.vue`)** — actionable/claimed/
tracking/SLA/recent-activity counts for a workflow-executor user — belong
to the `GET /api/dashboard/work` contract, implemented by
`App\Http\Controllers\Api\V1\DashboardWorkController::work()`. Its
`actionable` section is produced directly by
`App\Services\Workflow\UserActionableRequestQuery` (constructor-injected
into the controller) — the same query `/my-queue` uses — so the dashboard
count, preview, nav badge, and `/my-queue` never disagree. Any new
_actionable-work_ metric must go through this query, not a bespoke count;
anything else operational (claimed, tracking, SLA) is added to
`DashboardWorkController`'s own response shape.

**Analytics/governance metrics** (`SystemAdminDashboard`,
`BankAdminDashboard.vue`) belong to a different pipeline:
`App\Services\Dashboard\DashboardStatsService::stats()`, which dispatches
on role via a hardcoded `match(true)` with one private method per
analytics role hand-computing its own counters, served by
`DashboardController::stats()` (`GET /api/dashboard/stats`). Add a new
analytics counter to the relevant private method here, applying
`DataScope::applyTo()` if the query needs org-scoping (see the permission
model doc — this is not automatic), then surface it in the corresponding
analytics Vue component.

**Never mix the two.** Do not add a per-role dashboard branch, and do not
compute an "actionable work" count from a `DashboardStatsService` query or
any other bespoke query — actionable work must continue to resolve
through `UserActionableRequestQuery` exclusively. See
`architecture/04-dashboard-architecture.md` (**planned, not yet written**
— Step 3; today's authority is AGENTS.md's "Dashboard Architecture"
section) for the shared actionable-work invariant this must not violate.

---

## What "safely" means here

Before any of the above, check:

- **Does this break the actionable-work invariant?** The actionable count,
  dashboard preview, `/workflows` nav badge, and `/my-queue` must all stay
  equal **by record ID**, not just by count. A new stage or permission
  grant that changes who can act on a request changes this set — verify
  it doesn't silently diverge across those four surfaces.
- **Does this stage need a `semantic_role`?** If it's occupiable by a
  request, yes — see above.
- **Does this add a new mutating service method?** If so, it needs its own
  explicit `AuditService::log()` call — this is never automatic (see the
  permission model doc, §5).
- **Does this touch org-scoped data?** If so, call `DataScope::applyTo()`
  explicitly — it is a plain service method, not a global scope, and is
  not applied for you.

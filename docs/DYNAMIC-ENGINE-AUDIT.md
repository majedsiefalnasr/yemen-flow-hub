# Dynamic Workflow Engine — Repository Audit (2026-06-25)

Fresh code-evidence audit. Supersedes BMAD-era framing (Epic 18, sprint-status.yaml, story files — all removed from this repo). No BMAD references below; this doc + `docs/LOVABLE-PARITY-INVENTORY.md` are the current source of truth for this initiative.

---

## Executive Summary

**Maturity state: C — Mid-Migration**, but lopsided: the new engine's **backend is essentially feature-complete** (runtime, transition execution, designer, governance, screen permissions), while the **frontend and live data path are 100% on the legacy system**. There is no dynamic form renderer anywhere (the "designer" builds schemas; nothing consumes them to render a fillable request). No migration/backfill tooling exists, and no feature flag distinguishes which system is "live" — selection happens purely by which API route a given page calls.

In practice: a user today creates a request, votes, gets SWIFT/FX/customs handling — all through `import_requests`/`RequestStatus`/`users.role`/`request_votes`. The `engine_requests` API exists, is transactionally correct, and is never called by any page.

---

## Built

| Area | Evidence |
|---|---|
| Engine runtime — instance table | `engine_requests` (migration `2026_06_24_000001`): `current_stage_id` FK, `status`, `data` JSON, hybrid indexed columns (`amount`/`currency`/`invoice_number`), optimistic `version` counter, `request_percentage`. `engine_request_documents` table (field_id/stage_id/checksum/soft-deletes). |
| Engine runtime — transition executor | `EngineTransitionService::execute()` — `DB::transaction` + `lockForUpdate` + optimistic version check + `StagePermissionResolver` EXECUTE check + `StageFieldRuleValidator` + stage mutation + `WorkflowHistoryEntry` write + `AuditService` log + `StageHookRegistry` entry/exit hooks, all atomic. Fully implemented, not a stub. |
| Engine runtime — API surface | `EngineRequestController`: `index`, `myQueue` (دوري SLA-priority queue), `store`, `show`, `executeAction`, `draft`, `history`, `graph`, document upload/list/download/delete. |
| Workflow designer — schema tables | `workflow_definitions/versions/stages/transitions/actions`, `stage_permissions`, `field_groups`, `field_definitions` (9 field types incl. DYNAMIC_SELECT, full validation constraints), `stage_field_rules`. |
| Workflow designer — lifecycle | `WorkflowDesignerService`: `DRAFT → PUBLISHED → ARCHIVED` (`WorkflowVersionState`), `publishVersion()` (re-validates, archives prior published, `lockForUpdate`, audit logs), `cloneVersion()` (PUBLISHED-only source, deep-copies stages/transitions/actions/field-rules into new DRAFT). |
| Workflow designer — frontend | `admin/workflows.vue` + `WorkflowStageEditor/TransitionEditor/FieldDesigner/ProcessGraph/PublishPanel/ActionsCatalog.vue`, all backed by real `$fetch` composables (`useWorkflows.ts` → `/api/v1/workflow-definitions`, confirmed not mock data). |
| Governance layer | `organizations`, `teams`, `roles` (table), `user_roles`/`user_teams` pivots — real CRUD controllers with `DB::transaction`+`lockForUpdate`+optimistic-version, audit logged. Frontend `admin/{orgs,teams,roles}.vue` wired to real APIs. |
| Screen permissions | `screens`/`screen_permissions` tables, `PermissionService::userHasCapability()` (cached per role), `RoleScreenPermissionController`. Frontend `ScreenGuard.vue` + `useScreenPermissions.ts` + `middleware/screen.ts` enforce it live on several admin pages. |

---

## Partially Built

| Area | What exists | What's missing |
|---|---|---|
| Dynamic form system | Schema tables (`field_definitions`/`field_groups`/`stage_field_rules`) + backend `StageFieldRuleValidator` that validates `engine_requests.data` against them at transition time | **No runtime renderer.** `WorkflowFieldDesigner.vue` only *builds* field schemas (designer-side CRUD), it does not render a fillable form from them. No `DynamicForm.vue` equivalent exists anywhere in `frontend/app`. |
| Governance authorization cutover | `roles`/`user_roles` tables + `User::roles()` relation exist | `RoleController::update()` carries an explicit code comment: authorization is "still driven by the legacy `users.role` column (no pivot propagation yet)." Both models coexist unreconciled on the same `User` class. |
| Per-row migration flag | `voting_rule_version` column (1=legacy, 2=new) keeps in-flight legacy requests on old rules | This is a row-level flag for one subsystem (voting), not a system-level cutover mechanism — no equivalent exists for the engine vs. legacy request flow as a whole. |

---

## Missing

1. **Frontend consumer for `engine_requests`.** Zero composables/stores/pages call `/api/v1/engine-requests*` or `executeAction`. No instance queue, no instance detail page, no "دوري" queue UI (despite the backend `myQueue` endpoint existing).
2. **Dynamic form renderer.** The single largest functional gap — without it, `engine_requests` has no way to be filled out by an end user even if wired up.
3. **Dynamic stage-progress visualization.** `WorkflowProgress.vue`/`RequestProgress.vue` are keyed to the fixed `RequestStatus` enum, not to `workflow_stages`. No equivalent for dynamic instances.
4. **Legacy → engine data migration tooling.** No artisan command, seeder, or script copies `import_requests` rows into `engine_requests`, nor backfills `user_roles` from `users.role`. Confirmed by exhaustive search of `backend/app/Console/Commands` and seeders.
5. **Cutover flag / kill-switch.** No `.env` toggle or config value exists to switch which system is authoritative; removing legacy code today would break every live page.

---

## Parallel Legacy Systems (confirmed live, not orphaned)

| System | Confirmed live via |
|---|---|
| `import_requests` / `RequestStatus` enum | `requests/new.vue` → `RequestFormTabs.vue` → `useRequests.ts::create()` → `POST /api/requests` → `ImportRequestController::store()`. This is the only request-creation path real users exercise today. |
| `users.role` scalar column | Read directly in `WorkflowController`, `SearchController`, `ProfileController`, `RequestDocumentPolicy`, `TraderPolicy`, `CustomsDeclarationPolicy`; frontend `middleware/role.ts` gates pages via `definePageMeta({ requiredRoles })`. |
| Voting subsystem (`request_votes`, `VotingService`) | `VotingPanel.vue` rendered directly inside `requests/[id]/index.vue`; `voting.store.ts`/`useVoting.ts` actively used, not dead code. |
| Merchants (legacy) vs Traders (new) | Both `MerchantController` and `TraderController` have separate real CRUD; both `/merchants` and `/traders` are simultaneously present in the nav (`constants/workflow.ts`), neither retired. |
| SWIFT / customs / FX confirmation flows | All still hang off `ImportRequest`-based endpoints (`/api/workflow/{id}/swift-upload`, `/api/customs/{id}/generate`, `/api/requests/{id}/fx-confirmation-upload`) called from `useRequests.ts`. |

Migration-date evidence: legacy schema = `2026_05_13_*`–`2026_06_08_*`; dynamic-engine schema = `2026_06_22_*`–`2026_06_24_*`, added strictly afterward with zero changes to the legacy tables. This reads as "new engine bolted on alongside," not "legacy being dismantled."

---

## Technical Debt

- **Duplicate role/authorization model** on the same `User` class: scalar `role` column (legacy, live) + `roles()` BelongsToMany via `user_roles` (new, inert for authz). Both must eventually collapse to one; currently a maintenance trap — editing one without the other silently does nothing for live authorization.
- **Duplicate entity concept**: Merchant vs Trader. Per earlier locked decision (DI-5 in superseded memory), `merchants` was meant to be canonical — but Trader was built first with its own PII/snapshot model and is now equally live. Needs an explicit reconciliation decision, not silent drift.
- **Duplicate request concept**: `import_requests` vs `engine_requests`, with no bridge. Every day this persists, more legacy-flow data accumulates that will eventually need backfilling or will be permanently orphaned from the new model.
- **Voting contradicts engine design intent**: prior architecture decision called for voting to be removed entirely under the dynamic engine (single-approval EXEC stage via designer actions), but the full voting stack is still live and growing in the legacy path.
- **No flag = no safe partial rollout.** Because there's no toggle, any attempt to test the engine path in production would require shipping a second, fully-parallel UI rather than incrementally routing some traffic to it.

---

## Recommended Superpowers Workstreams

### Workstream A — Dynamic Runtime Wiring
**Goal:** make `engine_requests` reachable from the UI at all.
**Scope:** instance list/queue page (consume `EngineRequestController::index`/`myQueue`), instance detail page (consume `show`/`executeAction`/`history`/`graph`), wire to real backend (already built).
**Dependencies:** none on new backend work — backend is ready. Soft dependency on Workstream B for a usable create/edit experience (read-only instance view is buildable without it).
**Complexity:** Medium. Mostly new frontend pages/composables/store against an already-correct API; main risk is matching the optimistic-concurrency (`version`) and stage-permission UX patterns the backend already enforces.

### Workstream B — Dynamic Form Renderer
**Goal:** let a stage's `field_definitions`/`field_groups`/`stage_field_rules` actually render as a fillable, validated form at runtime — the missing link between designer output and usable requests.
**Scope:** a `DynamicForm`-equivalent Vue component reading field metadata + visibility/editability/required rules per stage; client-side validation mirroring `StageFieldRuleValidator`; dynamic_select support (reference-table-backed dropdowns).
**Dependencies:** Workstream A (needs an instance context to render into). Reference-data tables/admin UI (already built) feed `DYNAMIC_SELECT` fields.
**Complexity:** High. This is genuinely new functionality, not wiring — the hardest remaining piece in the whole migration, and currently invisible because nothing surfaces it as "missing" until someone tries to actually fill out an engine_request.

### Workstream C — Legacy Cutover & Data Migration
**Goal:** retire `import_requests`/`RequestStatus`/`users.role`-as-authority/`request_votes`/legacy SWIFT-customs-FX flow once A+B are functional, with a real data path for in-flight legacy requests.
**Scope:** backfill script (`import_requests` → `engine_requests`, `users.role` → `user_roles`), a cutover flag/feature toggle for staged rollout, removal of dead legacy code once traffic fully moves, voting-subsystem removal (per prior architecture decision) replaced by single-approval EXEC stage actions.
**Dependencies:** hard dependency on A and B being functionally complete and verified; this should not start until there's a working engine-based request flow to migrate users *to*.
**Complexity:** High — not technically complex per se, but high-risk (irreversible data operations, live-user-facing cutover, requires a rollback plan).

### Workstream D — Governance Consolidation
**Goal:** collapse the dual role/authorization model (legacy `users.role` scalar vs. new `roles`/`user_roles`) into one source of truth, and reconcile Merchant vs. Trader into a single canonical entity.
**Scope:** propagate `user_roles` into actual authorization checks (replace `$user->role ===` call sites across policies/controllers/middleware with `$user->roles()->...` or a unified accessor), then drop the scalar column; make an explicit decision on Merchant vs. Trader (keep one, alias/retire the other) and migrate data + nav accordingly.
**Dependencies:** can start independently of A/B/C (it's about consolidating the *governance* layer, not the request/workflow layer) but should land before or alongside Workstream C, since cutover work will also touch authorization checks.
**Complexity:** Medium-High. Mechanically straightforward (find/replace call sites) but high blast-radius — touches every policy and route guard in the app; needs careful regression coverage before removing the legacy column.

---

## Final Question: Building vs. Completing?

**The project is primarily completing the runtime + cutover of an already-built Dynamic Workflow Engine foundation — not building one from scratch.**

Evidence:
- The hard backend engineering — atomic transition execution with locking/optimistic-concurrency/permission/validation/audit/hooks, full workflow designer with draft/publish/clone versioning, governance tables with real CRUD, screen-permission enforcement — is **done and correct**, per direct code reading in `EngineTransitionService`, `WorkflowDesignerService`, `PermissionService`.
- What remains is **not engine design work**, it's **integration and migration work**: build the missing frontend consumer (Workstream A), build the one genuinely missing piece — the dynamic form renderer (Workstream B) — and then retire the legacy parallel system safely (Workstream C/D).
- The single biggest remaining unknown is Workstream B (dynamic form rendering), which is real new feature work, not glue code — so it would be inaccurate to say *only* wiring remains. But it is one well-scoped component, not a second engine.

So: **mostly cutover/completion, with one pocket of genuine net-new feature work (the dynamic form renderer) blocking it.**

# Functional / RBAC / Workflow Audit — Phase 1: System Discovery

**Audit type:** Role & permission correctness, workflow designer→runtime fidelity, UI functional + UX audit.
**Distinct from** `docs/audit/` (performance & scalability audit, closed 2026-07-08). This audit reuses its architecture baseline (`docs/audit/01-architecture.md`) but has a different objective: _does every user see and do exactly what they are allowed — no more, no less?_

**Method:** static code review at `main` (post-`6fb84010`), evidence cited as `file:line`. Dynamic verification (API probes, browser tests) happens in Phases 2–5. Every item below is labeled **Verified** (read in code) or **Assumption** (needs dynamic confirmation).

---

## 1. System architecture overview (Verified)

| Layer           | Implementation                                                                                                                                                                                                              |
| --------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Backend         | Laravel 11, Sanctum SPA cookie auth, MySQL, Redis (cache + queues)                                                                                                                                                          |
| Frontend        | Nuxt 4 / Vue 3, Pinia, Tailwind v4, shadcn-vue, RTL-first Arabic                                                                                                                                                            |
| API             | ~209 routes under `/api` (`backend/routes/api.php`); all authenticated routes wrapped in `auth:sanctum` + `active` + `throttle:api-default`                                                                                 |
| Authorization   | **No route-level authz middleware.** All authorization is controller-level: 20 policies (`backend/app/Policies/`) + inline `PermissionService::userHasCapability()` checks + engine-specific `StagePermissionResolver`      |
| Workflow engine | Fully metadata-driven ("dynamic engine"): `workflow_definitions → workflow_versions → workflow_stages / workflow_actions / workflow_transitions / stage_permissions / field_groups / field_definitions / stage_field_rules` |
| Request model   | `EngineRequest` (`status` ∈ ACTIVE/CLOSED/REJECTED/CANCELLED/ABANDONED + `current_stage_id` + `data` JSON + projection columns)                                                                                             |

### Authorization stack (four independent mechanisms — Verified)

1. **Stage permissions** (`stage_permissions` rows; org/team/role/user columns, NULL = wildcard, AND within row, OR across rows, EXECUTE ⊃ VIEW) — resolved by `StagePermissionResolver` (`backend/app/Services/Workflow/StagePermissionResolver.php`). Sole routing gate for engine view/queue/transitions. Users with no organization always denied (`:53`, `:133`).
2. **Screen permissions** (`screens` + `screen_permissions` per governance role) — `PermissionService` (`backend/app/Services/Authorization/PermissionService.php`), 1h role-keyed cache; `requests` screen capability _derived_ from stage permissions on published versions, not manually granted.
3. **Data scope** (`DataScope`, `backend/app/Services/Authorization/DataScope.php`): org classification NATIONAL_COMMITTEE → system-wide; BANKING_SECTOR → own `bank_id`; anything else → `1=0` deny-all. Applied at Eloquent query level in lists (`EngineRequest::scopeForUser`).
4. **Role codes** (`RoleCodes`, `backend/app/Support/RoleCodes.php`): hard checks (`hasRoleCode`, `isSystemAdmin`) used in User/Bank policies, SearchController, dashboards, claim admin override, FX authorization, admin settings.

**Observation (cross-cutting risk):** mechanisms 2 and 4 overlap and disagree on the source of truth — designer-family policies are capability-driven (`WorkflowDefinitionPolicy:15` checks `workflow_designer` MANAGE) while governance policies are role-code-driven (`UserPolicy`, `BankPolicy` check `SYSTEM_ADMIN`). See high-risk area H2.

### Transition execution chain (Verified — strong)

`POST /v1/engine-requests/{id}/actions` → `EngineRequestPolicy::execute` (bank scope + active + stage EXECUTE) → `EngineTransitionService::execute()` (`backend/app/Services/Workflow/EngineTransitionService.php:40-192`):
`DB::transaction` → `lockForUpdate` → re-check active → optimistic `version` check → transition must originate from current stage → re-check EXECUTE → claim ownership (if `requires_claim`) → required-comment check → **field rules** (hidden field present ⇒ reject; read-only changed ⇒ reject; required empty ⇒ reject on exit; FILE fields need server-side linked document evidence — `StageFieldRuleValidator`) → status from `is_final`/`final_outcome` → projection sync → `workflow_history` + `audit_logs` (correlation id, sensitive-field diff masking) → stage hooks inside txn (rollback on failure) → notifications after commit.

Duplicate-click / concurrency safety: row lock + `version` optimistic check ⇒ second submit gets `REQUEST_STALE`. (Verified in code; regression tests exist under `tests/Feature/Engine/`.)

### Read-side symmetry (Verified)

`EngineRequestResource` filters `data` through `StageFieldOutputFilter` (stage-level `is_visible`); field-linked documents suppressed when owning field hidden; `fx_panel` capabilities computed per user; `can_execute` computed from stage permissions. **Note:** field visibility is **stage-scoped, not viewer-scoped** — `filterRequestData(..., ?User $viewer)` accepts but ignores the viewer (`StageFieldOutputFilter.php:36`). All viewers at a stage see the same field set. Whether per-role field hiding was a requirement needs product confirmation (Missing info M5).

---

## 2. Role, permission, and organization model

### Actual role catalog (Verified — `RoleCodes.php`)

| Code (DB)            | API-mapped enum (`UserRoleMapper`) | Org side |
| -------------------- | ---------------------------------- | -------- |
| `intake`             | DATA_ENTRY                         | Bank     |
| `internal_reviewer`  | BANK_REVIEWER                      | Bank     |
| `bank_admin`         | BANK_ADMIN                         | Bank     |
| `fx_swift`           | SWIFT_OFFICER                      | Bank     |
| `support`            | SUPPORT_COMMITTEE                  | CBY      |
| `committee_manager`  | EXECUTIVE_MEMBER                   | CBY      |
| `committee_director` | COMMITTEE_DIRECTOR                 | CBY      |
| `fx_confirm`         | (serializes as EXECUTIVE_MEMBER)   | CBY      |
| `system_admin`       | CBY_ADMIN                          | CBY      |

- Users: single **active** role enforced (`User::assertSingleActiveRole`), pivot `user_roles.is_active`; role switch via `assignActiveRole` deactivates-but-keeps prior pivot rows (`User.php:160-181`) — see risk H1.
- Organizations carry `classification` (NATIONAL_COMMITTEE / BANKING_SECTOR / other) driving data scope; teams are org-scoped and feed stage-permission matching.
- Request creation gate: only BANKING_SECTOR users with a `bank_id` (`RequestCreationGate`), **and** EXECUTE on the published version's initial stage.
- System admin: list-wide visibility (`EngineRequest::scopeForUser` bypass, `EngineRequestPolicy::view` bypass), but **no execute bypass** (`can_execute` is assignment-based even for admins — `EngineRequestResource.php:58-77`); blocked from merchant MANAGE even if granted (`PermissionService::userHasCapability:334-343`).

### Documentation drift (Verified)

`AGENTS.md` canonical status enum (22 statuses: DRAFT…COMPLETED) and role enum describe the **legacy static workflow**, not the shipped dynamic engine (5 runtime statuses + stage codes; 9 DB role codes). `docs/user-view/*.md` and `docs/01/03/05/06` predate the engine in places. This is a standing confusion hazard for any contributor or AI tool and must be reconciled (candidate finding F-DOC-1).

---

## 3. Page & feature inventory (Verified routes/pages; functional status = Phase 5)

### Frontend pages (34) and guards

| Page                                                                                                                | Guard (middleware → meta)        | Backing API family        |
| ------------------------------------------------------------------------------------------------------------------- | -------------------------------- | ------------------------- |
| `/login`, `/reset-password`                                                                                         | guest/public                     | `auth/*`                  |
| `/`, `/dashboard`                                                                                                   | auth + role (`ROUTE_ROLE_MAP`)   | `dashboard/stats`         |
| `/workflows` (queue+list), `/workflows/new`, `/workflows/instances/[id]`                                            | auth + screen `requests`         | `v1/engine-requests*`     |
| `/customs` (legacy alias)                                                                                           | auth + role                      | FX confirmation endpoints |
| `/merchants`                                                                                                        | auth + role map                  | `v1/merchants`            |
| `/reports`                                                                                                          | auth + roles (`REPORTING_ROLES`) | `v1/reports/*`            |
| `/audit`                                                                                                            | auth + role map                  | `v1/audit-logs*`          |
| `/notifications`                                                                                                    | auth + role map                  | `v1/notifications*`       |
| `/staff` (BANK_ADMIN), `/bank/users` (screen `users`)                                                               | mixed role/screen                | `v1/users*`               |
| `/admin/{banks,orgs,teams,roles,staff,reference-data,screen-permissions,workflows,settings,health,email-templates}` | screen or role meta (mixed)      | `v1/*` governance + admin |
| `/settings/*`, profile/MFA pages                                                                                    | auth                             | `profile/*`, `settings*`  |
| `/forbidden`, `/unauthorized`                                                                                       | —                                | —                         |

Guard mechanics (Verified): `auth.global.ts` (login + forced password change) runs globally; `screen.ts` checks `/auth/me`-hydrated screen capabilities **client-side only** (`import.meta.server` skip); `role.ts` checks mapped role. `00.visual-bypass.global.ts` fabricates an authenticated CBY_ADMIN user when `NUXT_PUBLIC_VISUAL_BYPASS=true` — build-time flag, must be provably off in production builds (risk H6). Frontend guards are UX-only by design; backend re-checks everything (confirmed for engine + governance + designer; Phase 2 verifies remaining endpoints one-by-one).

### Backend API families (Verified against `routes/api.php`)

Auth (incl. demo-user switch endpoints gated by `config('demo.allowed_environments')` — risk H6) · Governance (orgs/teams/roles/users/banks/merchants) · Screen permissions (matrix/show/update) · Reference data · Workflow designer (definitions/versions/stages/actions/transitions/stage-permissions/field-groups/fields/field-rules, publish/archive/clone/validate/graph) · Engine runtime (list/queue/stats/store/show/form-schema/actions/draft/abandon/history/graph/documents/claim/FX) · Audit logs + async export · Compliance · Reports + exports + presets · Notifications inbox · Profile/MFA/sessions · Admin settings/health/notification templates · Search · Dashboard stats.

---

## 4. Workflow designer → runtime architecture map (Verified)

```text
Designer UI (/admin/workflows, screen: workflow_designer)
  → WorkflowDefinition / WorkflowVersion (DRAFT → PUBLISHED → ARCHIVED)
      • edit endpoints authorize via capability 'workflow_designer' MANAGE
      • published/archived versions immutable (WorkflowVersionImmutableException;
        WORKFLOW_IMMUTABLE_STATE — designer-only concept)
      • publishVersion(): validates via WorkflowVersionValidator + WorkflowPublishRulePack,
        archives the prior PUBLISHED version of the same definition,
        clears ALL role screen-permission caches (WorkflowVersionController:129)
  → stored metadata: stages (is_initial / is_final / final_outcome / requires_claim /
        sla_duration_minutes / semantic_role / status ACTIVE),
        transitions (from→to, action, requires_comment),
        stage_permissions (org/team/role/user × VIEW|EXECUTE),
        field definitions + per-stage field rules (visible/editable/required)
  → runtime binding: EngineRequest pins workflow_version_id at creation
        (EngineRequestService::create:97); only PUBLISHED versions creatable;
        existing requests keep their version after later publishes (stage/transition
        lookups are version-scoped via current_stage_id / from_stage_id match)
  → API: form-schema (visible fields + rules + dynamic options),
         graph (nodes/edges + user's execute_stage_ids),
         resource.can_execute / data filtered per stage visibility
  → UI: DynamicFormField disables on !is_editable; actions rail = graph edges from
        current stage, only when can_execute; claim banner when requires_claim
  → execution: EngineTransitionService (see §1 chain) → workflow_history + audit_logs
        → EngineNotificationDispatcher (audience from stage_permissions, after commit)
```

Permission granularity note: **EXECUTE is stage-level.** Any executor of a stage may fire _every_ outgoing transition of that stage. There is no per-transition/per-action permission in schema or resolver. The audit prompt's assumption of per-action permissions does not match the implemented model — designer configs must express "different people approve vs reject" as separate stages. (Confirm intent — Missing info M4.)

Claiming: `requires_claim` stages enforce claim-before-execute at service level; TTL in DB (`claim_expires_at`), heartbeat endpoint; admin claim-override path exists in `EngineClaimService:112` (Phase 3 reviews override semantics vs "CBY_ADMIN not a workflow super-actor").

---

## 5. Candidate findings from discovery (to verify in Phase 2 before rating final)

| ID   | Candidate                                                                                                                                                                                                                                                                                                                                                                                                                                       | Evidence                                                                                  | Preliminary severity                                |
| ---- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------- | --------------------------------------------------- |
| CF-1 | `User::isSystemAdmin()` ignores `user_roles.is_active` (and role `is_active`): matches **inactive** pivot rows. `assignActiveRole()` deactivates-but-keeps old pivots ⇒ a user demoted _from_ system_admin keeps admin privileges everywhere `isSystemAdmin`/role-code fallback is used (audit-log scope-wide read, engine list bypass, Gate hooks, claim override). `hasRoleCode()` has same any-role fallback when no active role.            | `backend/app/Models/User.php:222-244`, `:160-181`                                         | High (privilege retention)                          |
| CF-2 | `PUT /v1/roles/{role}/screen-permissions` accepts grants on `ADMIN_ONLY_SCREENS` (workflow_designer, users, roles, banks, orgs, reference_data, screen_permissions) — excluded from matrix UI but not from update validation (`$validScreenKeys` excludes only `requests`). Capability-driven policies (designer family) honor such grants ⇒ API-only path to hand designer control to any role, contradicting the "not customizable" contract. | `RoleScreenPermissionController.php:71-80`, `:163-177`; `WorkflowDefinitionPolicy.php:15` | High (contract-violating escalation-by-delegation)  |
| CF-3 | List-vs-detail scope asymmetry: `EngineRequestPolicy::inScope` returns true for any `bank_id === null` user regardless of org classification, while `DataScope` denies lists (`1=0`) for non-NC/non-banking orgs. With a wildcard (all-NULL) `stage_permissions` row, such a user can open any request by ID while lists appear empty.                                                                                                          | `EngineRequestPolicy.php:67-74` vs `DataScope.php:32-36`                                  | Medium (depends on wildcard rows + odd org data)    |
| CF-4 | `/auth/me` capability overlay builds identity **without** `is_active` filters on teams/roles, unlike the runtime resolver ⇒ nav chrome can show `requests` access that every real endpoint denies (or hide access that exists).                                                                                                                                                                                                                 | `PermissionService.php:292-297` vs `StagePermissionResolver.php:176-184`                  | Low-Medium (UX/consistency, fail-open display only) |
| CF-5 | `WORKFLOW_IMMUTABLE_STATE` rendered 403 by the global exception map but 409 by `WorkflowVersionController`; AGENTS.md says 409. Two paths, two codes.                                                                                                                                                                                                                                                                                           | `bootstrap/app.php:201-209` (per perf audit) vs `WorkflowVersionController.php:66`        | Low (consistency)                                   |
| CF-6 | Docs/enum drift: AGENTS.md 22-status + 8-role canonical enums vs implemented engine statuses (ACTIVE/CLOSED/REJECTED/CANCELLED/ABANDONED) and 9 role codes.                                                                                                                                                                                                                                                                                     | `AGENTS.md`, `RoleCodes.php`, `EngineRequestStatus`                                       | Medium (governance/mistake source, not runtime)     |

---

## 6. Prioritized high-risk areas (drives Phase 2–5 depth)

| #   | Area                                                                                                                                                                                                                                                                               | Why                                                  |
| --- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------- |
| H1  | Role-code helpers on inactive pivots (CF-1) + everywhere `isSystemAdmin`/`hasRoleCode` gate admin power                                                                                                                                                                            | direct privilege retention                           |
| H2  | Mixed authz models: capability-driven vs role-code-driven policies; screen-permissions update surface (CF-2)                                                                                                                                                                       | escalation & drift between UI matrix and enforcement |
| H3  | `stage_permissions` wildcard rows (all-NULL matches **everyone with any org**): who can create them, does designer UI warn, do they exist in seeds? Interacts with CF-3.                                                                                                           | broadest possible grant, silent                      |
| H4  | Cross-org/cross-bank scoping of governance endpoints (teams/roles/users listing, governance impact, search, dashboards, notifications inbox, report exports download) — several controllers have zero `authorize()` and rely on inline checks; each needs positive+negative probes | data isolation                                       |
| H5  | Designer→runtime fidelity: publish validation rule pack, stage deactivation (`status` ≠ ACTIVE) mid-flight, final stages with outgoing edges, archived-version requests, `effective-executors` accuracy                                                                            | workflow integrity                                   |
| H6  | Environment gates: demo-user switch endpoints (`demo.allowed_environments`), frontend `NUXT_PUBLIC_VISUAL_BYPASS`, Horizon gate                                                                                                                                                    | auth bypass if misconfigured                         |
| H7  | Documents & FX artifacts: download policy chain, field-linked visibility suppression, signed-FX upload authorization                                                                                                                                                               | file-level leaks                                     |
| H8  | Claim lifecycle: TTL expiry mid-edit, release-on-stage-change, admin override, heartbeat race                                                                                                                                                                                      | workflow correctness                                 |
| H9  | Notifications audience derivation from stage_permissions (over/under-notification = information leak/starvation)                                                                                                                                                                   | scoped visibility                                    |
| H10 | UI states: queue/list/detail loading/empty/error/forbidden, stale-after-action, RTL, Arabic labels vs stage metadata                                                                                                                                                               | Phase 5/6                                            |

---

## 7. Proposed test matrix (Phase 8 refines; Phase 2 executes the RBAC slice)

**Fixtures:** 2 banking orgs (2 banks) + NC org + 1 "other-classification" org; users per role incl. multi-team, inactive-role, demoted-admin; 1 published workflow (claim stage, hidden/read-only/required rules, final stages ±`final_outcome`), 1 draft, 1 archived-with-live-requests.

| Axis                | Cases                                                                                                                                                                                                         |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Role × endpoint     | Every route family × {permitted role, denied role, no-org user, inactive user, demoted admin (CF-1), wrong-bank user} → expect 200/403/404 + empty-vs-scoped lists                                            |
| ID tampering        | engine request, documents, audit log, report export, notification, user/team/role of other org — direct GET/POST by ID                                                                                        |
| Stage permissions   | org-only, org+team, role-only, user-only, wildcard row; EXECUTE⊃VIEW; parity `accessibleStageIds` vs `userCanAccessStage` (parity test exists — extend with is_active variants)                               |
| Field rules         | hidden field: absent in list/detail/form-schema/documents + write rejected; read-only: unchanged passes, changed rejected (incl. type-juggled equality); required: blocks transition not draft; FILE evidence |
| Designer lifecycle  | publish validation failures, publish archives prior, existing request continues old version, archived readable, immutable edit → 409/403 code check (CF-5), stage deactivation effects, clone                 |
| Transitions         | wrong-stage transition id, inactive transition/action, final-stage outgoing, requires_comment, claim not held, stale version, duplicate submit, concurrent submit                                             |
| Screen permissions  | matrix vs update surface (CF-2), `requests` derivation incl. team-scoped rows, cache invalidation on publish/permission change, last-admin guard                                                              |
| UI (playwright-cli) | per-role login → nav visibility vs /auth/me; forbidden deep-links; instance page: viewer vs executor vs claim-holder renders; wizard validation; stale-data refresh after action; RTL spot checks             |

Levels: PHPUnit feature tests for all RBAC/engine cases (extend existing 221); Vitest for guards/composables (161 exist); playwright-cli scripted flows for UI phases; manual exploratory for UX (Phase 6).

---

## 8. Missing information (blocking or shaping later phases)

| #   | Question                                                                                                                                                                           | Blocks                          |
| --- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------- |
| M1  | Is there a runnable local environment (MySQL+Redis seeded, backend+frontend up) for dynamic testing, and may the audit create throwaway seed data?                                 | Phases 2, 4, 5 dynamic evidence |
| M2  | Production/staging values of `demo.allowed_environments`, `NUXT_PUBLIC_VISUAL_BYPASS`, `APP_ENV` — or confirmation deploy config isn't in scope                                    | H6 severity rating              |
| M3  | Intended contract for demoted admins / inactive pivot roles (CF-1): is retaining old-role access ever intended?                                                                    | CF-1 final severity             |
| M4  | Is stage-level EXECUTE (all outgoing transitions) the accepted permission granularity, or was per-action permission intended?                                                      | Phase 3 conclusions             |
| M5  | Is per-viewer (role-based) field visibility required anywhere (e.g., hide CBY-internal fields from bank users at shared stages), or is stage-scoped visibility the accepted model? | Phase 3 field-rule audit        |
| M6  | Which docs are authoritative going forward — reconcile AGENTS.md enums with the dynamic engine?                                                                                    | CF-6 remediation scope          |

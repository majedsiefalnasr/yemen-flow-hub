---
title: Architecture — Dynamic Workflow Engine (Epic 18)
status: draft
created: 2026-06-22
owner: MAJED
companion: prd-dynamic-workflow-engine.md
absorbs:
  - ENGINE-RECONCILIATION.md (keep/retire, identity mapping, DI decisions)
  - dynamic-workflow-engine/docs/backend-handoff/07-data-model.md (tables/constraints/indexes)
  - dynamic-workflow-engine/docs/backend-handoff/00-api-and-auth.md (API/auth/concurrency contract)
  - dynamic-workflow-engine/src/lib/workflow-engine/* (engine concepts)
note: >
  Durable, self-contained. Absorbs the throwaway reference app + scratch docs so they
  can be deleted. Decisions here govern Epic 18 and SUPERSEDE the fixed-workflow
  assumptions in the older planning-artifacts/architecture.md and project-context.md.
---

# Architecture — Dynamic Workflow Engine (Epic 18)

## 1. Decision: replace the core, keep the infrastructure

Locked direction (PM-mandated engine, clean start, backend-first per phase, Nuxt stays):

**KEEP (infra, engine builds on top):** Laravel app, Sanctum/JWT auth + MFA (TOTP, pin, phone) + login_history + password recovery; `audit_logs` + AuditService; notifications + notification_templates + email_deliveries + Mail services; system_settings; `banks` (as entities under the commercial-banks org); `permissions`/`role_permissions` (repurposed into the screen-permission catalog); merchants tables (canonical) + supporting services.

**RETIRE (fixed workflow core the engine replaces):**
| Retire | Replaced by |
|---|---|
| `RequestStatus` enum (21) | dynamic `workflow_stages` (seeded 8-stage IMPORT_FINANCING) |
| `WorkflowService::transition()` + `WorkflowAction` enum + `TransitionMap` | `workflow_transitions`/`workflow_actions` resolved from config; new engine transition service |
| `request_stage_history` | `workflow_history` (instance-scoped, stage IDs not enum) |
| `import_requests` typed-core (~30 cols) | `requests` (instances) + `field_definitions` + JSON `data` + Hybrid typed projection (DI-2) |
| fixed 8-value `users.role` semantics | org→team→role assignments (§3) |
| `WorkflowController` fixed endpoints | engine instance/transition endpoints |
| **Voting** — `request_votes`/`RequestVote`/VotingService/`eligible_voter_ids`/auto-abstain/voting-rule-versioning/`VoteType`/`VotingSessionStatus` | **removed (DI-3)** — EXEC = single committee-manager APPROVE/REJECT |
| `traders`/`trader_owners`/`trader_companies`/TraderService (Epic 17) | **merchants canonical (DI-5)** — trader tables retire/alias; reconcile trader PII/snapshot onto merchants |

Keep `WorkflowLockedStateException` / `WorkflowImmutableStateException` (still relevant: published-version immutability, terminal/closed-state locks).

> This SUPERSEDES `architecture.md` and `project-context.md` where they assume the fixed 21-status workflow, the 8-role enum, `WorkflowService::transition` as the only path, or executive voting.

## 2. Locked design decisions (DI-1…DI-5)

- **DI-1** Identity cardinality: model user↔team and user↔role as M:N join tables (future-proof), but seed and enforce **one team + one role** per user at the UI/validation layer until multi is needed (backend-handoff defers multi).
- **DI-2** Request storage = **Hybrid** (also mandated by data-model §"بيانات الطلب الديناميكية"): JSON `requests.data` for designer fields **plus explicit indexed columns** for fields used in filter/scope/reports (bank, merchant, status, stage, reference, amount, currency, invoice_number). Core reports never scan unindexed JSON.
- **DI-3** Executive voting **removed entirely**. EXEC stage = single committee-manager approval via a normal designer `APPROVE`/`REJECT` action; engine stays single-actor.
- **DI-4** Domain side-effects (customs/FX-confirmation PDF, financing ledger, support-claim TTL, duplicate detection) bind via **stage entry/exit hooks / action-effect registry**, not fixed status transitions. New mechanism (absent in reference app); design in Phase 3/4.
- **DI-5** `merchants`/`merchant_companies` canonical source for `DYNAMIC_SELECT`; trader tables retire/alias.

## 3. Identity model (riskiest reconciliation)

Current: `users.role` single string (`UserRole`, 8 values), `users.bank_id` FK, RBAC via `role_permissions` (role string → permission).

Target: `organization_id` + `team_id` + `role_id` per user (M:N join tables behind, one-each seeded). **Org ≠ bank**: `commercial_banks` is an org category containing all `banks` rows; `national_committee` and `system_administration` are separate orgs. Bank-org users carry `organization_id = commercial_banks` **and** `bank_id`; committee/admin users carry their org and `bank_id = null`.

Migration: add `organizations`, `teams`, `roles` (data), join tables; seed governance defaults; migrate existing `users.role` → one role assignment + inferred team; keep `users.role` as a transitional denormalized cache then drop after cutover. `role_permissions` slugs map onto the screen catalog (FR-PERM), not a fresh parallel table.

## 4. Data model

### 4.1 Tables (by area)
- **Governance:** `organizations`, `teams`, `roles`, `banks`, `users`, `screens`, `screen_permissions`.
- **Merchants:** `merchants`, `merchant_owners`, `merchant_companies`.
- **Workflow design:** `workflow_definitions`, `workflow_versions`, `workflow_stages`, `workflow_actions`, `workflow_transitions`, `stage_permissions`, `field_groups`, `field_definitions`, `stage_field_rules`.
- **Runtime:** `requests`, `request_documents`, `workflow_history`.
- **Platform:** `reference_tables`, `reference_values`, `audit_logs`, `notifications`, `notification_recipients`, `report_exports`, JWT blacklist/cache + Laravel queue tables.

### 4.2 Unique constraints
`organizations.code`; `teams(organization_id,code)`; `roles(organization_id,code)`; `banks.code` + unique nullable `swift_code`; `users.email`; `merchants.tax_number`; `merchant_companies.commercial_registration_number`; `workflow_definitions.code`; `workflow_versions(workflow_definition_id,version_number)`; `workflow_stages(workflow_version_id,code)`; `field_definitions(workflow_version_id,key)`; `workflow_actions.code`; `workflow_transitions(from_stage_id,action_id)`; `screen_permissions(role_id,screen_id,capability)`; `notification_recipients(notification_id,user_id)`.

### 4.3 Delete/deactivate policy
Governance (orgs/teams/roles/banks/users): deactivate, blocked while in use. Merchants: soft delete + deactivate. Published versions / requests / history / audit: never deleted. Used reference values: deactivate only. Documents: logical delete + audit when the request stage allows.

### 4.4 Hybrid request storage (DI-2)
`requests.data` JSON validated from field definitions, storing reference IDs/keys not labels; explicit indexed columns for bank, merchant, status, stage, reference, amount, currency, invoice_number. Indexes per NFR5.

### 4.5 Engine runtime types (from reference engine)
`WorkflowInstance{ workflow_version_id, current_stage_id, status: ACTIVE|CLOSED|REJECTED, data, created_by, ... }`; `WorkflowHistory{ instance, from_stage_id, to_stage_id, action_code, performed_by, comments, timestamp }`. Stage permission match: set fields within a row AND; rows OR; EXECUTE⊇VIEW.

## 5. API & auth contract

Base `/api/v1`, JSON snake_case; every resource returns `id/created_at/updated_at/version`. Lists: `page`/`per_page`(25/100)/`search`/`sort`/`direction` → `{data, meta}`. Errors `{ error:{ code, message, fields, request_id } }`, codes 401/403/404/409/422/429. JWT: short access (Bearer) + HttpOnly refresh cookie + blacklist; deactivation/sensitive-perm-change invalidates sessions; TOTP MFA; rate limits; no tokens in localStorage. `/auth/me` returns user + organization + team + role + bank|null + computed screen permissions + general capabilities. Concurrency: `version` on editable records; mismatch → `409 STALE_RESOURCE`; transitions in DB transaction + row lock; no two transitions per version. Files: private disk, metadata+path in DB, authorized download endpoint, backend type/size/extension validation. Full endpoint surface enumerated per-module in the PRD FR sections (governance, merchants, designer, requests, audit/reports, reference/permissions/notifications).

## 6. Engine transition execution (replaces WorkflowService)

`POST /requests/{id}/actions` `{transition_id, comment, data, version}` in one transaction: lock request → check version → check current stage → check `EXECUTE` (from `stage_permissions`) → validate `stage_field_rules` + comment (`requires_comment`) → update `data`/`current_stage_id`/`status` + Hybrid columns → append `workflow_history` → append `audit_logs` → run DI-4 stage hooks (customs/ledger/etc.) → queue notification jobs after commit. Validate-before-publish (`/workflow-versions/{id}/validate`) guards config integrity; publish rejects invalid configs.

## 7. Net-new work beyond the reference app

1. All backend phases (migrations/models/policies/resources/feature-tests/OpenAPI) — reference app is localStorage-only.
2. Stage entry/exit hooks for domain side-effects (DI-4).
3. Hybrid typed/JSON storage + sync (DI-2).
4. Identity migration of existing users (§3).
5. Concurrency-safe transitions (parity with current `WorkflowService` locking).
(Voting is NOT net-new — DI-3 removed it.)

## 8. Build order (dependency-justified)

Phase 0 foundation (engine core, identity model, governance+engine+runtime tables, seed, ScreenGuard/RoleGuard primitives, stage-permission resolver) → Phase 1 governance → Phase 2 merchants → Phase 2.5 reference-data foundation (pulled early; designer fields depend on it) → Phase 3 designer (stages→actions→transitions→stage-permissions→field-groups→fields→field-rules→graph→validate/publish→versioning) → Phase 4 requests + دوري queue (instances, DynamicForm, concurrency-safe transitions, history, stage-scoped queue, DI-4 hooks) → Phase 5 audit + reports → Phase 6 reference-data UI + screen-permissions admin + notifications.

Each phase backend-first: finalize migration/model/policy/form-requests/resources/feature-tests/OpenAPI, then build + wire the Nuxt screen against it.
